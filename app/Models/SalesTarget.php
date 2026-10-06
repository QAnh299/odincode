<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'SalesTargets';

    protected $primaryKey = 'target_id';

    protected string $idPrefix = 'TGT';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'target_id',
        'target_name',
        'target_type',
        'unit',
    ];

    public function teamTargets()
    {
        return $this->hasMany(TeamSalesTarget::class, 'target_id', 'target_id');
    }
}
