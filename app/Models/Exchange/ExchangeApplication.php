<?php
namespace App\Models\Exchange;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Where some of an exchange's value went. Insert-only: a cancelled order's credit returns as a negative row. */
class ExchangeApplication extends Model
{
    public const KINDS = ['sale' => 'Sale', 'order' => 'Order', 'cash' => 'Paid in cash'];

    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

    protected $fillable = ['exchange_transaction_id', 'kind', 'applied_id', 'amount', 'note', 'user_id', 'created_at'];

    protected static function booted()
    {
        static::creating(fn ($a) => $a->created_at ??= now());
    }

    public function exchange()
    {
        return $this->belongsTo(ExchangeTransaction::class, 'exchange_transaction_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
