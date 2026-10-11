<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    use HasFactory, HasStringId;

    // Stage "Data chưa tương tác": vừa được phân chia
    const UNTOUCHED = 'STG001';

    // Stage "Data đã có lịch hẹn": tạo lịch hẹn đầu tiên thì chuyển sang stage này
    const APPOINTED = 'STG002';

    // Stage "Data đã xử lý": đã test và/hoặc tư vấn xong (mọi loại lịch hẹn đều có lần khách đến)
    const PROCESSED = 'STG003';

    // Stage "Chốt": chỉ Opportunity ở stage này mới được tạo báo giá
    const CLOSED = 'STG004';

    protected $table = 'Stages';

    protected $primaryKey = 'stage_id';

    protected string $idPrefix = 'STG';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'stage_id',
        'stage_name',
        'sort_order',
        'description',
    ];

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'stage_id', 'stage_id');
    }
}
