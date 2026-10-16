<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Học viên: Opportunity đã thanh toán toàn bộ học phí (đơn hàng Hoàn tất).
 * Mỗi Opportunity tối đa một học viên (UNIQUE opportunity_id) - học viên cũ đăng ký khoá mới
 * qua Opportunity mới sẽ là một bản ghi học viên mới.
 */
class Student extends Model
{
    use HasFactory, HasStringId;

    // Trạng thái (CHECK trong odin.sql: Studying, Dropped, Completed)
    const STATUS_STUDYING = 'Studying';
    const STATUS_COMPLETED = 'Completed';
    const STATUS_DROPPED = 'Dropped';
    const STATUSES = [self::STATUS_STUDYING, self::STATUS_COMPLETED, self::STATUS_DROPPED];

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
        'opportunity_id',
    ];

    protected $casts = [
        'date_of_birth'   => 'date',
        'conversion_date' => 'datetime',
    ];

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id', 'opportunity_id');
    }

    /**
     * Học viên mà nhân viên được phép xem: theo Opportunity của học viên (Opportunity::visibleTo).
     * Giám đốc / Sale Admin / Kế toán xem tất cả, Sale Leader xem của đội mình, Salesperson xem của chính mình.
     */
    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        return $query->whereHas('opportunity', fn ($q) => $q->visibleTo($employee));
    }

    /**
     * Đơn hàng đã thanh toán đủ (Hoàn tất) của Opportunity - căn cứ để trở thành học viên.
     */
    public function paidOrder(): ?SalesOrder
    {
        return SalesOrder::query()
            ->where('status', SalesOrder::STATUS_COMPLETED)
            ->whereHas('quotation', fn ($q) => $q->where('opportunity_id', $this->opportunity_id))
            ->latest('created_at')
            ->first();
    }

    /**
     * Tạo học viên khi đơn hàng đã thanh toán đủ (gọi từ SalesOrder khi lưu).
     * Thông tin lấy từ Lead của Opportunity; Opportunity đã có học viên thì bỏ qua.
     */
    public static function enrollFromOrder(SalesOrder $order): ?self
    {
        $opportunity = $order->quotation?->opportunity;

        if (! $opportunity || $opportunity->student()->exists() || ! $opportunity->lead) {
            return null;
        }

        return static::create([
            'full_name'       => $opportunity->lead->full_name,
            'phone'           => $opportunity->lead->phone,
            'email'           => $opportunity->lead->email,
            'conversion_date' => now(),
            'status'          => self::STATUS_STUDYING,
            'opportunity_id'  => $opportunity->opportunity_id,
        ]);
    }
}
