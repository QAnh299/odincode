<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'Invoices';

    protected $primaryKey = 'invoice_id';

    protected string $idPrefix = 'INV';

    protected int $idLength = 3;

    public $timestamps = false;

    // Hoá đơn đã thanh toán (tính vào số tiền đã thu)
    const STATUS_PAID = 'Paid';

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
}
