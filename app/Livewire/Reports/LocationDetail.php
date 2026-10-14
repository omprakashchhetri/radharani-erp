<?php
namespace App\Livewire\Reports;

use App\Models\Location;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use Livewire\Component;

/** What is at one location right now, and how it got there (8 Oct change list, 3.1). */
class LocationDetail extends Component
{
    public Location $location;

    public function mount(Location $location): void
    {
        $this->location = $location;
    }

    public function render()
    {
        $entries = Location::floorEntries();
        $vault = Location::vault();
        $isVault = $this->location->id === $vault?->id;

        // Containers recorded as being here (the vault holds everything not recorded elsewhere).
        $here = $entries->filter(fn ($e) => $e['location_id'] === $this->location->id);
        $boxes = Box::whereIn('id', $here->where('type', 'box')->pluck('id'))->get();
        $packets = Packet::with('box:id,code')->whereIn('id', $here->where('type', 'packet')->pluck('id'))->get();

        $ids = collect(Location::itemLocations())->filter(fn ($l) => $l === $this->location->id)->keys();
        $items = Item::with('packet.box')->whereIn('id', $ids)->orderBy('category')->limit(300)->get();

        // The vault lists its trays; a tray points back to the vault.
        $at = Location::itemLocations();
        $weights = Item::where('status', 'in_stock')->pluck('weight', 'id');
        $trays = Location::where('type', 'tray')->where('parent_id', $this->location->id)->orderBy('sort_order')->orderBy('id')->get()->map(function ($tray) use ($at, $weights) {
            $trayIds = array_keys(array_filter($at, fn ($l) => $l === $tray->id));

            return ['location' => $tray, 'qty' => count($trayIds), 'weight' => collect($trayIds)->sum(fn ($id) => (float) ($weights[$id] ?? 0))];
        });

        $recent = $this->location->movements()->with('user:id,name', 'doneBy:id,name')->latest('id')->limit(15)->get();

        return view('livewire.reports.location-detail', [
            'boxes' => $boxes,
            'packets' => $packets,
            'items' => $items,
            'itemCount' => $ids->count(),
            'weight' => Item::whereIn('id', $ids)->sum('weight'),
            'isVault' => $isVault,
            'trays' => $trays,
            'parentLocation' => $this->location->isTray() ? $this->location->parent : null,
            'recent' => $recent,
        ])->layout('components.layouts.app', ['title' => $this->location->name . ' · Radharani Jewellery ERP']);
    }
}
