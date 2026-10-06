<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'Students';

    protected $primaryKey = 'student_id';

    protected string $idPrefix = 'STD';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'full_name',
        'phone',
        'email',
        'date_of_birth',
        'conversion_date',
        'status',
        'opportunity_id',
    ];

    protected $casts = [
        'date_of_birth'   => 'date',
        'conversion_date' => 'datetime',
    ];

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id', 'opportunity_id');
    }
}
