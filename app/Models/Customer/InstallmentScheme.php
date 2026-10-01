<?php
namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class InstallmentScheme extends Model
{
    protected $fillable = ['customer_id', 'monthly_amount', 'months_paid', 'start_date', 'status'];

    protected $casts = ['start_date' => 'date'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(InstallmentPayment::class, 'scheme_id');
    }
}
