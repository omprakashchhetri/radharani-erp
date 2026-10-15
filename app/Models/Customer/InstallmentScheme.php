<?php
namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class InstallmentScheme extends Model
{
    protected $fillable = [
        'customer_id', 'monthly_amount', 'late_fee', 'total_months', 'months_paid', 'opening_pending_amount', 'start_date', 'status',
        'maturity_outcome', 'outcome_ref', 'outcome_at',
    ];

    protected $casts = ['start_date' => 'date', 'outcome_at' => 'datetime'];

    public const OUTCOMES = ['order' => 'Create an order', 'sale' => 'Make a sale', 'reserve' => 'Reserve a product in stock'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(InstallmentPayment::class, 'scheme_id');
    }

    public function getMonthsPendingAttribute(): int
    {
        return max(0, $this->total_months - $this->months_paid);
    }

    /** The start date plus the total number of months. */
    public function getCompletionDateAttribute()
    {
        return $this->start_date?->copy()->addMonths($this->total_months);
    }

    /** Still to pay: what was pending when they joined, less what has been paid since. */
    public function getAmountPendingAttribute(): float
    {
        $opening = $this->opening_pending_amount ?? ($this->total_months * (float) $this->monthly_amount);

        return max(0.0, round((float) $opening - (float) ($this->payments_sum_amount ?? $this->payments()->sum('amount')), 2));
    }

    /** When instalment number $index (0 is the first) falls due: the start date plus that many months. */
    public function dueDateFor(int $index): \Illuminate\Support\Carbon
    {
        return $this->start_date->copy()->addMonthsNoOverflow($index);
    }

    /** The next instalment's due date, or null once every month is paid. */
    public function getNextDueOnAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->months_paid < $this->total_months ? $this->dueDateFor($this->months_paid) : null;
    }

    /** How many instalments are past their due date and unpaid (due today is not late yet). */
    public function getMonthsLateAttribute(): int
    {
        $late = 0;
        for ($k = $this->months_paid; $k < $this->total_months; $k++) {
            if ($this->dueDateFor($k)->lt(today())) {
                $late++;
            } else {
                break;
            }
        }

        return $late;
    }

    public function getIsLateAttribute(): bool
    {
        return $this->months_late > 0;
    }

    /** Days since the oldest unpaid instalment fell due; 0 when nothing is late. */
    public function getDaysLateAttribute(): int
    {
        return $this->is_late ? (int) $this->dueDateFor($this->months_paid)->diffInDays(today()) : 0;
    }

    public function getIsMaturedAttribute(): bool
    {
        return $this->months_paid >= $this->total_months;
    }

    /** What the member has paid into the scheme in all, for the outcome. */
    public function getPaidInAttribute(): float
    {
        return round($this->months_paid * (float) $this->monthly_amount, 2);
    }
}
