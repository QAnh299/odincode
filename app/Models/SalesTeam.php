<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesTeam extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'SalesTeams';

    protected $primaryKey = 'team_id';

    protected string $idPrefix = 'TEAM';

    protected int $idLength = 2;

    public $timestamps = false;

    protected $fillable = [
        'team_id',
        'team_name',
        'established_date',
        'team_leader_id',
    ];

    protected $casts = [
        'established_date' => 'date',
    ];

    public function leader()
    {
        return $this->belongsTo(Employee::class, 'team_leader_id', 'employee_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'team_id', 'team_id');
    }

    /**
     * Chi nhánh của đội. Bảng SalesTeams không có cột branch_id (odin.sql) nên suy ra từ nhân viên:
     * ưu tiên trưởng nhóm, không có thì lấy theo thành viên đang làm việc; đội chưa có ai → null.
     * Quy ước: mọi nhân viên trong một đội phải cùng một chi nhánh.
     *
     * @param  array  $exceptIds  bỏ qua các nhân viên này (VD: trưởng nhóm sắp bị thay)
     */
    public function branchId(array $exceptIds = []): ?string
    {
        if ($this->leader && ! in_array($this->leader->employee_id, $exceptIds, true)) {
            return $this->leader->branch_id;
        }

        return $this->employees()
            ->where('status', Employee::STATUS_WORKING)
            ->whereNotIn('employee_id', $exceptIds)
            ->value('branch_id');
    }

    public function salesTargets()
    {
        return $this->hasMany(TeamSalesTarget::class, 'team_id', 'team_id');
    }
}
