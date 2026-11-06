<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory, HasStringId;

    // Trụ sở chính: kế toán trụ sở nhận thay các cơ sở chưa có kế toán đang làm việc (Employee::coveredBranchIds)
    const HEAD_OFFICE = 'BR001';

    protected $table = 'Branches';

    protected $primaryKey = 'branch_id';

    protected string $idPrefix = 'BR';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'branch_name',
        'address',
        'phone',
        'email',
        'status',
        'established_date',
    ];

    protected $casts = [
        'established_date' => 'date',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'branch_id', 'branch_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'branch_id', 'branch_id');
    }
}
