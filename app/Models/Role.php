<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    use HasStringId;

    public const DIRECTOR = 'director';
    public const SALE_ADMIN = 'sale_admin';
    public const SALE_LEADER = 'sale_leader';
    public const SALESPERSON = 'salesperson';
    public const ACCOUNTANT = 'accountant';

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
