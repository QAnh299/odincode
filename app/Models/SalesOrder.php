<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    use HasFactory, HasStringId;

    // Trạng thái đơn hàng (CHECK trong odin.sql: InProgress, Completed). InProgress khi còn nợ, Completed khi hết nợ (HĐ33)
    const STATUS_IN_PROGRESS = 'InProgress';
    const STATUS_COMPLETED = 'Completed';

    const STATUSES = [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED];

    // Hình thức thanh toán (CHECK trong odin.sql: Full, Installment)
    const PAYMENT_FULL = 'Full';
    const PAYMENT_INSTALLMENT = 'Installment';

    const PAYMENT_TYPES = [self::PAYMENT_FULL, self::PAYMENT_INSTALLMENT];

    protected $table = 'SalesOrders';

    protected $primaryKey = 'order_id';

    protected string $idPrefix = 'ORD';

    protected int $idLength = 3;

    // Bảng SalesOrders chỉ có created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'payment_type',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'status',
        'notes',
        'quotation_id',
    ];

    protected $casts = [
        'total_amount'     => 'decimal:2',
        'paid_amount'      => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'created_at'       => 'datetime',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id', 'quotation_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'order_id', 'order_id');
    }

    /**
     * Đơn hàng mà nhân viên được phép xem: theo báo giá của đơn (Quotation::visibleTo).
     * Giám đốc / Sale Admin / Kế toán xem tất cả, Sale Leader xem của đội mình, Salesperson xem của chính mình.
     */
    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        return $query->whereHas('quotation', fn ($q) => $q->visibleTo($employee));
    }

    /**
     * Phần trăm đã thanh toán = đã thanh toán ÷ tổng tiền, làm tròn (tổng = 0 thì 0), giới hạn 0 – 100.
     */
    public function progressPercent(): int
    {
        $total = (float) $this->total_amount;

        return $total > 0 ? (int) max(0, min(100, round((float) $this->paid_amount / $total * 100))) : 0;
    }
}
