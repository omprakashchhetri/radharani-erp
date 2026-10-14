<?php
namespace App\Models\Pricing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** The GST rates a bill starts with. Single row; staff can still pick another rate (or 0) on a bill. */
class TaxSetting extends Model
{
    use LogsActivity;

    /** Rates offered on a bill. */
    public const CHOICES = [0, 3, 5, 12, 18];

    protected $fillable = ['jewellery_percent', 'hallmark_percent', 'updated_by'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['jewellery_percent', 'hallmark_percent'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('pricing');
    }

    public static function current(): self
    {
        return static::first() ?? static::create(['jewellery_percent' => 3, 'hallmark_percent' => 18]);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
