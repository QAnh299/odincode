<?php

namespace App\Livewire\Opportunities;

use App\Models\Opportunity;
use App\Models\Stage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Quản lý Opportunity của Salesperson dạng Kanban (giống Odoo CRM):
 * mỗi cột là một Stage (theo sort_order), mỗi thẻ là một Opportunity do chính mình phụ trách.
 * Không hiển thị trạng thái vì mỗi stage đã tương ứng một trạng thái.
 *
 * Bộ lọc theo ngày chuyển đổi (ngày Lead được chia cho salesperson):
 * nút chọn nhanh (Hôm nay, Hôm qua, 7 ngày qua, Tháng này) chỉ điền sẵn Từ ngày – Đến ngày,
 * nút đang sáng được suy ra từ khoảng ngày nên không cần lưu thêm trạng thái.
 * Hiện chỉ xem; chưa kéo thả đổi stage.
 */
#[Layout('layouts.app')]
class Kanban extends Component
{
    const PRESETS = ['today', 'yesterday', 'last7', 'month'];

    // Khoảng ngày chuyển đổi (Y-m-d) – có thể bị sửa từ client nên luôn kiểm tra qua validDate()
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    /**
     * Bấm nút chọn nhanh: điền Từ ngày – Đến ngày; '' = Tất cả.
     */
    public function applyPreset(string $preset): void
    {
        [$this->from, $this->to] = $this->presetRange($preset) ?? ['', ''];
    }

    public function resetFilters(): void
    {
        $this->reset('from', 'to');
    }

    public function hasFilters(): bool
    {
        return $this->from !== '' || $this->to !== '';
    }

    /**
     * Nút chọn nhanh đang khớp với khoảng ngày hiện tại ('' = Tất cả, null = khoảng tuỳ chọn).
     */
    public function activePreset(): ?string
    {
        if (! $this->hasFilters()) {
            return '';
        }

        foreach (self::PRESETS as $preset) {
            if ($this->presetRange($preset) === [$this->from, $this->to]) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Khoảng ngày [từ, đến] (Y-m-d) của một nút chọn nhanh.
     */
    protected function presetRange(string $preset): ?array
    {
        $today = CarbonImmutable::today();

        $range = match ($preset) {
            'today'     => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'last7'     => [$today->subDays(6), $today],
            'month'     => [$today->startOfMonth(), $today],
            default     => null,
        };

        return $range ? [$range[0]->toDateString(), $range[1]->toDateString()] : null;
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    #[Computed]
    public function stages(): Collection
    {
        return Stage::query()->orderBy('sort_order')->get();
    }

    public function render()
    {
        $opportunities = Opportunity::query()
            ->where('employee_id', Auth::user()->employee?->employee_id)
            ->when($this->validDate($this->from), fn ($q) => $q->whereDate('conversion_date', '>=', $this->from))
            ->when($this->validDate($this->to), fn ($q) => $q->whereDate('conversion_date', '<=', $this->to))
            ->with('lead:lead_id,full_name,phone,email,source_name,contact_method')
            ->orderByDesc('conversion_date')
            ->get();

        return view('livewire.opportunities.kanban', [
            'columns' => $opportunities->groupBy('stage_id'),
            'total'   => $opportunities->count(),
        ])->title(__('opportunities.title'));
    }
}
