<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    use HasFactory;
    use HasStringId;

    public const STATUS_IN_PROGRESS = 'InProgress';
    public const STATUS_COMPLETED = 'Completed';

    public const STATUSES = [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED];

    public const PAYMENT_FULL = 'Full';
    public const PAYMENT_INSTALLMENT = 'Installment';

    public const PAYMENT_TYPES = [self::PAYMENT_FULL, self::PAYMENT_INSTALLMENT];

    protected $table = 'SalesOrders';

    protected $primaryKey = 'order_id';

    protected string $idPrefix = 'ORD';

    protected int $idLength = 3;

    public const UPDATED_AT = null;

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

    protected static function booted(): void
    {
        static::saved(function (self $order) {
            if ((float) $order->paid_amount > 0 && ($order->wasRecentlyCreated || $order->wasChanged('paid_amount'))) {
                Student::enrollFromOrder($order);
            }
        });
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id', 'quotation_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'order_id', 'order_id');
    }

    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        return $query->whereHas('quotation', fn ($q) => $q->visibleTo($employee));
    }

    public function progressPercent(): int
    {
        $total = (float) $this->total_amount;

        return $total > 0 ? (int) max(0, min(100, round((float) $this->paid_amount / $total * 100))) : 0;
    }
}
