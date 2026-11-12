<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    use HasStringId;

    // Giá trị role_name trong bảng Roles
    public const DIRECTOR = 'director';       // Giám đốc
    public const SALE_ADMIN = 'sale_admin';   // Sale Admin
    public const SALE_LEADER = 'sale_leader'; // Sale Leader
    public const SALESPERSON = 'salesperson'; // Salesperson
    public const ACCOUNTANT = 'accountant';   // Kế toán

    protected $table = 'Roles';

    protected $primaryKey = 'role_id';

    protected string $idPrefix = 'ROLE';

    protected int $idLength = 2;

    public $timestamps = false;

    protected $fillable = [
        'role_id',
        'role_name',
        'description',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'role_id', 'role_id');
    }
}
