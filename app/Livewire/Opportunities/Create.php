<?php

namespace App\Livewire\Opportunities;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\Stage;
use App\Models\Student;
use App\Services\OpportunityCreateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('layouts.app')]
class Create extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Locked]
    public ?string $selectedLeadId = null;

    public string $contactPhone = '';

    public string $contactEmail = '';

    public string $contactSourceUrl = '';

    #[Locked]
    public ?string $fromStudent = null;

    #[Locked]
    public bool $lockedLead = false;

    public function mount(): void
    {
        abort_unless(Auth::user()?->role_name === Role::SALESPERSON, 403);

        $studentId = request()->query('student');
        if ($studentId) {
            $this->preselectFromStudent((string) $studentId);
        }
    }

    protected function preselectFromStudent(string $studentId): void
    {
        $student = Student::query()
            ->visibleTo($this->me)
            ->where('student_id', $studentId)
            ->first(['student_id', 'lead_id']);

        if (! $student) {
            return;
        }

        $this->fromStudent = $student->student_id;

        if (! $student->lead_id) {
            return;
        }

        $lead = app(OpportunityCreateService::class)
            ->findForSalesperson($student->lead_id, $this->me);

        if ($lead) {
            $this->selectedLeadId = $lead->lead_id;
            $this->lockedLead     = true;
            $this->fillContact($lead);
        }
    }

    #[Computed]
    public function me(): Employee
    {
        return Auth::user()->employee;
    }

    #[Computed]
    public function results(): Collection
    {
        $search = trim($this->search);

        if ($search === '') {
            return collect();
        }

        $service  = app(OpportunityCreateService::class);
        $leads    = $service->candidates($this->me, $search);

        return $leads->map(fn (Lead $lead) => [
            'lead_id'   => $lead->lead_id,
            'full_name' => $lead->full_name,
            'phone'     => $lead->phone,
            'check'     => $service->check($lead, $this->me),
        ]);
    }

    #[Computed]
    public function defaultCandidates()
    {
        if (trim($this->search) !== '') {
            return null;
        }

        $service = app(OpportunityCreateService::class);

        return $service->candidatesPaginated($this->me)
            ->through(fn (Lead $lead) => [
                'lead_id'   => $lead->lead_id,
                'full_name' => $lead->full_name,
                'phone'     => $lead->phone,
                'check'     => $service->check($lead, $this->me),
            ]);
    }

    #[Computed]
    public function selected(): ?array
    {
        if (! $this->selectedLeadId) {
            return null;
        }

        $service = app(OpportunityCreateService::class);
        $lead    = $service->findForSalesperson($this->selectedLeadId, $this->me);

        if (! $lead) {
            $this->selectedLeadId = null;

            return null;
        }

        return [
            'lead'    => $lead,
            'check'   => $service->check($lead, $this->me),
            'profile' => $this->profileOf($lead),
        ];
    }

    protected function profileOf(Lead $lead): array
    {
        return [
            'student' => $lead->student,
        ];
    }

    #[Computed]
    public function firstStageName(): string
    {
        return Stage::query()->whereKey(Stage::UNTOUCHED)->value('stage_name') ?? Stage::UNTOUCHED;
    }

    public function updatedSearch(): void
    {
        if ($this->lockedLead) {
            return;
        }

        $this->selectedLeadId = null;
        $this->resetContact();
        $this->resetPage('defaultPage');
        unset($this->results, $this->selected, $this->defaultCandidates);
    }

    public function selectLead(string $leadId): void
    {
        if ($this->lockedLead) {
            return;
        }

        $this->selectedLeadId = $leadId;
        $this->resetContact();
        unset($this->selected);

        $service = app(OpportunityCreateService::class);
        $lead    = $service->findForSalesperson($leadId, $this->me);

        if ($lead) {
            $this->fillContact($lead);
        }
    }

    protected function fillContact(Lead $lead): void
    {
        $this->contactPhone     = $lead->phone ?? '';
        $this->contactEmail     = $lead->email ?? '';
        $this->contactSourceUrl = $lead->source_url ?? '';
    }

    public function clearSelection(): void
    {
        if ($this->lockedLead) {
            return;
        }

        $this->selectedLeadId = null;
        $this->resetContact();
        unset($this->selected);
    }

    protected function resetContact(): void
    {
        $this->contactPhone     = '';
        $this->contactEmail     = '';
        $this->contactSourceUrl = '';
    }

    public function confirm(): void
    {
        abort_unless(Auth::user()?->role_name === Role::SALESPERSON, 403);

        if (! $this->selectedLeadId) {
            return;
        }

        $service = app(OpportunityCreateService::class);
        $lead    = $service->findForSalesperson($this->selectedLeadId, $this->me);

        if (! $lead) {
            $this->addError('general', __('opportunities.create.error_no_permission'));

            return;
        }

        try {
            $opportunity = $service->create($lead, $this->me, [
                'phone'      => trim($this->contactPhone) ?: null,
                'email'      => trim($this->contactEmail) ?: null,
                'source_url' => trim($this->contactSourceUrl) ?: null,
            ]);
        } catch (RuntimeException $e) {
            $this->addError('general', $e->getMessage());
            unset($this->selected);

            return;
        }

        $this->redirect(
            route('salesperson.opportunities.show', $opportunity),
            navigate: true,
        );
    }

    public function backUrl(): string
    {
        return $this->fromStudent
            ? route('salesperson.students.show', $this->fromStudent)
            : route('salesperson.opportunities');
    }

    public function render()
    {
        return view('livewire.opportunities.create')
            ->title(__('opportunities.create.title'));
    }
}
