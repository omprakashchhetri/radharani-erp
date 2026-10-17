<?php
namespace App\Models\Movement;

use Illuminate\Database\Eloquent\Model;

/** One line of what was sent to a karigar in a batch (17 Oct): the item described, how many, how heavy. */
class KarigarBatchLine extends Model
{
    protected $fillable = ['karigar_raw_batch_id', 'description', 'pieces', 'weight'];

    public function batch()
    {
        return $this->belongsTo(KarigarRawBatch::class, 'karigar_raw_batch_id');
    }
}
