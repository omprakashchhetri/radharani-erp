<?php
namespace App\Models\Movement;

use App\Models\Purchase\Vendor;
use App\Models\Stock\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class KarigarRawBatch extends Model
{
    protected $fillable = [
        'vendor_id', 'weight_out', 'metal', 'purity', 'purpose_label',
        'expected_return', 'actual_return', 'weight_returned', 'weight_loss',
        'status', 'note', 'user_id', 'returned_by',
    ];

    protected $casts = ['expected_return' => 'date', 'actual_return' => 'date'];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Finished pieces created when this batch was returned.
    public function items()
    {
        return $this->hasMany(Item::class, 'source_karigar_batch_id');
    }
}
