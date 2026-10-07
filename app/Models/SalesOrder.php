<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'SalesOrders';

    protected $primaryKey = 'order_id';

    protected string $idPrefix = 'ORD';

    protected int $idLength = 3;

    // Bảng SalesOrders chỉ có created_at
    const UPDATED_AT = null;

    // Đơn hàng đã huỷ không tính vào doanh thu
    const STATUS_CANCELLED = 'Cancelled';

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
}
