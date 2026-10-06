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

    public function salesTargets()
    {
        return $this->hasMany(TeamSalesTarget::class, 'team_id', 'team_id');
    }
}
