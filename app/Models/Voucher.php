<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'Vouchers';

    protected $primaryKey = 'voucher_id';

    protected string $idPrefix = 'VCH';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'voucher_id',
        'discount_type',
        'value',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'value'      => 'decimal:2',
    ];

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'voucher_id', 'voucher_id');
    }

    public function isValid(): bool
    {
        return $this->status === 'Active'
            && today()->between($this->start_date, $this->end_date);
    }
}
