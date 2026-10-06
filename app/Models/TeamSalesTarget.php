<?php

namespace App\Models;

use App\Models\Concerns\HasCompositeKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamSalesTarget extends Model
{
    use HasCompositeKey, HasFactory;

    protected $table = 'TeamSalesTargets';

    // Khoá chính ghép (target_id, team_id, target_month)
    protected $primaryKey = 'target_id';

    protected array $compositeKey = ['target_id', 'team_id', 'target_month'];

    public $incrementing = false;

    protected $keyType = 'string';

    // updated_at do MySQL tự cập nhật (ON UPDATE CURRENT_TIMESTAMP)
    public $timestamps = false;

    protected $fillable = [
        'target_id',
        'team_id',
        'target_month',
        'target_value',
        'status',
    ];

    protected $casts = [
        'target_month' => 'date:Y-m-d',
        'target_value' => 'decimal:2',
        'updated_at'   => 'datetime',
    ];

    public function target()
    {
        return $this->belongsTo(SalesTarget::class, 'target_id', 'target_id');
    }

    public function team()
    {
        return $this->belongsTo(SalesTeam::class, 'team_id', 'team_id');
    }
}
