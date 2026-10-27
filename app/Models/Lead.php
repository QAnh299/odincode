<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory, HasStringId;

    // Trạng thái: New = Sale Admin vừa nhập, Converted = đã phân cho salesperson (đã có Opportunity)
    const STATUS_NEW = 'New';
    const STATUS_CONVERTED = 'Converted';
    const STATUSES = [self::STATUS_NEW, self::STATUS_CONVERTED];

    const CONTACT_METHODS = ['Zalo', 'Phone', 'Facebook'];

    // Nguồn Lead (theo ràng buộc CHECK source_name trong odin.sql)
    const SOURCES = ['Facebook', 'Workshop', 'Website ODIN', 'Zalo', 'Instagram', 'TikTok'];

    // Thông tin bắt buộc theo kênh liên hệ (Salesperson cần biết liên hệ Lead qua đâu):
    // Điện thoại, Zalo → bắt buộc SĐT; Facebook → bắt buộc Link nguồn
    const METHODS_PHONE_REQUIRED = ['Phone', 'Zalo'];
    const METHODS_LINK_REQUIRED = ['Facebook'];

    protected $table = 'Leads';

    protected $primaryKey = 'lead_id';

    protected string $idPrefix = 'LEAD';

    protected int $idLength = 3;

    // Bảng Leads chỉ có created_at
    const UPDATED_AT = null;

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
