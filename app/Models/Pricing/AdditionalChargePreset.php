<?php
namespace App\Models\Pricing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AdditionalChargePreset extends Model
{
    protected $fillable = ['name', 'value', 'created_by'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
