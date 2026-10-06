<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'Opportunities';

    protected $primaryKey = 'opportunity_id';

    protected string $idPrefix = 'OPP';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'opportunity_id',
        'conversion_date',
        'expected_value',
        'status',
        'lead_id',
        'stage_id',
        'employee_id',
    ];

    protected $casts = [
        'conversion_date' => 'datetime',
        'expected_value'  => 'decimal:2',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'lead_id');
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class, 'stage_id', 'stage_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'opportunity_id', 'opportunity_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'opportunity_id', 'opportunity_id');
    }

    public function careResults()
    {
        return $this->hasMany(CareResult::class, 'opportunity_id', 'opportunity_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'opportunity_id', 'opportunity_id');
    }
}
