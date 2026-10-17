<?php
namespace App\Services;

use App\Models\Customer\Customer;
use App\Models\Movement\KarigarPayment;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Purchase\Vendor;
use Illuminate\Support\Collection;

/**
 * Party ledgers (8 Oct change list, section 14): weights and counts only, never money.
 * One per karigar and one per hallmarking centre.
 */
class LedgerService
{
    public const KARIGAR_COLUMNS = ['Date', 'Batch', 'Pieces out', 'Pieces in', 'Issued (g)', 'Received (g)', 'Loss (g)', 'Metal paid (g)', 'Balance (g)'];

    /**
     * Rows for a party, oldest first. Each row: date, label, cells[], and a 'kind' for styling.
     * Balance on a batch row is the metal still with the karigar for that batch: the advance
     * metal given less what came back and the loss typed in (never below zero).
     *
     * @return array{columns: array<int, string>, rows: Collection, totals: array<int, mixed>}
     */
    public function forParty(Vendor $party): array
    {
        return $party->type === 'hallmark_center' ? $this->hallmarker($party) : $this->karigar($party);
    }

    /**
     * One line per event, never two events on one line (17 Oct): the issue of a batch, each part receipt and each metal
     * payment are rows of their own. The balance is the metal still with the karigar after that line.
     */
    private function karigar(Vendor $party): array
    {
        $batches = KarigarRawBatch::where('vendor_id', $party->id)->with('receipts')->orderBy('id')->get();

        $events = collect();
        foreach ($batches as $b) {
            $events->push(['at' => $b->created_at, 'sort' => 0, 'type' => 'issue', 'batch' => $b]);
            foreach ($b->receipts as $r) {
                $events->push(['at' => $r->created_at, 'sort' => 1, 'type' => 'receipt', 'batch' => $b, 'receipt' => $r]);
            }
            if ($b->status === 'closed' && $b->closed_at) {
                $events->push(['at' => $b->closed_at, 'sort' => 2, 'type' => 'closed', 'batch' => $b]);
            }
        }
        foreach (KarigarPayment::where('vendor_id', $party->id)->where('kind', 'metal')->orderBy('id')->get() as $p) {
            $events->push(['at' => $p->created_at, 'sort' => 1, 'type' => 'payment', 'payment' => $p]);
        }

        $rows = collect();
        $tot = ['out' => 0, 'in' => 0, 'issued' => 0.0, 'received' => 0.0, 'loss' => 0.0, 'paid' => 0.0];
        $held = []; // batch id => metal still held after the latest line
        $in = [];   // batch id => [received, loss] so far

        foreach ($events->sortBy(fn ($e) => [$e['at']->timestamp, $e['sort']])->values() as $e) {
            $cells = [null, null, null, null, null, null, null];
            if ($e['type'] === 'issue') {
                $b = $e['batch'];
                $issued = (float) $b->advance_metal_weight;
                $held[$b->id] = $issued;
                $in[$b->id] = [0.0, 0.0];
                $cells = [$b->pieces_expected, null, $issued, null, null, null, null];
                $label = $b->label . ' · issued';
                $tot['out'] += $b->pieces_expected;
                $tot['issued'] += $issued;
            } elseif ($e['type'] === 'receipt') {
                $b = $e['batch'];
                $r = $e['receipt'];
                $in[$b->id][0] += (float) $r->weight_received;
                $in[$b->id][1] += (float) $r->weight_loss;
                $issued = (float) $b->advance_metal_weight;
                $held[$b->id] = $issued > 0 ? max(0.0, $issued - $in[$b->id][0] - $in[$b->id][1]) : 0.0;
                $cells = [null, (int) $r->pieces, null, (float) $r->weight_received, (float) $r->weight_loss, null, null];
                $label = $b->label . ' · received back';
                $tot['in'] += (int) $r->pieces;
                $tot['received'] += (float) $r->weight_received;
                $tot['loss'] += (float) $r->weight_loss;
            } elseif ($e['type'] === 'closed') {
                $label = $e['batch']->label . ' · closed' . ($e['batch']->close_note ? ': ' . $e['batch']->close_note : '');
            } else {
                $p = $e['payment'];
                $cells = [null, null, null, null, null, (float) $p->weight, null];
                $label = 'Metal paid: ' . ucfirst($p->metal) . ' ' . $p->purity;
                $tot['paid'] += (float) $p->weight;
            }
            $cells[6] = round(array_sum($held), 3);
            $rows->push(['at' => $e['at'], 'label' => $label, 'cells' => $cells]);
        }

        return [
            'columns' => self::KARIGAR_COLUMNS,
            'rows' => $rows,
            'totals' => [$tot['out'], $tot['in'], $tot['issued'], $tot['received'], $tot['loss'], $tot['paid'], round(array_sum($held), 3)],
        ];
    }

    /**
     * A customer's ledger (8 Oct change list, 14.1): what they were billed, what they paid and what is
     * still due, oldest first. Unlike the karigar and hallmarker ledgers this one is money.
     */
    public function forCustomer(Customer $customer): array
    {
        $rows = collect();
        $billed = 0.0;
        $paid = 0.0;
        $running = 0.0;

        $sales = $customer->sales()->with('payments')->orderBy('id')->get();
        $events = collect();
        foreach ($sales as $sale) {
            $events->push(['at' => $sale->created_at, 'label' => 'Sale ' . $sale->bill_number . ' (' . $sale->items()->count() . ' ' . \Illuminate\Support\Str::plural('piece', $sale->items()->count()) . ')', 'billed' => (float) $sale->total, 'paid' => 0.0]);
            foreach ($sale->payments as $p) {
                $events->push(['at' => $p->created_at, 'label' => 'Paid by ' . \App\Models\Sales\SalePayment::MODES[$p->mode] . ' for ' . $sale->bill_number, 'billed' => 0.0, 'paid' => (float) $p->amount]);
            }
        }

        // Exchanges and what became of their value. They are not money billed or paid (a sale that uses the credit
        // shows it as a payment above), so these rows are notes: they sit in the history without moving the balance.
        $exchanges = \App\Models\Exchange\ExchangeTransaction::where('customer_id', $customer->id)->with('applications')->orderBy('id')->get();
        foreach ($exchanges as $x) {
            $metal = ucfirst($x->metal);
            $events->push(['at' => $x->created_at, 'info' => true, 'label' => "Exchange #{$x->id} received: " . rtrim(rtrim(number_format((float) $x->gross_weight, 3), '0'), '.') . " g old {$metal}" . ($x->stage === 'settled' ? '' : ' (' . ['received' => 'being melted', 'melted' => 'being tested', 'tested' => 'waiting for the final value', 'valued' => 'waiting for the final value'][$x->stage] . ')')]);
            if ($x->stage === 'settled') {
                $events->push(['at' => $x->settled_at ?? $x->updated_at, 'info' => true, 'label' => "Exchange #{$x->id} settled: " . rtrim(rtrim(number_format((float) $x->net_weight, 3), '0'), '.') . " g at " . rtrim(rtrim(number_format((float) $x->purity_averaged, 2), '0'), '.') . "%, value ₹" . number_format((float) $x->final_value, 2) . ' held as credit']);
            }
            foreach ($x->applications as $a) {
                if ($a->kind === 'sale') {
                    continue; // already a payment on that sale
                }
                $amount = '₹' . number_format(abs((float) $a->amount), 2);
                $text = match (true) {
                    $a->kind === 'cash' && $a->amount < 0 => "Exchange #{$x->id}: {$amount} cash payout reversed",
                    $a->kind === 'cash' => "Exchange #{$x->id}: {$amount} paid out in cash",
                    $a->amount < 0 => "Exchange #{$x->id}: {$amount} credit returned from order #{$a->applied_id}",
                    default => "Exchange #{$x->id}: {$amount} put towards the advance of order #{$a->applied_id}",
                };
                $events->push(['at' => $a->created_at, 'info' => true, 'label' => $text]);
            }
        }
        // Advance money on orders. It becomes payments on the bill when the order is sold, so until then these are notes too.
        foreach (\App\Models\Orders\Order::where('customer_id', $customer->id)->with('payments')->orderBy('id')->get() as $o) {
            if ((float) $o->advance_amount > 0) {
                $events->push(['at' => $o->created_at, 'info' => true, 'label' => "Advance ₹" . number_format((float) $o->advance_amount, 2) . " received on order #{$o->id}" . ($o->converted_sale_id ? '' : ', counted on the bill when it is sold')]);
            }
            foreach ($o->payments as $p) {
                $events->push(['at' => $p->created_at, 'info' => true, 'label' => "Advance ₹" . number_format((float) $p->amount, 2) . " received on order #{$o->id} by " . \App\Models\Orders\OrderPayment::MODES[$p->mode] . ($o->converted_sale_id ? '' : ', counted on the bill when it is sold')]);
            }
        }
        // Advance paid in with no order behind it: notes too (a sale that uses it shows it as a payment above).
        foreach (\App\Models\Customer\CustomerAdvance::where('customer_id', $customer->id)->where('kind', 'received')->orderBy('id')->get() as $a) {
            $events->push(['at' => $a->created_at, 'info' => true, 'label' => 'Advance ₹' . number_format((float) $a->amount, 2) . ' received by ' . \App\Models\Customer\CustomerAdvance::MODES[$a->mode] . ($a->occasion ? " ({$a->occasion})" : '') . ', held until a sale uses it']);
        }
        $credit = round($exchanges->sum(fn ($x) => $x->creditRemaining()), 2);
        $advance = app(AdvanceService::class)->balance($customer->id);

        foreach ($events->sortBy(fn ($e) => $e['at']->timestamp)->values() as $e) {
            if ($e['info'] ?? false) {
                $rows->push(['at' => $e['at'], 'label' => $e['label'], 'info' => true, 'cells' => [null, null, null]]);

                continue;
            }
            $running += $e['billed'] - $e['paid'];
            $billed += $e['billed'];
            $paid += $e['paid'];
            $rows->push(['at' => $e['at'], 'label' => $e['label'], 'cells' => [$e['billed'], $e['paid'], round($running, 2)]]);
        }

        return ['columns' => ['Date', 'Description', 'Billed (₹)', 'Paid (₹)', 'Balance (₹)'], 'rows' => $rows, 'totals' => [round($billed, 2), round($paid, 2), round($billed - $paid, 2)],
            'notes' => array_values(array_filter([
                $credit > 0 ? 'Exchange credit available to spend: ₹' . number_format($credit, 2) : null,
                $advance > 0 ? 'Advance held on the account: ₹' . number_format($advance, 2) : null,
            ]))];
    }

    // Filled in with the hallmarking rebuild (section 7).
    private function hallmarker(Vendor $party): array
    {
        return app(HallmarkLedger::class)->forCentre($party);
    }

    /** Summary line per party for the ledger index. */
    public function summary(string $kind): Collection
    {
        $type = $kind === 'hallmarker' ? 'hallmark_center' : 'karigar';

        return Vendor::where('type', $type)->orderBy('name')->get()->map(function (Vendor $v) {
            $l = $this->forParty($v);

            return ['party' => $v, 'last' => $l['rows']->last()['at'] ?? null, 'totals' => $l['totals']];
        });
    }
}
