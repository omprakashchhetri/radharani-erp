<?php
namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** A subcategory under a metal (Gold > Earrings). */
class ItemCategory extends Model
{
    use LogsActivity;

    public const METALS = ['gold' => 'Gold', 'silver' => 'Silver', 'diamond' => 'Diamond'];

    protected $fillable = ['metal', 'name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['metal', 'name', 'sort_order', 'is_active'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('stock');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'category_id');
    }

    /** Diamond jewellery gets the diamond fields (carat weight, cut, summary number) on its pieces. */
    public function isDiamond(): bool
    {
        return self::nameIsDiamond($this->name);
    }

    public static function nameIsDiamond(?string $name): bool
    {
        return str_contains(mb_strtolower((string) $name), 'diamond');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForMetal($q, ?string $metal)
    {
        return $q->where('metal', $metal ?: 'gold');
    }

    /** URL-safe key used by the website: gold-earrings, silver-earrings. */
    public function getSlugAttribute(): string
    {
        return $this->metal . '-' . \Illuminate\Support\Str::slug($this->name);
    }

    public function getFullNameAttribute(): string
    {
        return (self::METALS[$this->metal] ?? ucfirst($this->metal)) . ' > ' . $this->name;
    }
}
