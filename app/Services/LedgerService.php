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

    private function karigar(Vendor $party): array
    {
        $batches = KarigarRawBatch::where('vendor_id', $party->id)->withSum('receipts', 'pieces')
            ->withSum('receipts', 'weight_received')->withSum('receipts', 'weight_loss')->orderBy('id')->get();

        $rows = collect();
        $tot = ['out' => 0, 'in' => 0, 'issued' => 0.0, 'received' => 0.0, 'loss' => 0.0, 'paid' => 0.0, 'balance' => 0.0];

        foreach ($batches as $b) {
            $issued = (float) $b->advance_metal_weight;
            $received = (float) ($b->receipts_sum_weight_received ?? 0);
            $loss = (float) ($b->receipts_sum_weight_loss ?? 0);
            $in = (int) ($b->receipts_sum_pieces ?? 0);
            $balance = $issued > 0 ? max(0.0, $issued - $received - $loss) : 0.0;

            $rows->push([
                'at' => $b->created_at,
                'label' => $b->label . ($b->status === 'closed' ? ' (closed)' : ''),
                'cells' => [$b->pieces_expected, $in, $issued, $received, $loss, 0.0, $balance],
            ]);
            $tot['out'] += $b->pieces_expected;
            $tot['in'] += $in;
            $tot['issued'] += $issued;
            $tot['received'] += $received;
            $tot['loss'] += $loss;
            $tot['balance'] += $balance;
        }

        foreach (KarigarPayment::where('vendor_id', $party->id)->where('kind', 'metal')->orderBy('id')->get() as $p) {
            $rows->push([
                'at' => $p->created_at,
                'label' => 'Metal paid: ' . ucfirst($p->metal) . ' ' . $p->purity,
                'cells' => [null, null, 0.0, 0.0, 0.0, (float) $p->weight, null],
            ]);
            $tot['paid'] += (float) $p->weight;
        }

        return [
            'columns' => self::KARIGAR_COLUMNS,
            'rows' => $rows->sortBy(fn ($r) => $r['at']->timestamp)->values(),
            'totals' => [$tot['out'], $tot['in'], $tot['issued'], $tot['received'], $tot['loss'], $tot['paid'], $tot['balance']],
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
        $credit = round($exchanges->sum(fn ($x) => $x->creditRemaining()), 2);

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
            'notes' => $credit > 0 ? ['Exchange credit available to spend: ₹' . number_format($credit, 2)] : []];
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
