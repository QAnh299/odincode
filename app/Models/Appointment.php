<?php

namespace App\Models;

use App\Models\Concerns\HasCompositeKey;
use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lịch hẹn. Một lịch hẹn (cùng mã, thời gian, địa điểm) có thể gồm cả test và tư vấn:
 * mỗi loại một dòng, khoá chính ghép (appointment_id, appointment_type).
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
