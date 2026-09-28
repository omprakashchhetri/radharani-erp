<?php
namespace App\Models\Customer;

use App\Models\Purchase\Vendor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerMaterialJob extends Model
{
    protected $fillable = [
        'customer_id', 'vendor_id', 'description', 'weight_out', 'metal',
        'expected_return', 'actual_return', 'weight_in', 'weight_loss',
        'status', 'note', 'user_id', 'returned_by',
    ];

    protected $casts = ['expected_return' => 'date', 'actual_return' => 'date'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
