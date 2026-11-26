<?php

namespace App\Livewire\Concerns;

use App\Models\Opportunity;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;

trait StudentTrail
{
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
            ->first(['student_id', 'lead_id']);

        $leadId = Opportunity::query()->whereKey($opportunityId)->value('lead_id');

        if ($student && $leadId && $student->lead_id === $leadId) {
            $this->trailStudent = $student->student_id;
        }
    }

    public function trailQuery(): array
    {
        return $this->trailStudent ? ['student' => $this->trailStudent] : [];
    }

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
