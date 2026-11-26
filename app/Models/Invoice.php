<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    use HasStringId;

    public const STATUS_UNPAID = 'Unpaid';
    public const STATUS_PAID = 'Paid';
    public const STATUS_OVERDUE = 'Overdue';

    public const STATUSES = [self::STATUS_UNPAID, self::STATUS_PAID, self::STATUS_OVERDUE];

    public const METHOD_BANK_TRANSFER = 'BankTransfer';
    public const METHOD_CASH = 'Cash';

    public const METHODS = [self::METHOD_BANK_TRANSFER, self::METHOD_CASH];

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

    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        return $query->whereHas('order.quotation', fn ($q) => $q->visibleTo($employee));
    }

    public function daysUntilDue(): int
    {
        return (int) today()->diffInDays($this->due_date, false);
    }

    public function qrUrl(): string
    {
        $config = config('services.vietqr');

        return sprintf('https://img.vietqr.io/image/%s-%s-%s.png', $config['bank_id'], $config['account_no'], $config['template'])
            .'?'.http_build_query([
                'amount'      => (int) round((float) $this->payment_amount),
                'addInfo'     => $this->invoice_id,
                'accountName' => $config['account_name'],
            ]);
    }

    public function isLate(): bool
    {
        return $this->status !== self::STATUS_PAID
            && ($this->status === self::STATUS_OVERDUE || $this->daysUntilDue() < 0);
    }
}
