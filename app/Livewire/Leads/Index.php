<?php

namespace App\Livewire\Leads;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Role;
use App\Services\LeadDuplicateService;
use App\Services\LeadImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public const PER_PAGE = 10;

    public const STATS_TTL = [30, 120];

    public const UNASSIGNED = 'Unassigned';

    public const PENDING_SALESPERSON = 'PendingSales';

    public const ASSIGNED = 'Assigned';

    public const RETURNING = 'Returning';

    public const ASSIGN_FILTERS = [self::UNASSIGNED, self::PENDING_SALESPERSON, self::ASSIGNED, self::RETURNING];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $assign = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $method = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public const FILTERS = ['search', 'status', 'assign', 'source', 'method', 'branch', 'from', 'to'];

    public string $modal = '';

    public array $form = [];

    #[Locked]
    public ?array $duplicate = null;

    public array $updateFields = [];

    public $file = null;

    #[Locked]
    public ?array $importResult = null;

    #[Locked]
    public string $importError = '';

    #[Locked]
    public string $previewToken = '';

    public array $decisions = [];

    public string $previewFilter = 'review';

    public int $previewPage = 1;

    public ?int $previewOpen = null;

    public const PREVIEW_PER_PAGE = 20;

    public ?array $notice = null;

    public function updated(string $property): void
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }

        if (str_starts_with($property, 'form')) {
            $this->duplicate = null;
        }
    }

    public function filterCard(string $key): void
    {
        $active = $this->activeCard() === $key;
        $this->status = ! $active && in_array($key, Lead::STATUSES, true) ? $key : '';
        $this->assign = ! $active && in_array($key, self::ASSIGN_FILTERS, true) ? $key : '';
        $this->resetPage();
    }

    public function activeCard(): string
    {
        return $this->assign !== '' ? $this->assign : $this->status;
    }

    #[Computed]
    public function pendingKey(): string
    {
        return $this->routePrefix === Role::SALE_LEADER ? self::PENDING_SALESPERSON : self::UNASSIGNED;
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

    #[Computed]
    public function canManage(): bool
    {
        return in_array($this->routePrefix, [Role::SALE_ADMIN, Role::SALE_LEADER], true);
    }

    #[Computed]
    public function canCreate(): bool
    {
        return $this->routePrefix === Role::SALE_ADMIN;
    }

    public function canAssign(Lead $lead): bool
    {
        if ($lead->status !== Lead::STATUS_NEW) {
            return false;
        }

        $latest = $lead->latestOpportunity;
        $active = $latest?->status === Opportunity::STATUS_IN_PROGRESS;

        return match ($this->routePrefix) {
            Role::SALE_ADMIN  => ! $active,
            Role::SALE_LEADER => $active && $latest->stage_id === \App\Models\Stage::UNTOUCHED
                && $latest->employee_id === Auth::user()->employee?->employee_id,
            default           => false,
        };
    }

    protected function authorizeCreate(): void
    {
        abort_unless($this->canCreate, 403);
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->duplicate = null;
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorizeCreate();

        $this->form = [
            'full_name'      => '',
            'phone'          => '',
            'email'          => '',
            'source_name'    => '',
            'source_url'     => '',
            'contact_method' => '',
            'branch_id'      => '',
        ];
        $this->duplicate = null;
        $this->resetValidation();
        $this->modal = 'create';
    }

    public function saveLead(LeadImportService $service, LeadDuplicateService $duplicates): void
    {
        $this->authorizeCreate();

        $validated = $this->validatedForm($service);
        $match = $duplicates->check($validated);

        if ($match['case'] === LeadDuplicateService::NEW) {
            $this->createLead($service, $validated);

            return;
        }

        $this->duplicate = $this->describeMatch($duplicates, $match, $validated);
    }

    public function createAsOther(LeadImportService $service, LeadDuplicateService $duplicates): void
    {
        $this->authorizeCreate();

        if (! $this->duplicate) {
            $this->saveLead($service, $duplicates);

            return;
        }

        $this->createLead($service, $this->validatedForm($service));
    }

    public function sameAs(string $leadId, LeadImportService $service, LeadDuplicateService $duplicates): void
    {
        $this->authorizeCreate();

        if (! $this->duplicate) {
            $this->saveLead($service, $duplicates);

            return;
        }

        abort_unless(in_array($leadId, array_column($this->duplicate['leads'], 'lead_id'), true), 403);

        $validated = $this->validatedForm($service);
        $lead = $duplicates->find($leadId);
        abort_unless($lead, 404);

        $state = $duplicates->stateOf($lead);

        if ($state !== LeadDuplicateService::REOPEN) {
            $this->duplicate = $this->describeMatch($duplicates, ['case' => $state, 'lead' => $lead, 'leads' => collect([$lead])], $validated, confirmed: true);

            return;
        }

        if ($this->duplicate['case'] !== LeadDuplicateService::REOPEN) {
            $described = $this->describeMatch($duplicates, ['case' => $state, 'lead' => $lead, 'leads' => collect([$lead])], $validated, confirmed: true);

            if ($this->updateFields) {
                $this->duplicate = $described;

                return;
            }
        }

        try {
            $result = $duplicates->reopen($lead, $validated, $this->updateFields);
        } catch (RuntimeException) {
            $lead = $duplicates->find($leadId);
            $this->duplicate = $this->describeMatch($duplicates, ['case' => $duplicates->stateOf($lead), 'lead' => $lead, 'leads' => collect([$lead])], $validated, confirmed: true);

            return;
        }

        $notes = $duplicates->reopenNotes($result);
        $text = __('leads.duplicate.reopened', ['id' => $result['lead']->lead_id, 'name' => $result['lead']->full_name]);

        $this->modal = '';
        $this->duplicate = null;

        $this->announce(['type' => $notes ? 'warning' : 'success', 'text' => trim($text.' '.implode(' ', $notes))]);
        $this->resetPage();
    }

    public function updateExisting(LeadImportService $service, LeadDuplicateService $duplicates): void
    {
        $this->authorizeCreate();

        if (! $this->duplicate || ! $this->duplicate['lead']) {
            $this->saveLead($service, $duplicates);

            return;
        }

        $validated = $this->validatedForm($service);

        try {
            $result = $duplicates->updateExisting($this->duplicate['lead'], $validated, $this->updateFields, $service);
        } catch (RuntimeException $e) {
            $this->addError('duplicate', $e->getMessage());

            return;
        }

        if (! $result['fields']) {
            $this->addError('duplicate', __('leads.duplicate.update_none'));

            return;
        }

        $fields = implode(', ', array_map(fn ($field) => $this->fieldLabel($field), $result['fields']));

        $this->modal = '';
        $this->duplicate = null;
        $this->announce(['type' => 'success', 'text' => __('leads.duplicate.updated', ['id' => $result['lead']->lead_id, 'fields' => $fields])]);
        $this->resetPage();
    }

    protected function fieldLabel(string $field): string
    {
        return $field === 'branch_id' ? __('leads.branch') : __('leads.field.'.$field);
    }

    protected function displayValue(string $field, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return match ($field) {
            'branch_id'      => $this->branches->firstWhere('branch_id', $value)?->branch_name ?? $value,
            'contact_method' => __('leads.method.'.$value),
            default          => $value,
        };
    }

    protected function validatedForm(LeadImportService $service): array
    {
        $data = $service->normalize($this->form);

        return Validator::make($data, $service->rules(), $service->messages(), $service->attributes())
            ->validate();
    }

    protected function createLead(LeadImportService $service, array $validated): void
    {
        $lead = $service->create($validated);

        $this->modal = '';
        $this->duplicate = null;
        $this->announce(['type' => 'success', 'text' => __('leads.form.created', ['id' => $lead->lead_id, 'name' => $lead->full_name])]);
        $this->resetPage();
    }

    protected function describeMatch(LeadDuplicateService $duplicates, array $match, array $data, bool $confirmed = false): array
    {
        $leads = $match['lead'] ? collect([$match['lead']]) : $match['leads'];

        $updatable = $match['lead'] !== null
            && in_array($match['case'], [LeadDuplicateService::IN_QUEUE, LeadDuplicateService::IN_PROGRESS, LeadDuplicateService::REOPEN], true);

        $items = $leads->map(function (Lead $lead) use ($duplicates, $data, $updatable) {
            $active = $lead->opportunities->firstWhere('status', Opportunity::STATUS_IN_PROGRESS);
            $state = $duplicates->stateOf($lead);

            $waitingTeam = $state === LeadDuplicateService::IN_QUEUE && $active;

            return [
                'lead_id'   => $lead->lead_id,
                'name'      => $lead->full_name,
                'learner'   => $duplicates->learnerName($lead),
                'contact'   => collect([$lead->phone, $lead->email])->filter()->implode(' · '),
                'state'     => $waitingTeam ? 'in_queue_team' : $state,
                'owner'     => $waitingTeam ? null : $active?->employee?->full_name,
                'url'       => route($this->routePrefix.'.leads.show', $lead),

                'locked_reason' => $duplicates->lockReason($lead),
                'changes'   => collect($duplicates->changes($lead, $data, $updatable))
                    ->map(fn ($change, $field) => $change + [
                        'label'    => $this->fieldLabel($field),
                        'old_text' => $this->displayValue($field, $change['old']),
                        'new_text' => $this->displayValue($field, $change['new']),
                    ])->all(),
            ];
        })->values()->all();

        $this->updateFields = $updatable
            ? array_keys(array_filter($items[0]['changes'], fn ($change) => $change['editable']))
            : [];

        return [
            'case'      => $match['case'],

            'confirmed' => $confirmed,
            'updatable' => $updatable,
            'message'   => $duplicates->message($match['case'], $match['lead'], $match['leads']),
            'lead'      => $match['lead']?->lead_id,
            'leads'     => $items,
        ];
    }

    public function openImport(): void
    {
        $this->authorizeCreate();

        if ($this->previewToken !== '') {
            app(LeadImportService::class)->discardPreview($this->previewToken);
        }

        $this->reset('file', 'importResult', 'importError', 'previewToken', 'decisions', 'previewFilter', 'previewPage', 'previewOpen');
        unset($this->preview);
        $this->resetValidation();
        $this->modal = 'import';
    }

    public function downloadTemplate(LeadImportService $service)
    {
        $this->authorizeCreate();

        return response()
            ->download($service->template(), 'mau-import-lead.xlsx')
            ->deleteFileAfterSend();
    }

    public function updatedFile(): void
    {
        $this->reset('importResult', 'importError');
        $this->validateFile();
    }

    protected function validateFile(): void
    {
        $this->validate(
            ['file' => ['required', 'file', 'extensions:xlsx', 'max:5120']],
            [
                'file.required'   => __('leads.excel.file_required'),
                'file.extensions' => __('leads.excel.file_type'),
                'file.max'        => __('leads.excel.file_size'),
            ],
        );
    }

    public function checkFile(LeadImportService $service): void
    {
        $this->authorizeCreate();
        $this->validateFile();

        $result = $service->preview($this->file->getRealPath());

        $this->file->delete();
        $this->reset('file');

        if (! $result['ok']) {
            $this->importError = $result['error'];

            return;
        }

        $this->previewToken = $result['token'];
        unset($this->preview);

        $this->decisions = collect($this->preview['rows'] ?? [])->mapWithKeys(fn ($row) => [$row['line'] => $row['default']])->all();
        $this->previewFilter = 'review';
        $this->previewPage = 1;
        $this->previewOpen = null;
        unset($this->preview);

        if (! $this->previewCount('review')) {
            $this->previewFilter = 'all';
        }
    }

    #[Computed]
    public function preview(): ?array
    {
        return $this->previewToken !== '' ? app(LeadImportService::class)->loadPreview($this->previewToken) : null;
    }

    public function previewCount(string $filter): int
    {
        return count($this->previewRows($filter));
    }

    protected function previewRows(string $filter): array
    {
        $rows = $this->preview['rows'] ?? [];

        return array_values(match ($filter) {
            'review'  => array_filter($rows, fn ($r) => ! in_array($r['kind'], [LeadDuplicateService::NEW, LeadImportService::ROW_INVALID], true)),
            'invalid' => array_filter($rows, fn ($r) => $r['kind'] === LeadImportService::ROW_INVALID),
            'new'     => array_filter($rows, fn ($r) => $r['kind'] === LeadDuplicateService::NEW),
            default   => $rows,
        });
    }

    public function previewPageRows(): array
    {
        return array_slice($this->previewRows($this->previewFilter), ($this->previewPage - 1) * self::PREVIEW_PER_PAGE, self::PREVIEW_PER_PAGE);
    }

    public function previewPages(): int
    {
        return max(1, (int) ceil($this->previewCount($this->previewFilter) / self::PREVIEW_PER_PAGE));
    }

    public function setPreviewFilter(string $filter): void
    {
        $this->previewFilter = in_array($filter, ['all', 'review', 'invalid', 'new'], true) ? $filter : 'all';
        $this->previewPage = 1;
        $this->previewOpen = null;
    }

    public function gotoPreviewPage(int $page): void
    {
        $this->previewPage = min(max(1, $page), $this->previewPages());
        $this->previewOpen = null;
    }

    public function togglePreviewRow(int $line): void
    {
        $this->previewOpen = $this->previewOpen === $line ? null : $line;
    }

    public function decisionFor(array $row): string
    {
        $chosen = $this->decisions[$row['line']] ?? null;

        return in_array($chosen, $row['options'], true) ? $chosen : $row['default'];
    }

    public function previewSummary(): array
    {
        $summary = ['create' => 0, 'update' => 0, 'reopen' => 0, 'skip' => 0];

        foreach ($this->preview['rows'] ?? [] as $row) {
            $action = $this->decisionFor($row);
            $key = str_starts_with($action, LeadImportService::ACTION_SAME) ? 'update' : $action;
            $summary[$key] = ($summary[$key] ?? 0) + 1;
        }

        return $summary;
    }

    public function cancelPreview(LeadImportService $service): void
    {
        $this->authorizeCreate();

        if ($this->previewToken !== '') {
            $service->discardPreview($this->previewToken);
        }

        $this->reset('previewToken', 'decisions', 'previewFilter', 'previewPage', 'previewOpen', 'importError');
        unset($this->preview);
    }

    public function confirmImport(LeadImportService $service): void
    {
        $this->authorizeCreate();

        $result = $service->commit($this->previewToken, $this->decisions);

        $this->reset('previewToken', 'decisions', 'previewFilter', 'previewPage', 'previewOpen');
        unset($this->preview);

        if (! $result['ok']) {
            $this->importError = $result['error'];

            return;
        }

        $this->importResult = $result;
        $this->announce(match (true) {
            $result['failed'] === 0 && $result['skipped'] === 0 => ['type' => 'success', 'text' => __('leads.excel.done_all', ['count' => $result['success']])],
            $result['success'] === 0 => ['type' => 'danger', 'text' => __('leads.import_review.done_none', ['skipped' => $result['skipped'], 'failed' => $result['failed']])],
            default => ['type' => 'warning', 'text' => __('leads.import_review.done_partial', [
                'total' => $result['total'], 'success' => $result['success'], 'skipped' => $result['skipped'], 'failed' => $result['failed'],
            ])],
        });

        $this->resetPage();
    }

    public function downloadResult()
    {
        $this->authorizeCreate();

        $path = $this->importResult['result'] ?? null;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            $this->importError = __('leads.excel.result_missing');

            return null;
        }

        return Storage::disk('local')->download($path, 'ket-qua-import-lead-'.now()->format('Ymd-His').'.xlsx');
    }

    #[On('leads-distributed')]
    public function distributed(array $notice): void
    {
        $this->announce($notice);
        $this->resetPage();
    }

    private function announce(array $notice): void
    {
        $this->notice = $notice;

        if ($notice['type'] === 'success') {
            $this->dispatch('lead-toast', text: $notice['text']);
        }
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
    }

    #[Computed]
    public function canFilterBranch(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

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

    #[Computed]
    public function sources(): array
    {
        return Lead::SOURCES;
    }

    #[Computed]
    public function stats(): array
    {
        $key = 'leads.stats:'.md5(json_encode([
            Cache::get(Lead::STATS_VERSION_KEY),
            Auth::id(),
            $this->pendingKey,
            trim($this->search), $this->source, $this->method, $this->branch, $this->from, $this->to,
        ]));

        return Cache::flexible($key, self::STATS_TTL, fn () => $this->computeStats());
    }

    protected function computeStats(): array
    {
        $row = $this->filtered(Lead::query(), withStatus: false)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(status = ?) AS converted_count', [Lead::STATUS_CONVERTED])
            ->first();

        $pending = $this->filtered(Lead::query(), withStatus: false)
            ->{$this->pendingKey === self::UNASSIGNED ? 'unassigned' : 'pendingSalesperson'}()
            ->count();

        return [
            'all'                  => (int) $row->total,
            $this->pendingKey      => $pending,
            self::ASSIGNED         => (int) $row->total - $pending,
            self::RETURNING        => $this->filtered(Lead::query(), withStatus: false)->returning()->count(),
            Lead::STATUS_CONVERTED => (int) $row->converted_count,
        ];
    }

    protected function knownTotal(): ?int
    {
        $status = in_array($this->status, Lead::STATUSES, true) ? $this->status : '';
        $assign = in_array($this->assign, self::ASSIGN_FILTERS, true) ? $this->assign : '';

        if ($status !== '' && $assign !== '') {
            return null;
        }

        $stats = $this->stats;

        return match (true) {
            $status === '' && $assign === '' => $stats['all'],
            $status === Lead::STATUS_CONVERTED => $stats[Lead::STATUS_CONVERTED],
            $status === Lead::STATUS_NEW => $stats['all'] - $stats[Lead::STATUS_CONVERTED],
            default => $stats[$assign] ?? null,
        };
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

    protected function scoped(Builder $query): Builder
    {
        return $query->visibleTo(Auth::user()->employee);
    }

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
            ->when(
                $withStatus && in_array($this->status, Lead::STATUSES, true),
                fn ($q) => $q->where('status', $this->status)
            )
            ->when(
                $withStatus && $this->assign === self::UNASSIGNED,
                fn ($q) => $q->unassigned()
            )
            ->when(
                $withStatus && $this->assign === self::PENDING_SALESPERSON,
                fn ($q) => $q->pendingSalesperson()
            )
            ->when(
                $withStatus && $this->assign === self::ASSIGNED,
                fn ($q) => $q->whereNot(fn ($q) => $this->pendingKey === self::UNASSIGNED ? $q->unassigned() : $q->pendingSalesperson())
            )
            ->when(
                $withStatus && $this->assign === self::RETURNING,
                fn ($q) => $q->returning()
            )
            ->when(
                in_array($this->source, $this->sources, true),
                fn ($q) => $q->where('source_name', $this->source)
            )
            ->when(
                in_array($this->method, Lead::CONTACT_METHODS, true),
                fn ($q) => $q->where('contact_method', $this->method)
            )
            ->when(
                $this->branches->contains('branch_id', $this->branch),
                fn ($q) => $q->where('branch_id', $this->branch)
            )
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
            ->orderByDesc('created_at')
            ->orderByDesc('lead_id')
            ->paginate(self::PER_PAGE, ['lead_id'], total: $this->knownTotal());

        $leads->setCollection(Lead::query()
            ->with(['branch:branch_id,branch_name', 'latestOpportunity.employee:employee_id,full_name', 'latestOpportunity.employee.ledTeam:team_id,team_name,team_leader_id'])
            ->whereIn('lead_id', $leads->pluck('lead_id'))
            ->orderByDesc('created_at')
            ->orderByDesc('lead_id')
            ->get());

        return view('livewire.leads.index', [
            'leads' => $leads,
        ])->title(__('leads.title'));
    }
}
