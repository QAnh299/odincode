<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lịch sử chuyển stage của Opportunity - bảng `StagesHistory` trong odin.sql.
 * Mỗi dòng là một lần chuyển từ from_stage_id sang to_stage_id; ghi qua Opportunity::moveToStage().
 */
class StageHistory extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'StagesHistory';

    protected $primaryKey = 'history_id';

    protected string $idPrefix = 'SH';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'history_id',
        'changed_at',
        'notes',
        'opportunity_id',
        'from_stage_id',
        'to_stage_id',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id', 'opportunity_id');
    }

    public function fromStage()
    {
        return $this->belongsTo(Stage::class, 'from_stage_id', 'stage_id');
    }

    public function toStage()
    {
        return $this->belongsTo(Stage::class, 'to_stage_id', 'stage_id');
    }
}
