<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CareResult extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'CareResults';

    protected $primaryKey = 'result_id';

    protected string $idPrefix = 'CARE';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'result_id',
        'performed_at',
        'result',
        'notes',
        'activity_id',
        'opportunity_id',
        'employee_id',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    public function activity()
    {
        return $this->belongsTo(CareActivity::class, 'activity_id', 'activity_id');
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id', 'opportunity_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
