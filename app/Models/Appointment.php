<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'Appointments';

    protected $primaryKey = 'appointment_id';

    protected string $idPrefix = 'APT';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'appointment_id',
        'appointment_type',
        'location',
        'scheduled_time',
        'status',
        'notes',
        'employee_id',
        'opportunity_id',
    ];

    protected $casts = [
        'scheduled_time' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id', 'opportunity_id');
    }
}
