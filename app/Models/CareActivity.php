<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CareActivity extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'CareActivities';

    protected $primaryKey = 'activity_id';

    protected string $idPrefix = 'ACT';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'activity_id',
        'activity_name',
        'notes',
    ];

    public function careResults()
    {
        return $this->hasMany(CareResult::class, 'activity_id', 'activity_id');
    }
}
