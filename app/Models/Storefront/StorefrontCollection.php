<?php
namespace App\Models\Storefront;

use App\Models\Stock\Item;
use App\Support\StorefrontImage;
use Illuminate\Database\Eloquent\Model;

class StorefrontCollection extends Model
{
    protected $fillable = ['slug', 'name', 'blurb', 'image', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return StorefrontImage::url($this->image);
    }
}
