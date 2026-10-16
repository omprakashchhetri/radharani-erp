<?php
namespace App\Models\Orders;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Advance money received for an order after it was placed. Insert-only. */
class OrderPayment extends Model
{
    public const MODES = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank', 'cheque' => 'Cheque'];

    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

    protected $fillable = ['order_id', 'amount', 'mode', 'note', 'user_id', 'created_at'];

    protected static function booted()
    {
        static::creating(fn ($p) => $p->created_at ??= now());
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
