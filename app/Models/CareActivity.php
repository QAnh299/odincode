<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CareActivity extends Model
{
    use HasFactory, HasStringId;

    // "Liên hệ ban đầu": liên hệ ở stage Data chưa tương tác
    const INITIAL = 'ACT001';

    // "Xếp lại lịch": liên hệ ở stage Data đã có lịch hẹn khi khách không đến
    const RESCHEDULE = 'ACT002';

    // "Chốt đăng ký": liên hệ ở stage Data đã xử lý
    const DECISION = 'ACT003';

    protected $table = 'CareActivities';

    protected $primaryKey = 'activity_id';

    protected string $idPrefix = 'ACT';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'activity_id',
        'activity_name',
        'notes',
    ];

    public function careResults()
    {
        return $this->hasMany(CareResult::class, 'activity_id', 'activity_id');
    }
}
