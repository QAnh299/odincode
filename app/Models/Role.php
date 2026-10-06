<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory, HasStringId;

    // Giá trị role_name trong bảng Roles
    const DIRECTOR = 'director';       // Giám đốc
    const SALE_ADMIN = 'sale_admin';   // Sale Admin
    const SALE_LEADER = 'sale_leader'; // Sale Leader
    const SALESPERSON = 'salesperson'; // Salesperson
    const ACCOUNTANT = 'accountant';   // Kế toán

    // Trang home (route name) của từng vai trò sau khi đăng nhập
    const HOME_ROUTES = [
        self::DIRECTOR    => 'director.home',
        self::SALE_ADMIN  => 'sale_admin.home',
        self::SALE_LEADER => 'sale_leader.home',
        self::SALESPERSON => 'salesperson.home',
        self::ACCOUNTANT  => 'accountant.home',
    ];

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
