<?php

namespace App\Models;

use App\Models\Concerns\HasCompositeKey;
use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Lịch hẹn. Một lịch hẹn (cùng mã) có thể gồm cả test và tư vấn: mỗi loại một dòng,
 * khoá chính ghép (appointment_id, appointment_type). Địa điểm là cơ sở của khách (Leads.branch_id).
 */
class Appointment extends Model
{
    use HasCompositeKey, HasFactory, HasStringId;

    // Loại lịch hẹn
    const TYPE_TEST = 'Test';
    const TYPE_CONSULTATION = 'Consultation';
    const TYPES = [self::TYPE_TEST, self::TYPE_CONSULTATION];

    // Trạng thái: Success = khách đã đến, Failed = khách không đến
    const STATUS_SCHEDULED = 'Scheduled';
    const STATUS_SUCCESS = 'Success';
    const STATUS_FAILED = 'Failed';

    protected $table = 'Appointments';

    // Khoá chính ghép (appointment_id, appointment_type)
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

    /**
     * Gom các dòng lịch hẹn (đã sắp theo thời gian tăng dần) theo mã để hiển thị: mới nhất lên đầu,
     * "lần N" đánh theo thời gian. Mỗi nhóm: id, no, rows (Test trước, Tư vấn sau).
     */
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
