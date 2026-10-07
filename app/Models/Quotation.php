<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'Quotations';

    protected $primaryKey = 'quotation_id';

    protected string $idPrefix = 'QUO';

    protected int $idLength = 3;

    protected $fillable = [
        'quotation_id',
        'expiry_date',
        'status',
        'notes',
        'employee_id',
        'voucher_id',
        'opportunity_id',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id', 'voucher_id');
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id', 'opportunity_id');
    }

    public function details()
    {
        return $this->hasMany(QuotationDetail::class, 'quotation_id', 'quotation_id');
    }

    /**
     * Báo giá mà nhân viên được phép xem:
     * Giám đốc / Sale Admin xem tất cả, Sale Leader xem của nhóm mình, Salesperson xem của chính mình.
     */
    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        return match ($employee?->role_name) {
            Role::DIRECTOR, Role::SALE_ADMIN => $query,
            Role::SALE_LEADER => $query->whereHas('employee', fn ($q) => $q->where('team_id', $employee->team_id)),
            Role::SALESPERSON => $query->where('employee_id', $employee->employee_id),
            default           => $query->whereRaw('1 = 0'),
        };
    }

    public function order()
    {
        return $this->hasOne(SalesOrder::class, 'quotation_id', 'quotation_id');
    }
}
