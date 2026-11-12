<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Lead extends Model
{
    use HasFactory;
    use HasStringId;

    // Phiên bản dữ liệu của thẻ thống kê danh sách Lead (Leads\Index::stats): đổi phiên bản = bỏ mọi kết quả đã cache.
    // Đổi khi Lead / cơ hội / kết quả chăm sóc thay đổi; ghi hàng loạt bằng insert() thì gọi touchStats() thủ công.
    public const STATS_VERSION_KEY = 'leads.stats.version';

    public static function touchStats(): void
    {
        Cache::forever(self::STATS_VERSION_KEY, Str::random(8));
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::touchStats());
        static::deleted(fn () => self::touchStats());
    }

    // Trạng thái: New = Sale Admin vừa nhập, Converted = đã phân cho salesperson (đã có Opportunity)
    public const STATUS_NEW = 'New';
    public const STATUS_CONVERTED = 'Converted';
    public const STATUSES = [self::STATUS_NEW, self::STATUS_CONVERTED];

    public const CONTACT_METHODS = ['Zalo', 'Phone', 'Facebook'];

    // Nguồn Lead (theo ràng buộc CHECK source_name trong odin.sql)
    public const SOURCES = ['Facebook', 'Workshop', 'Website ODIN', 'Zalo', 'Instagram', 'TikTok'];

    // Thông tin bắt buộc theo kênh liên hệ (Salesperson cần biết liên hệ Lead qua đâu):
    // Điện thoại, Zalo → bắt buộc SĐT; Facebook → bắt buộc Link nguồn
    public const METHODS_PHONE_REQUIRED = ['Phone', 'Zalo'];
    public const METHODS_LINK_REQUIRED = ['Facebook'];

    protected $table = 'Leads';

    protected $primaryKey = 'lead_id';

    protected string $idPrefix = 'LEAD';

    protected int $idLength = 3;

    // Bảng Leads chỉ có created_at
    public const UPDATED_AT = null;

    protected $fillable = [
        'lead_id',
        'full_name',
        'phone',
        'email',
        'source_name',
        'source_url',
        'contact_method',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Lead được xem theo vai trò (danh sách và chi tiết Lead):
     *   - Giám đốc, Sale Admin: tất cả chi nhánh;
     *   - Sale Leader: Lead thuộc chi nhánh của mình mà Sale Admin đã phân cho đội (trưởng nhóm đang giữ
     *     hoặc đã phân cho thành viên);
     *   - vai trò khác: không xem.
     */
    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        if (in_array($employee?->role_name, [Role::DIRECTOR, Role::SALE_ADMIN], true)) {
            return $query;
        }

        $team = $employee?->role_name === Role::SALE_LEADER ? ($employee->ledTeam ?? $employee->team) : null;

        return $team
            ? $query->where('branch_id', $employee->branch_id)
                ->whereHas('opportunities', fn ($q) => $q->where(fn ($q) => $q
                    ->where('employee_id', $team->team_leader_id)
                    ->orWhereHas('employee', fn ($e) => $e->where('team_id', $team->team_id))))
            : $query->whereRaw('1 = 0');
    }

    /**
     * Lead chưa phân cho đội: "Mới" và không có cơ hội đang xử lý (cùng điều kiện với
     * LeadDistributionService::availableLeads()). Lead đã giao cho trưởng nhóm vẫn ở trạng thái "Mới" nên phải
     * loại theo cơ hội; khách cũ được đưa về hàng chờ (LeadDuplicateService::reopen) còn các cơ hội đã kết thúc.
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW)
            ->whereDoesntHave('opportunities', fn ($q) => $q->where('status', Opportunity::STATUS_IN_PROGRESS));
    }

    /**
     * Khách cũ quay lại: Lead đang chờ phân cho đội nhưng đã từng có cơ hội kết thúc (Won / Lost / Archived).
     */
    public function scopeReturning(Builder $query): Builder
    {
        return $query->unassigned()
            ->whereHas('opportunities', fn ($q) => $q->where('status', '!=', Opportunity::STATUS_IN_PROGRESS));
    }

    /**
     * Khách cũ: đã có cơ hội đã kết thúc (Won / Lost / Archived).
     */
    public function isReturning(): bool
    {
        return $this->opportunities()->where('status', '!=', Opportunity::STATUS_IN_PROGRESS)->exists();
    }

    /**
     * Lead đội đã nhận nhưng chưa phân cho Salesperson: còn cơ hội trưởng nhóm đang giữ ở stage đầu,
     * chưa chăm sóc (cùng điều kiện với LeadDistributionService::pendingOpportunities()).
     */
    public function scopePendingSalesperson(Builder $query): Builder
    {
        return $query->whereHas('opportunities', fn ($q) => $q
            ->where('stage_id', Stage::UNTOUCHED)
            ->where('status', Opportunity::STATUS_IN_PROGRESS)
            ->whereDoesntHave('careResults')
            ->whereHas('employee.ledTeam'));
    }

    /**
     * Thông tin Lead còn được sửa: Lead chưa phân chia, hoặc mọi cơ hội của Lead đều đang chăm sóc.
     * Cơ hội đã chốt hay đã lưu trữ giữ nguyên thông tin khách lúc kết thúc.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_NEW
            || ! $this->opportunities()->where('status', '!=', Opportunity::STATUS_IN_PROGRESS)->exists();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    /**
     * Link nguồn là đường dẫn http(s) → hiển thị thành link.
     */
    public function sourceUrlIsLink(): bool
    {
        return (bool) preg_match('#^https?://#i', (string) $this->source_url);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'lead_id', 'lead_id');
    }

    /**
     * Cơ hội gần nhất của Lead.
     */
    public function latestOpportunity()
    {
        return $this->hasOne(Opportunity::class, 'lead_id', 'lead_id')->latestOfMany('conversion_date');
    }
}
