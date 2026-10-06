<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    use HasFactory, HasStringId;

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
