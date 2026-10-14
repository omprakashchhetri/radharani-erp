<?php
namespace App\Models\Sales;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One part of a bill's payment. Insert-only: the balance is worked out from these. */
class SalePayment extends Model
{
    // 'exchange' is never picked from the list: it appears only when a customer's exchange credit is used.
    public const MODES = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank', 'cheque' => 'Cheque', 'exchange' => 'Exchange credit'];

    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

    protected $fillable = ['sale_id', 'mode', 'exchange_transaction_id', 'amount', 'note', 'user_id', 'created_at'];

    protected static function booted()
    {
        static::creating(fn ($p) => $p->created_at ??= now());
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
