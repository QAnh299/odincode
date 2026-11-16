<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Học viên: người đã thanh toán ít nhất một lần - 1 Lead = 1 Student xuyên suốt mọi lần đăng ký.
 * opportunity_id ghi lại cơ hội tạo ra học viên này (dùng cho báo cáo tính theo salesperson).
 */
class Student extends Model
{
    use HasFactory;
    use HasStringId;

    // Trạng thái (CHECK trong odin.sql: Studying, Dropped, Completed)
    public const STATUS_STUDYING = 'Studying';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_DROPPED = 'Dropped';
    public const STATUSES = [self::STATUS_STUDYING, self::STATUS_COMPLETED, self::STATUS_DROPPED];

    protected $table = 'Students';

    protected $primaryKey = 'student_id';

    protected string $idPrefix = 'STD';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'full_name',
        'phone',
        'email',
        'date_of_birth',
        'conversion_date',
        'status',
        'lead_id',
        'opportunity_id',
    ];

    protected $casts = [
        'date_of_birth'   => 'date',
        'conversion_date' => 'datetime',
    ];

    /**
     * Lead (người) của học viên.
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'lead_id');
    }

    /**
     * Cơ hội đã tạo ra học viên này (lần đăng ký đầu tiên).
     */
    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id', 'opportunity_id');
    }

    /**
     * Học viên mà nhân viên được phép xem: theo bất kỳ Opportunity nào cùng Lead.
     * Giám đốc / Sale Admin xem tất cả, Kế toán xem khách thuộc cơ sở mình phụ trách,
     * Sale Leader xem của đội mình, Salesperson xem của chính mình.
     */
    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        return $query->whereHas('lead', fn ($q) =>
            $q->whereHas('opportunities', fn ($q2) => $q2->visibleTo($employee))
        );
    }

    /**
     * Đơn hàng đã đóng tiền của bất kỳ cơ hội nào cùng Lead.
     */
    public function paidOrder(): ?SalesOrder
    {
        return SalesOrder::query()
            ->where('paid_amount', '>', 0)
            ->whereHas('quotation.opportunity', fn ($q) => $q->where('lead_id', $this->lead_id))
            ->latest('created_at')
            ->first();
    }

    /**
     * Tạo / cập nhật học viên khi thanh toán thành công (gọi từ SalesOrder khi lưu).
     * - Lead chưa có học viên → tạo mới.
     * - Lead đã có → cập nhật trạng thái về Studying nếu trước đó Completed / Dropped.
     */
    public static function enrollFromOrder(SalesOrder $order): ?self
    {
        $opportunity = $order->quotation?->opportunity;

        if (! $opportunity?->lead_id) {
            return null;
        }

        $lead = Lead::query()->with('student')->find($opportunity->lead_id);

        if (! $lead) {
            return null;
        }

        if ($existing = $lead->student) {
            if (in_array($existing->status, [self::STATUS_COMPLETED, self::STATUS_DROPPED])) {
                $existing->update(['status' => self::STATUS_STUDYING]);
            }

            return $existing;
        }

        return static::create([
            'full_name'       => $lead->full_name,
            'phone'           => $lead->phone,
            'email'           => $lead->email,
            'conversion_date' => now(),
            'status'          => self::STATUS_STUDYING,
            'lead_id'         => $lead->lead_id,
            'opportunity_id'  => $opportunity->opportunity_id,
        ]);
    }
}
