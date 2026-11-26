<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpportunityTransfer extends Model
{
    use HasFactory;
    use HasStringId;

    protected $table = 'OpportunityTransfers';

    protected $primaryKey = 'transfer_id';

    protected string $idPrefix = 'OT';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'transfer_id',
        'transferred_at',
        'reason',
        'opportunity_id',
        'from_employee_id',
        'to_employee_id',
        'transferred_by',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id', 'opportunity_id');
    }

    public function fromEmployee()
    {
        return $this->belongsTo(Employee::class, 'from_employee_id', 'employee_id');
    }

    public function toEmployee()
    {
        return $this->belongsTo(Employee::class, 'to_employee_id', 'employee_id');
    }

    public function transferrer()
    {
        return $this->belongsTo(Employee::class, 'transferred_by', 'employee_id');
    }
}
