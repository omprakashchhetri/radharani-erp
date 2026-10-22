<?php
namespace App\Models\Customer;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Money a customer paid in with no order behind it, held on their account until a sale uses it. Insert-only. */
class CustomerAdvance extends Model
{
    public const MODES = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank', 'cheque' => 'Cheque'];

    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

    protected $fillable = ['customer_id', 'kind', 'amount', 'mode', 'occasion', 'sale_id', 'note', 'user_id', 'created_at'];

    protected static function booted()
    {
        static::creating(fn ($a) => $a->created_at ??= now());
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
