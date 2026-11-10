<?php

namespace App\Livewire\Concerns;

use App\Models\Opportunity;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;

/**
 * Breadcrumb nối tiếp khi đi từ trang Chi tiết học viên sang cơ hội / báo giá / đơn hàng / hóa đơn:
 *   Quản lý học viên › STD002 › OPP027 › QUO006 › ORD005 › INV007
 * Trang học viên gắn ?student={mã} vào link; trang chi tiết nhận lại ở mount() (initStudentTrail),
 * chỉ giữ khi học viên trong phạm vi được xem và cùng khách (Lead) với bản ghi đang mở.
 * Link sang trang chi tiết khác trong chuỗi dùng trailQuery() để giữ ?student= cho trang sau.
 */
trait StudentTrail
{
    // Mã học viên của chuỗi điều hướng (null = không đi từ trang học viên)
    #[Locked]
    public ?string $trailStudent = null;

    protected function initStudentTrail(?string $opportunityId): void
    {
        $studentId = request()->query('student');

        if (! is_string($studentId) || $studentId === '' || ! $opportunityId) {
            return;
        }

        $student = Student::query()
            ->visibleTo(Auth::user()->employee)
            ->whereKey($studentId)
            ->with('opportunity:opportunity_id,lead_id')
            ->first();

        $leadId = Opportunity::query()->whereKey($opportunityId)->value('lead_id');

        if ($student && $leadId && $student->opportunity?->lead_id === $leadId) {
            $this->trailStudent = $student->student_id;
        }
    }

    /**
     * Tham số query giữ chuỗi điều hướng, ghép vào route(): route($name, [$model] + $this->trailQuery()).
     */
    public function trailQuery(): array
    {
        return $this->trailStudent ? ['student' => $this->trailStudent] : [];
    }

    /**
     * Các mục breadcrumb: Quản lý học viên › mã học viên › $items (mỗi mục [route, mã]; mục cuối là trang hiện tại).
     * Mục nào vai trò không có trang chi tiết thì chỉ hiện chữ.
     *
     * @param  array<int, array{0: string, 1: ?string}>  $items
     * @return array<int, array{label: string, url: ?string}>
     */
    public function studentTrail(array $items): array
    {
        $prefix = (string) Auth::user()->role_name;
        $url = function (string $name, ?string $key) use ($prefix) {
            $route = $prefix.'.'.$name.'.show';

            return $key !== null && Route::has($route) ? route($route, [$key] + $this->trailQuery()) : null;
        };

        $trail = [
            ['label' => __('menu.students'), 'url' => Route::has($prefix.'.students') ? route($prefix.'.students') : null],
            ['label' => $this->trailStudent, 'url' => Route::has($prefix.'.students.show') ? route($prefix.'.students.show', $this->trailStudent) : null],
        ];

        $last = array_key_last($items);
        foreach ($items as $i => [$name, $key]) {
            if ($key === null) {
                continue;
            }
            $trail[] = ['label' => $key, 'url' => $i === $last ? null : $url($name, $key)];
        }

        return $trail;
    }
}
