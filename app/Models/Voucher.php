<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory, HasStringId;

    // Giá trị discount_type trong bảng Vouchers
    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED = 'fixed';

    // Trạng thái voucher (cột status): Hoạt động hoặc Không hoạt động
    const STATUS_ACTIVE = 'Active';

    // Key trạng thái dùng cho bộ lọc / nhãn hiển thị (lang vouchers.state.*)
    const STATE_ACTIVE = 'active';     // status = 'Active'
    const STATE_INACTIVE = 'inactive'; // status khác 'Active'

    const STATES = [self::STATE_ACTIVE, self::STATE_INACTIVE];

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

    /**
     * Lọc theo trạng thái (một trong self::STATES).
     */
    public function scopeState(Builder $query, string $state): Builder
    {
        return match ($state) {
            self::STATE_ACTIVE   => $query->where('status', self::STATUS_ACTIVE),
            self::STATE_INACTIVE => $query->where('status', '<>', self::STATUS_ACTIVE),
            default              => $query,
        };
    }

    public function state(): string
    {
        return $this->status === self::STATUS_ACTIVE ? self::STATE_ACTIVE : self::STATE_INACTIVE;
    }

    public function isPercentage(): bool
    {
        return $this->discount_type === self::TYPE_PERCENTAGE;
    }

    /**
     * Giá trị giảm hiển thị: "10%" hoặc "1.000.000 ₫".
     */
    public function displayValue(): string
    {
        return $this->isPercentage()
            ? rtrim(rtrim(number_format((float) $this->value, 2, ',', '.'), '0'), ',').'%'
            : number_format((float) $this->value, 0, ',', '.').' ₫';
    }

    /**
     * Số tiền được giảm khi áp dụng cho một khoản tiền (fixed không vượt quá khoản tiền).
     */
    public function discountFor(float $amount): float
    {
        return $this->isPercentage()
            ? round($amount * (float) $this->value / 100, 2)
            : min((float) $this->value, $amount);
    }

    public function isValid(): bool
    {
        return $this->status === 'Active'
            && today()->between($this->start_date, $this->end_date);
    }
}
