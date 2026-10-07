<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory, HasStringId;

    // Trạng thái khóa học (cột status): Đang cung cấp hoặc Ngừng cung cấp
    const STATUS_ACTIVE = 'Active';

    // Key trạng thái dùng cho bộ lọc / nhãn hiển thị (lang courses.state.*)
    const STATE_ACTIVE = 'active';     // status = 'Active'
    const STATE_INACTIVE = 'inactive'; // status khác 'Active'

    const STATES = [self::STATE_ACTIVE, self::STATE_INACTIVE];

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

    /**
     * Học phí hiển thị: "6.000.000 ₫".
     */
    public function displayPrice(): string
    {
        return number_format((float) $this->unit_price, 0, ',', '.').' ₫';
    }

    /**
     * VAT hiển thị: "10%", "0%", "8,5%".
     */
    public function displayVat(): string
    {
        return rtrim(rtrim(number_format((float) $this->VAT, 2, ',', '.'), '0'), ',').'%';
    }
}
