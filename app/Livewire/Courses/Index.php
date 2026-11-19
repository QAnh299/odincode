<?php

namespace App\Livewire\Courses;

use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Danh sách khóa học + tra cứu (tìm theo mã / tên, lọc trạng thái, sắp xếp),
 * hiển thị dạng thẻ hoặc bảng.
 * Chỉ xem: không thêm, kích hoạt hay xóa khóa học.
 * Dùng chung cho Giám đốc, Sale Admin, Sale Leader, Salesperson.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    // 12 chia hết cho 2, 3, 4 cột → lưới thẻ không bị hụt hàng cuối
    public const PER_PAGE = 12;

    public const SORTS = ['code', 'name', 'price_asc', 'price_desc', 'newest'];

    public const VIEWS = ['grid', 'table'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $state = '';

    #[Url(except: 'code')]
    public string $sort = 'code';

    #[Url(except: 'grid')]
    public string $view = 'grid';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'state', 'sort'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Bấm vào thẻ thống kê: lọc theo trạng thái, bấm lại thì bỏ lọc.
     */
    public function filterState(string $state): void
    {
        $this->state = $this->state === $state ? '' : $state;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'state', 'sort');
        $this->resetPage();
    }

    /**
     * Đổi chế độ hiển thị (thẻ / bảng); giữ nguyên bộ lọc và trang hiện tại.
     */
    public function setView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : 'grid';
    }

    /**
     * Prefix route theo vai trò, VD: 'director' → route 'director.courses.show'.
     */
    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Số khóa học theo từng trạng thái (cho các thẻ thống kê).
     */
    #[Computed]
    public function counts(): array
    {
        $counts = ['all' => Course::count()];

        foreach (Course::STATES as $state) {
            $counts[$state] = Course::query()->state($state)->count();
        }

        return $counts;
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->state !== '' || $this->sort !== 'code';
    }

    public function render()
    {
        $search = trim($this->search);

        // Giá trị lạ từ URL thì quay về mặc định
        if (! in_array($this->sort, self::SORTS, true)) {
            $this->sort = 'code';
        }
        if (! in_array($this->view, self::VIEWS, true)) {
            $this->view = 'grid';
        }

        $courses = Course::query()
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('course_id', 'like', $like)->orWhere('course_name', 'like', $like));
            })
            ->when(in_array($this->state, Course::STATES, true), fn ($q) => $q->state($this->state))
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('course_name'))
            ->when($this->sort === 'price_asc', fn ($q) => $q->orderBy('unit_price'))
            ->when($this->sort === 'price_desc', fn ($q) => $q->orderByDesc('unit_price'))
            ->when($this->sort === 'newest', fn ($q) => $q->orderByDesc('created_at'))
            ->orderBy('course_id') // mặc định, và để thứ tự ổn định khi trùng giá trị
            ->paginate(self::PER_PAGE);

        return view('livewire.courses.index', [
            'courses' => $courses,
        ])->title(__('courses.title'));
    }
}
