<?php

namespace App\Livewire\Leads;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Danh sách Lead + bộ lọc + thống kê theo bộ lọc.
 * Dùng chung cho Giám đốc, Sale Admin, Sale Leader.
 *
 * Quyền theo vai trò:
 *   - Giám đốc: chỉ xem, tất cả chi nhánh (lọc được theo chi nhánh).
 *   - Sale Admin: tất cả chi nhánh; có nút Thêm, Import Excel, Phân chia Lead cho Sale Team.
 *   - Sale Leader: chỉ Lead thuộc chi nhánh của mình; có nút Thêm, Import Excel,
 *     Phân chia Lead cho Salesperson.
 * Các nút thao tác hiện chỉ hiển thị, chưa có chức năng.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    const PER_PAGE = 10;

    // Các bộ lọc có thể bị sửa từ client → chỉ áp dụng giá trị nằm trong danh sách hợp lệ

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $method = '';

    // Chi nhánh (Giám đốc, Sale Admin) – '' = Tất cả
    #[Url(except: '')]
    public string $branch = '';

    // Khoảng ngày tạo (Y-m-d)
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    const FILTERS = ['search', 'status', 'source', 'method', 'branch', 'from', 'to'];

    public function updated(string $property): void
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }
    }

    /**
     * Bấm vào thẻ thống kê: lọc theo trạng thái, bấm lại thì bỏ lọc.
     */
    public function filterStatus(string $status): void
    {
        $this->status = $this->status === $status ? '' : $status;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(...self::FILTERS);
        $this->resetPage();
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Có các nút Thêm / Import Excel / Phân chia (Sale Admin, Sale Leader).
     */
    #[Computed]
    public function canManage(): bool
    {
        return in_array($this->routePrefix, [Role::SALE_ADMIN, Role::SALE_LEADER], true);
    }

    #[Computed]
    public function canFilterBranch(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

    /**
     * Chi nhánh cố định của Sale Leader (null với vai trò khác).
     */
    #[Computed]
    public function fixedBranch(): ?Branch
    {
        if ($this->routePrefix !== Role::SALE_LEADER) {
            return null;
        }

        return Branch::find(Auth::user()->employee?->branch_id);
    }

    #[Computed]
    public function branches(): Collection
    {
        return $this->canFilterBranch
            ? Branch::query()->orderBy('branch_id')->get(['branch_id', 'branch_name'])
            : collect();
    }

    /**
     * Nguồn Lead có trong phạm vi được xem (cho bộ lọc Nguồn).
     */
    #[Computed]
    public function sources(): array
    {
        return $this->scoped(Lead::query())
            ->distinct()
            ->orderBy('source_name')
            ->pluck('source_name')
            ->all();
    }

    /**
     * Thống kê theo bộ lọc hiện tại (trừ bộ lọc trạng thái, để các thẻ trạng thái luôn có số).
     */
    #[Computed]
    public function stats(): array
    {
        $row = $this->filtered(Lead::query(), withStatus: false)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(status = ?) AS new_count', [Lead::STATUS_NEW])
            ->selectRaw('SUM(status = ?) AS converted_count', [Lead::STATUS_CONVERTED])
            ->first();

        $total = (int) $row->total;
        $converted = (int) $row->converted_count;

        return [
            'all'                   => $total,
            Lead::STATUS_NEW        => (int) $row->new_count,
            Lead::STATUS_CONVERTED  => $converted,
            'rate'                  => $total ? round($converted * 100 / $total, 1) : 0,
        ];
    }

    public function hasFilters(): bool
    {
        foreach (self::FILTERS as $filter) {
            if ($this->$filter !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Phạm vi dữ liệu theo vai trò.
     */
    protected function scoped(Builder $query): Builder
    {
        if ($this->routePrefix === Role::SALE_LEADER) {
            $query->where('branch_id', $this->fixedBranch?->branch_id);
        }

        return $query;
    }

    /**
     * Phạm vi vai trò + các bộ lọc trên màn hình.
     */
    protected function filtered(Builder $query, bool $withStatus = true): Builder
    {
        $search = trim($this->search);

        return $this->scoped($query)
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('lead_id', 'like', $like)
                    ->orWhere('full_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->when($withStatus && in_array($this->status, Lead::STATUSES, true),
                fn ($q) => $q->where('status', $this->status))
            ->when(in_array($this->source, $this->sources, true),
                fn ($q) => $q->where('source_name', $this->source))
            ->when(in_array($this->method, Lead::CONTACT_METHODS, true),
                fn ($q) => $q->where('contact_method', $this->method))
            ->when($this->branches->contains('branch_id', $this->branch),
                fn ($q) => $q->where('branch_id', $this->branch))
            ->when($this->validDate($this->from), fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->validDate($this->to), fn ($q) => $q->whereDate('created_at', '<=', $this->to));
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    public function render()
    {
        $leads = $this->filtered(Lead::query())
            ->with(['branch:branch_id,branch_name', 'latestOpportunity.employee:employee_id,full_name'])
            ->orderByDesc('created_at')
            ->orderByDesc('lead_id')
            ->paginate(self::PER_PAGE);

        return view('livewire.leads.index', [
            'leads' => $leads,
        ])->title(__('leads.title'));
    }
}
