<?php
namespace App\Models\Exchange;

use App\Models\Customer\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ExchangeTransaction extends Model
{
    use LogsActivity;

    // Every edit before settlement is logged with its before and after (8 Oct change list, 9.1).
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['customer_id', 'metal', 'gross_weight', 'description', 'net_weight', 'purity_test_1', 'purity_test_2', 'purity_averaged', 'preset_deduction_percent', 'deductable_weight', 'stage', 'final_value'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('exchange');
    }

    public function getIsSettledAttribute(): bool
    {
        return $this->stage === 'settled';
    }

    protected $fillable = [
        'customer_id', 'metal', 'gross_weight', 'description', 'net_weight',
        'purity_test_1', 'purity_test_2', 'purity_averaged',
        'preset_deduction_percent', 'deductable_weight', 'stage',
        'final_value', 'settled_by', 'settled_at', 'created_by',
    ];

    protected $casts = ['settled_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function applications()
    {
        return $this->hasMany(ExchangeApplication::class);
    }

    /** What has gone out of this exchange's value so far (sales, orders, cash), less anything returned. */
    public function appliedTotal(): float
    {
        return round((float) $this->applications()->sum('amount'), 2);
    }

    /** The value of a settled exchange not yet spent: the customer's exchange credit. */
    public function creditRemaining(): float
    {
        return $this->stage === 'settled' ? max(0.0, round((float) $this->final_value - $this->appliedTotal(), 2)) : 0.0;
    }

    /** The pure metal in the net weight at the tested purity. Shown, never stored. */
    public function pureWeight(): ?float
    {
        return $this->net_weight !== null && $this->purity_averaged !== null ? round((float) $this->net_weight * (float) $this->purity_averaged / 100, 3) : null;
    }

    /** The pure metal left after the preset deduction. Shown, never stored. */
    public function payablePureWeight(): ?float
    {
        $pure = $this->pureWeight();

        return $pure !== null && $this->preset_deduction_percent !== null ? round($pure * (1 - (float) $this->preset_deduction_percent / 100), 3) : null;
    }

    /**
     * A guide for the valuation, not the value: the weight after the deduction at today's rate for the nearest carat.
     * Null when there is no rate for that carat. The rupee amount is still typed at settlement.
     */
    public function indicativeValue(): ?float
    {
        if ($this->deductable_weight === null || $this->purity_averaged === null) {
            return null;
        }
        $carat = \App\Models\Movement\RawMetalEntry::caratFor($this->metal, (float) $this->purity_averaged);
        $rate = $carat ? \App\Models\Movement\RateLog::latestFor($this->metal, $carat)?->rate : null;

        return $rate ? round((float) $this->deductable_weight * (float) $rate, 2) : null;
    }

    public function settler()
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
