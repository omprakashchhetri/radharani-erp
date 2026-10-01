<?php
namespace App\Models\Storefront;

use App\Support\StorefrontImage;
use Illuminate\Database\Eloquent\Model;

class StorefrontCategory extends Model
{
    protected $fillable = ['slug', 'name', 'blurb', 'image', 'stock_categories', 'sort_order', 'in_menu', 'is_active'];

    protected $casts = [
        'stock_categories' => 'array',
        'in_menu' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    // Item category strings (lower-cased) that belong to this website category.
    // The category's own name always counts, so "Earrings" items land in
    // "Earrings" without anyone having to list it.
    public function stockCategoryKeys(): array
    {
        return collect($this->stock_categories ?? [])
            ->push($this->name)
            ->map(fn ($c) => mb_strtolower(trim((string) $c)))
            ->filter()->unique()->values()->all();
    }

    public function getImageUrlAttribute(): ?string
    {
        return StorefrontImage::url($this->image);
    }

    // Stock category (as typed on the item) -> website category, for every active category.
    public static function lookup(): array
    {
        $map = [];
        foreach (static::active()->get() as $category) {
            foreach ($category->stockCategoryKeys() as $key) {
                $map[$key] ??= $category;
            }
        }

        return $map;
    }

    public static function forStockCategory(?string $stockCategory): ?self
    {
        return static::lookup()[mb_strtolower(trim((string) $stockCategory))] ?? null;
    }
}
