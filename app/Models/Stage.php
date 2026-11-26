<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    use HasFactory;
    use HasStringId;

    public const UNTOUCHED = 'STG001';

    public const APPOINTED = 'STG002';

    public const PROCESSED = 'STG003';

    public const CLOSED = 'STG004';

    public const ARCHIVED = 'STG005';

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
