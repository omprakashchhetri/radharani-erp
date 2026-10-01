<?php
namespace App\Livewire\Website;

use App\Models\Stock\Item;
use App\Models\Storefront\StorefrontCategory;
use App\Services\StorefrontCatalog;
use Illuminate\Database\Eloquent\Model;

/**
 * Website > Categories. A website category gathers one or more stock
 * categories (as typed on pieces), so publishing a piece never means
 * re-categorising it in Stock.
 */
class CategoryManager extends GroupManager
{
    public string $stockCategories = ''; // comma separated
    public bool $in_menu = false;

    protected function model(): string
    {
        return StorefrontCategory::class;
    }

    protected function imageDirectory(): string
    {
        return 'storefront/categories';
    }

    protected function rules(): array
    {
        return parent::rules() + ['stockCategories' => ['nullable', 'string', 'max:500']];
    }

    protected function loadExtra(Model $group): void
    {
        $this->stockCategories = implode(', ', $group->stock_categories ?? []);
        $this->in_menu = (bool) $group->in_menu;
    }

    protected function resetExtra(): void
    {
        $this->stockCategories = '';
        $this->in_menu = false;
    }

    protected function extraData(): array
    {
        return [
            'stock_categories' => collect(explode(',', $this->stockCategories))
                ->map(fn ($c) => trim($c))->filter()->unique(fn ($c) => mb_strtolower($c))->values()->all(),
            'in_menu' => $this->in_menu,
        ];
    }

    public function render()
    {
        $categories = $this->applySorting(StorefrontCategory::query()
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('stock_categories', 'like', "%{$this->search}%"))))
            ->paginate($this->perPageValue());

        // Pieces live on the site per website category, and stock categories
        // that no website category picks up yet (those pieces can't be listed).
        $live = app(StorefrontCatalog::class)->pieces()->countBy('category');
        $claimed = collect(StorefrontCategory::lookup())->keys();
        $unmapped = Item::query()->where('status', '!=', 'sold')->distinct()->orderBy('category')->pluck('category')
            ->reject(fn ($c) => $claimed->contains(mb_strtolower(trim($c))))->values();

        return view('livewire.website.group-manager', [
            'groups' => $categories,
            'live' => $live,
            'isCategory' => true,
            'unmapped' => $unmapped,
            'stockCategoryOptions' => $this->showForm ? Item::query()->distinct()->orderBy('category')->pluck('category') : collect(),
        ])->layout('components.layouts.app', ['title' => 'Website categories — Radharani Jewellery']);
    }
}
