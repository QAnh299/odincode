<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'Courses';

    protected $primaryKey = 'course_id';

    protected string $idPrefix = 'CRS';

    protected int $idLength = 3;

    // Bảng Courses chỉ có created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'course_id',
        'course_name',
        'description',
        'unit_price',
        'VAT',
        'duration',
        'status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'VAT'        => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function quotationDetails()
    {
        return $this->hasMany(QuotationDetail::class, 'course_id', 'course_id');
    }
}
