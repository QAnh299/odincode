<?php

namespace App\Livewire\Vouchers;

use App\Models\Quotation;
use App\Models\Voucher;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Danh sách voucher + tra cứu (tìm kiếm, bộ lọc).
 * Dùng chung cho Giám đốc, Sale Admin, Sale Leader, Salesperson (không có ở Kế toán).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    const PER_PAGE = 10;

    const SORTS = ['newest', 'oldest', 'ending', 'code'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $state = '';

    // Khoảng ngày: voucher có hiệu lực giao với khoảng [from, to]
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'state', 'from', 'to', 'sort'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Bấm vào thẻ thống kê: lọc theo tình trạng, bấm lại thì bỏ lọc.
     */
    public function filterState(string $state): void
    {
        $this->state = $this->state === $state ? '' : $state;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'type', 'state', 'from', 'to', 'sort');
        $this->resetPage();
    }

    /**
     * Prefix route theo vai trò, VD: 'director' → route 'director.vouchers.show'.
     */
    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Số voucher theo từng tình trạng (cho các thẻ thống kê).
     */
    #[Computed]
    public function counts(): array
    {
        $counts = ['all' => Voucher::count()];

        foreach (Voucher::STATES as $state) {
            $counts[$state] = Voucher::query()->state($state)->count();
        }

        return $counts;
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->type !== '' || $this->state !== ''
            || $this->from !== '' || $this->to !== '' || $this->sort !== 'newest';
    }

    public function render()
    {
        $employee = Auth::user()->employee;
        $search = trim($this->search);

        $vouchers = Voucher::query()
            ->withCount(['quotations as used_count' => fn ($q) => $q->visibleTo($employee)])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('voucher_id', 'like', '%'.addcslashes($search, '%_\\').'%');

                    // Gõ số thì tìm luôn theo giá trị giảm (VD: 10 → voucher 10%)
                    $number = str_replace(['.', ',', ' ', '%', '₫'], '', $search);
                    if (ctype_digit($number)) {
                        $q->orWhere('value', (int) $number);
                    }
                });
            })
            ->when(in_array($this->type, [Voucher::TYPE_PERCENTAGE, Voucher::TYPE_FIXED], true),
                fn ($q) => $q->where('discount_type', $this->type))
            ->when(in_array($this->state, Voucher::STATES, true), fn ($q) => $q->state($this->state))
            ->when($this->validDate($this->from), fn ($q) => $q->whereDate('end_date', '>=', $this->from))
            ->when($this->validDate($this->to), fn ($q) => $q->whereDate('start_date', '<=', $this->to))
            ->tap(fn ($q) => match ($this->sort) {
                'oldest' => $q->orderBy('start_date')->orderBy('voucher_id'),
                'ending' => $q->orderBy('end_date')->orderBy('voucher_id'),
                'code'   => $q->orderBy('voucher_id'),
                default  => $q->orderByDesc('start_date')->orderByDesc('voucher_id'),
            })
            ->paginate(self::PER_PAGE);

        return view('livewire.vouchers.index', [
            'vouchers' => $vouchers,
        ])->title(__('vouchers.title'));
    }

    private function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }
}
