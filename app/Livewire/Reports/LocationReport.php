<?php
namespace App\Livewire\Reports;

use App\Models\Stock\Item;
use App\Services\PricingService;
use Livewire\Component;

/**
 * Location Report — stock quantity and value by current location.
 *
 * Same derived-location approach as the Owner Dashboard (Item::currentMovement()).
 * Value uses PricingService::priceFor() per item — the same live-calculated
 * price used everywhere else (rule 2: never store a calculated price). For
 * a large catalogue this means pricing every item on every page load; fine
 * at this shop's scale, flagged as a spot to cache if the catalogue grows.
 */
class LocationReport extends Component
{
    public function render()
    {
        $pricing = app(PricingService::class);

        $items = Item::all()->groupBy(function ($item) {
            if ($item->status === 'sold') return 'Sold';
            if ($item->status === 'in_stock') return 'Vault / Counter';
            $m = $item->currentMovement();
            return $m ? ucfirst(str($m->movement_type)->before('_out')->toString()) : 'Unknown';
        });

        $rows = $items->map(fn ($group, $location) => [
            'location' => $location,
            'qty' => $group->count(),
            'weight' => $group->sum('weight'),
            'value' => $group->sum(fn ($item) => $pricing->priceFor($item)),
        ])->sortByDesc('value')->values();

        return view('livewire.reports.location-report', [
            'rows' => $rows,
            'stats' => [
                'locations' => $rows->count(),
                'items' => $rows->sum('qty'),
                'weight' => $rows->sum('weight'),
                'value' => $rows->sum('value'),
            ],
        ])->layout('components.layouts.app', ['title' => 'Location Report — Radharani Jewellery ERP']);
    }
}
