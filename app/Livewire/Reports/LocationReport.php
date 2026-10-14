<?php
namespace App\Livewire\Reports;

use App\Models\Location;
use App\Models\Movement\Movement;
use App\Models\Stock\Item;
use Livewire\Component;

/**
 * Location Report: pieces and weight by where they are right now. In-store places
 * are the owner-managed locations; pieces away from the shop are grouped by where
 * they went. Weight and counts only (8 Oct change list, 1.7 and 3.1).
 */
class LocationReport extends Component
{
    public function render()
    {
        $at = Location::itemLocations(); // in-stock pieces, item_id => location_id
        $weights = Item::where('status', 'in_stock')->pluck('weight', 'id');

        $locations = Location::orderBy('sort_order')->orderBy('id')->get();
        $countAt = function ($loc) use ($at, $weights) {
            $ids = array_keys(array_filter($at, fn ($l) => $l === $loc->id));

            return ['qty' => count($ids), 'weight' => collect($ids)->sum(fn ($id) => (float) ($weights[$id] ?? 0))];
        };

        // A place with trays in it (the vault) shows everything in it, trays included, with its trays listed under it.
        $rows = collect();
        foreach ($locations->where('type', '!=', 'tray') as $loc) {
            $own = $countAt($loc);
            $trays = $locations->where('type', 'tray')->where('parent_id', $loc->id)->map(fn ($tray) => ['location' => $tray, 'label' => $tray->name, 'indent' => true] + $countAt($tray));

            $rows->push(['location' => $loc, 'label' => $loc->name, 'indent' => false, 'trays' => $trays->count(),
                'qty' => $own['qty'] + $trays->sum('qty'), 'weight' => $own['weight'] + $trays->sum('weight')]);
            $trays->each(fn ($t) => $rows->push($t));
        }

        // Away from the shop or in a process: grouped by what the piece is doing.
        $away = Item::whereIn('status', ['dispatched', 'pending_review', 'reserved'])->get(['id', 'status', 'weight'])
            ->groupBy(function ($item) {
                if ($item->status !== 'dispatched') {
                    return $item->status === 'pending_review' ? 'Awaiting review' : 'Held for a sale';
                }
                $m = $item->currentMovement();

                return $m ? Movement::LABELS[$m->movement_type] ?? 'Away' : 'Away';
            })->map(fn ($g, $label) => ['location' => null, 'label' => $label, 'qty' => $g->count(), 'weight' => $g->sum('weight')])->values();

        $all = $rows->concat($away);
        $counted = $all->reject(fn ($r) => $r['indent'] ?? false); // trays are already inside their vault's figure

        return view('livewire.reports.location-report', [
            'rows' => $all,
            'stats' => [
                'locations' => $rows->reject(fn ($r) => $r['indent'])->count(),
                'items' => $counted->sum('qty'),
                'weight' => $counted->sum('weight'),
            ],
        ])->layout('components.layouts.app', ['title' => 'Location Report · Radharani Jewellery ERP']);
    }
}
