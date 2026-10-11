<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory, HasStringId;

    // Trạng thái báo giá (CHECK trong odin.sql: Draft, Confirmed, Rejected)
    const STATUS_DRAFT = 'Draft';
    const STATUS_CONFIRMED = 'Confirmed';
    const STATUS_REJECTED = 'Rejected';

    const STATUSES = [self::STATUS_DRAFT, self::STATUS_CONFIRMED, self::STATUS_REJECTED];

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
     * Giám đốc / Sale Admin / Kế toán xem tất cả, Sale Leader xem của nhóm mình, Salesperson xem của chính mình.
     */
    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        return match ($employee?->role_name) {
            Role::DIRECTOR, Role::SALE_ADMIN, Role::ACCOUNTANT => $query,
            Role::SALE_LEADER => $query->whereHas('employee', fn ($q) => $q->where('team_id', $employee->team_id)),
            Role::SALESPERSON => $query->where('employee_id', $employee->employee_id),
            default           => $query->whereRaw('1 = 0'),
        };
    }

    public function order()
    {
        return $this->hasOne(SalesOrder::class, 'quotation_id', 'quotation_id');
    }

    /**
     * Tạm tính: tổng line_total các dòng chi tiết (đã gồm VAT, trước voucher).
     * Dùng giá trị withSum('details as details_subtotal', 'line_total') nếu đã nạp sẵn.
     */
    public function subtotal(): float
    {
        return array_key_exists('details_subtotal', $this->attributes)
            ? (float) $this->details_subtotal
            : (float) $this->details()->sum('line_total');
    }

    /**
     * Tiền giảm của voucher trên tạm tính (không có voucher thì 0).
     */
    public function discountAmount(): float
    {
        return $this->voucher?->discountFor($this->subtotal()) ?? 0.0;
    }

    /**
     * Tổng tiền báo giá = tạm tính − giảm giá (không lưu trong CSDL, luôn tính ra).
     */
    public function totalAmount(): float
    {
        return max(0, $this->subtotal() - $this->discountAmount());
    }

    /**
     * Biểu thức SQL tính tổng tiền báo giá, cùng công thức với totalAmount():
     * tạm tính = SUM(line_total); voucher % = ROUND(tạm tính × giá trị / 100, 2);
     * voucher cố định = MIN(giá trị, tạm tính); tổng = MAX(0, tạm tính − giảm giá).
     * Chỉ chứa hằng số của hệ thống (không có dữ liệu người dùng) nên dùng được trong whereRaw/orderByRaw.
     */
    public static function totalSql(): string
    {
        $subtotal = '(SELECT COALESCE(SUM(qd.line_total), 0) FROM QuotationDetails AS qd'
            .' WHERE qd.quotation_id = Quotations.quotation_id)';

        $discount = "(SELECT CASE v.discount_type"
            ." WHEN '".Voucher::TYPE_PERCENTAGE."' THEN ROUND($subtotal * v.value / 100, 2)"
            ." WHEN '".Voucher::TYPE_FIXED."' THEN LEAST(v.value, $subtotal)"
            .' ELSE 0 END FROM Vouchers AS v WHERE v.voucher_id = Quotations.voucher_id)';

        return "GREATEST(0, $subtotal - COALESCE($discount, 0))";
    }

    /**
     * Thêm cột computed_total (tổng tiền tính bằng SQL) để lọc, sắp xếp và hiển thị dùng chung một công thức.
     */
    public function scopeWithTotal(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($query->qualifyColumn('*'));
        }

        return $query->selectRaw(self::totalSql().' AS computed_total');
    }

    /**
     * Số ngày còn lại tới ngày hết hạn (hôm nay = 0, đã qua thì âm). Chỉ có ý nghĩa với báo giá Nháp.
     */
    public function daysUntilExpiry(): int
    {
        return (int) today()->diffInDays($this->expiry_date, false);
    }

    /**
     * Báo giá Nháp đã quá ngày hết hạn (chỉ là nhãn tính ra, không đổi dữ liệu; tự Từ chối làm ở HĐ15).
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->daysUntilExpiry() < 0;
    }
}
