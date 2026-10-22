<?php
namespace App\Services;

use App\Models\Movement\HallmarkBatch;
use App\Models\Purchase\Vendor;

/** The ledger of one hallmarking centre: counts and weights only (8 Oct change list, 7.4). */
class HallmarkLedger
{
    public const COLUMNS = ['Date', 'Batch', 'Pieces out', 'Pieces in', 'Weight out (g)', 'Weight in (g)', 'Loss (g)', 'Pieces pending'];

    /** One line per event (17 Oct): a dispatch and each part return are separate rows, never pieces in and out on one line. */
    public function forCentre(Vendor $centre): array
    {
        $batches = HallmarkBatch::where('vendor_id', $centre->id)->withCount('lines')->withSum('lines', 'weight_out')->with('receipts')->orderBy('id')->get();

        $events = collect();
        foreach ($batches as $b) {
            $events->push(['at' => $b->created_at, 'sort' => 0, 'type' => 'out', 'batch' => $b]);
            foreach ($b->receipts as $r) {
                $events->push(['at' => $r->created_at, 'sort' => 1, 'type' => 'in', 'batch' => $b, 'receipt' => $r]);
            }
            if ($b->status === 'closed' && $b->closed_at) {
                $events->push(['at' => $b->closed_at, 'sort' => 2, 'type' => 'closed', 'batch' => $b]);
            }
        }

        $rows = collect();
        $tot = ['out' => 0, 'in' => 0, 'wout' => 0.0, 'win' => 0.0, 'loss' => 0.0];
        $pending = []; // batch id => pieces still out after the latest line
        $back = [];

        foreach ($events->sortBy(fn ($e) => [$e['at']->timestamp, $e['sort']])->values() as $e) {
            $b = $e['batch'];
            $cells = [null, null, null, null, null, null];
            if ($e['type'] === 'out') {
                $out = $b->pieces_out;
                $wout = (float) $b->weight_counted + (float) ($b->lines_sum_weight_out ?? 0);
                $pending[$b->id] = $out;
                $back[$b->id] = 0;
                $cells = [$out, null, $wout, null, null, null];
                $label = $b->label . ' · sent';
                $tot['out'] += $out;
                $tot['wout'] += $wout;
            } elseif ($e['type'] === 'in') {
                $r = $e['receipt'];
                $back[$b->id] += (int) $r->pieces;
                $pending[$b->id] = max(0, $b->pieces_out - $back[$b->id]);
                $cells = [null, (int) $r->pieces, null, (float) $r->weight_received, (float) $r->weight_loss, null];
                $label = $b->label . ' · received back';
                $tot['in'] += (int) $r->pieces;
                $tot['win'] += (float) $r->weight_received;
                $tot['loss'] += (float) $r->weight_loss;
            } else {
                $pending[$b->id] = 0; // a closed batch has nothing pending any more
                $label = $b->label . ' · closed' . ($b->close_note ? ': ' . $b->close_note : '');
            }
            $cells[5] = array_sum($pending);
            $rows->push(['at' => $e['at'], 'label' => $label, 'cells' => $cells]);
        }

        return [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'totals' => [$tot['out'], $tot['in'], $tot['wout'], $tot['win'], $tot['loss'], array_sum($pending)],
        ];
    }
}
