<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory, HasStringId;

    // Trạng thái hóa đơn (CHECK trong odin.sql: Unpaid, Paid, Overdue). Unpaid hiển thị "Chờ thanh toán"
    const STATUS_UNPAID = 'Unpaid';
    const STATUS_PAID = 'Paid';
    const STATUS_OVERDUE = 'Overdue';

    const STATUSES = [self::STATUS_UNPAID, self::STATUS_PAID, self::STATUS_OVERDUE];

    // Phương thức thanh toán (CHECK trong odin.sql: BankTransfer, Cash; cột cho phép NULL)
    const METHOD_BANK_TRANSFER = 'BankTransfer';
    const METHOD_CASH = 'Cash';

    const METHODS = [self::METHOD_BANK_TRANSFER, self::METHOD_CASH];

    protected $table = 'Invoices';

    protected $primaryKey = 'invoice_id';

    protected string $idPrefix = 'INV';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'issued_date',
        'due_date',
        'payment_amount',
        'status',
        'payment_method',
        'payment_date',
        'order_id',
        'payment_installment',
        'created_by_employee_id',
        'accountant_id',
    ];

    protected $casts = [
        'issued_date'    => 'date',
        'due_date'       => 'date',
        'payment_date'   => 'datetime',
        'payment_amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id', 'order_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Employee::class, 'created_by_employee_id', 'employee_id');
    }

    public function accountant()
    {
        return $this->belongsTo(Employee::class, 'accountant_id', 'employee_id');
    }

    /**
     * Hóa đơn mà nhân viên được phép xem: theo báo giá của đơn hàng (Quotation::visibleTo).
     * Giám đốc / Sale Admin / Kế toán xem tất cả, Sale Leader xem của đội mình, Salesperson xem của chính mình.
     */
    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        return $query->whereHas('order.quotation', fn ($q) => $q->visibleTo($employee));
    }

    /**
     * Số ngày tới hạn thanh toán (hôm nay = 0, đã qua thì âm). Chỉ là nhãn tính ra, không đổi dữ liệu.
     */
    public function daysUntilDue(): int
    {
        return (int) today()->diffInDays($this->due_date, false);
    }

    /**
     * Hóa đơn chưa thanh toán đã trễ hạn: trạng thái Overdue, hoặc Unpaid mà hạn đã qua
     * (nhãn tính ra, không đổi dữ liệu; chưa có bước nào chuyển Unpaid → Overdue).
     */
    public function isLate(): bool
    {
        return $this->status !== self::STATUS_PAID
            && ($this->status === self::STATUS_OVERDUE || $this->daysUntilDue() < 0);
    }
}
