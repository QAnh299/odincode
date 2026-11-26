<?php

namespace App\Models;

use App\Models\Concerns\HasCompositeKey;
use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Appointment extends Model
{
    use HasCompositeKey;
    use HasFactory;
    use HasStringId;

    public const TYPE_TEST = 'Test';
    public const TYPE_CONSULTATION = 'Consultation';
    public const TYPES = [self::TYPE_TEST, self::TYPE_CONSULTATION];

    public const STATUS_SCHEDULED = 'Scheduled';
    public const STATUS_SUCCESS = 'Success';
    public const STATUS_FAILED = 'Failed';
    public const STATUS_CANCELLED = 'Cancelled';

    protected $table = 'Appointments';

    protected $primaryKey = 'appointment_id';

    protected array $compositeKey = ['appointment_id', 'appointment_type'];

    protected string $idPrefix = 'APT';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'appointment_id',
        'appointment_type',
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

    public static function groupForDisplay(Collection $rows): Collection
    {
        return $rows->groupBy('appointment_id')
            ->values()
            ->map(fn (Collection $group, int $i) => [
                'id'   => $group->first()->appointment_id,
                'no'   => $i + 1,
                'rows' => $group->sortBy(fn ($r) => array_search($r->appointment_type, self::TYPES))->values(),
            ])
            ->reverse()
            ->values();
    }
}
