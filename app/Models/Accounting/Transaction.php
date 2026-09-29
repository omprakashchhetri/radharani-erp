<?php
namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Transaction extends Model
{
    public $timestamps = false;
    // $timestamps=false also disables Eloquent's default created_at
    // date-casting, so it needs an explicit cast or ->format() calls on it
    // (Ledger View) fatal-error on a raw string.
    protected $casts = ['created_at' => 'datetime'];
    protected $fillable = [
        'account_id', 'reference_type', 'reference_id', 'debit', 'credit', 'note', 'created_by',
    ];

    protected static function booted()
    {
        static::creating(fn ($t) => $t->created_at ??= now());
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
