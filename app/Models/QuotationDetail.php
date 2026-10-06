<?php

namespace App\Models;

use App\Models\Concerns\HasCompositeKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationDetail extends Model
{
    use HasCompositeKey, HasFactory;

    protected $table = 'QuotationDetails';

    // Khoá chính ghép (quotation_id, course_id)
    protected $primaryKey = 'quotation_id';

    protected array $compositeKey = ['quotation_id', 'course_id'];

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'quotation_id',
        'course_id',
        'unit_price',
        'quantity',
        'VAT',
        'line_total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'VAT'        => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id', 'quotation_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }
}
