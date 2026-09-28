<?php
namespace App\Models\Pricing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MakingChargePreset extends Model
{
    protected $fillable = ['category', 'type', 'value', 'created_by'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
