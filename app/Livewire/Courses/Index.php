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
 * Danh sách khóa học + tra cứu (tìm theo mã / tên, lọc trạng thái).
 * Chỉ xem: không thêm, kích hoạt hay xóa khóa học.
 * Dùng chung cho Giám đốc, Sale Admin, Sale Leader, Salesperson.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    const PER_PAGE = 10;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $state = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'state'], true)) {
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
        $this->reset('search', 'state');
        $this->resetPage();
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
        return $this->search !== '' || $this->state !== '';
    }

    public function render()
    {
        $search = trim($this->search);

        $courses = Course::query()
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('course_id', 'like', $like)->orWhere('course_name', 'like', $like));
            })
            ->when(in_array($this->state, Course::STATES, true), fn ($q) => $q->state($this->state))
            ->orderBy('course_id')
            ->paginate(self::PER_PAGE);

        return view('livewire.courses.index', [
            'courses' => $courses,
        ])->title(__('courses.title'));
    }
}
