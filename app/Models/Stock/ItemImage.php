<?php
namespace App\Models\Stock;

use App\Support\StorefrontImage;
use Illuminate\Database\Eloquent\Model;

class ItemImage extends Model
{
    protected $fillable = ['item_id', 'path', 'sort_order', 'created_by'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function getUrlAttribute(): ?string
    {
        return StorefrontImage::url($this->path);
    }
}
