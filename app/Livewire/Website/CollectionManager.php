<?php
namespace App\Livewire\Website;

use App\Models\Storefront\StorefrontCollection;
use App\Services\StorefrontCatalog;

/** Website > Collections (Mayur, Temple...). Pieces join one from their Website tab. */
class CollectionManager extends GroupManager
{
    protected function model(): string
    {
        return StorefrontCollection::class;
    }

    protected function imageDirectory(): string
    {
        return 'storefront/collections';
    }

    public function render()
    {
        $collections = $this->applySorting(StorefrontCollection::query()
            ->withCount('items')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")))
            ->paginate($this->perPageValue());

        return view('livewire.website.group-manager', [
            'groups' => $collections,
            'live' => app(StorefrontCatalog::class)->pieces()->whereNotNull('collection')->countBy('collection'),
            'isCategory' => false,
            'unmapped' => collect(),
            'stockCategoryOptions' => collect(),
        ])->layout('components.layouts.app', ['title' => 'Website collections — Radharani Jewellery']);
    }
}
