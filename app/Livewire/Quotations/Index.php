<?php

namespace App\Livewire\Quotations;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SalesTeam;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    public const SORTS = ['newest', 'oldest', 'expiring', 'code', 'total_desc', 'total_asc', 'customer'];

    public const DEADLINES = ['valid', 'soon', 'overdue'];

    public const SOON_DAYS = 3;

    public const ORDER_STATES = ['with', 'without'];

    public const MAX_TERMS = 8;
    public const MAX_TERM_LENGTH = 100;

    public const FILTER_DEFAULTS = [
        'statuses'    => [],
        'deadline'    => '',
        'orderState'  => '',
        'createdFrom' => '',
        'createdTo'   => '',
        'expiryFrom'  => '',
        'expiryTo'    => '',
        'team'        => '',
        'employee'    => '',
    ];

    #[Url(as: 'q', except: '')]
    public $search = '';

    #[Url(as: 'status')]
    public $statuses = [];

    #[Url(as: 'deadline', except: '')]
    public $deadline = '';

    #[Url(as: 'order', except: '')]
    public $orderState = '';

    #[Url(as: 'created_from', except: '')]
    public $createdFrom = '';

    #[Url(as: 'created_to', except: '')]
    public $createdTo = '';

    #[Url(as: 'expiry_from', except: '')]
    public $expiryFrom = '';

    #[Url(as: 'expiry_to', except: '')]
    public $expiryTo = '';

    #[Url(except: '')]
    public $team = '';

    #[Url(except: '')]
    public $employee = '';

    #[Url(except: 'newest')]
    public $sort = 'newest';

    public array $form = self::FILTER_DEFAULTS;

    private array $employeesCache = [];

    public function mount(): void
    {
        $this->sanitizeFilters();
        $this->form = $this->appliedFilters();
    }

    public function updated(string $property): void
    {
        $this->sanitizeFilters();

        if ($property === 'form.team') {
            unset($this->formEmployeeOptions);

            if (! $this->formEmployeeOptions->contains('employee_id', $this->form['employee'])) {
                $this->form['employee'] = '';
            }
        }

        if (in_array($property, ['search', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function applyFilters(): void
    {
        $this->sanitizeFilters();
        $this->resetErrorBag();

        foreach (['created' => ['createdFrom', 'createdTo'], 'expiry' => ['expiryFrom', 'expiryTo']] as [$from, $to]) {
            if ($this->dateRange($this->form[$from], $this->form[$to])[2]) {
                $this->addError('form.'.$to, __('quotations.invalid_range'));
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        foreach (self::FILTER_DEFAULTS as $property => $default) {
            $this->{$property} = $this->form[$property];
        }

        $this->resetPage();

        $this->dispatch('quotation-filters-applied');
    }

    public function resetForm(): void
    {
        $this->form = array_merge(self::FILTER_DEFAULTS, ['statuses' => $this->form['statuses']]);
        $this->resetErrorBag();
    }

    public function filterStatus(string $status): void
    {
        $this->statuses = $status === '' || $this->activeStatuses() === [$status] ? [] : [$status];
        $this->form['statuses'] = $this->statuses;
        $this->resetPage();
    }

    public function clearFilter(string $key): void
    {
        $properties = match ($key) {
            'search'   => ['search'],
            'statuses' => ['statuses'],
            'deadline' => ['deadline'],
            'order'    => ['orderState'],
            'created'  => ['createdFrom', 'createdTo'],
            'expiry'   => ['expiryFrom', 'expiryTo'],
            'team'     => ['team'],
            'employee' => ['employee'],
            default    => [],
        };

        foreach ($properties as $property) {
            $this->reset($property);

            if (array_key_exists($property, self::FILTER_DEFAULTS)) {
                $this->form[$property] = self::FILTER_DEFAULTS[$property];
            }
        }

        $this->resetErrorBag();
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'sort', ...array_keys(self::FILTER_DEFAULTS));
        $this->form = self::FILTER_DEFAULTS;
        $this->resetErrorBag();
        $this->resetPage();
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    #[Computed]
    public function canFilterTeam(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN, Role::ACCOUNTANT], true);
    }

    #[Computed]
    public function canFilterEmployee(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN, Role::ACCOUNTANT, Role::SALE_LEADER], true);
    }

    #[Computed]
    public function teams(): Collection
    {
        return $this->canFilterTeam
            ? SalesTeam::query()->orderBy('team_name')->get(['team_id', 'team_name'])
            : collect();
    }

    #[Computed]
    public function activeTeam(): ?SalesTeam
    {
        return $this->findTeam($this->team);
    }

    #[Computed]
    public function formEmployeeOptions(): Collection
    {
        return $this->employeesFor($this->findTeam($this->form['team']));
    }

    #[Computed]
    public function activeEmployee(): ?Employee
    {
        return $this->employee === '' ? null : $this->employeesFor($this->activeTeam)->firstWhere('employee_id', $this->employee);
    }

    public function activeStatuses(): array
    {
        return array_values(array_intersect(Quotation::STATUSES, $this->stringList($this->statuses)));
    }

    public function activeDeadline(): ?string
    {
        return in_array($this->deadline, self::DEADLINES, true) ? $this->deadline : null;
    }

    public function activeOrderState(): ?string
    {
        return in_array($this->orderState, self::ORDER_STATES, true) ? $this->orderState : null;
    }

    public function activeSort(): string
    {
        return in_array($this->sort, self::SORTS, true) ? $this->sort : 'newest';
    }

    #[Computed]
    public function createdRange(): array
    {
        return $this->dateRange($this->createdFrom, $this->createdTo);
    }

    #[Computed]
    public function expiryRange(): array
    {
        return $this->dateRange($this->expiryFrom, $this->expiryTo);
    }

    #[Computed]
    public function terms(): array
    {
        return $this->parseTerms($this->search);
    }

    #[Computed]
    public function counts(): array
    {
        $rows = $this->filteredQuery(withStatus: false)
            ->selectRaw('Quotations.status, COUNT(*) AS total')
            ->groupBy('Quotations.status')
            ->pluck('total', 'status');

        $counts = ['all' => (int) $rows->sum()];

        foreach (Quotation::STATUSES as $status) {
            $counts[$status] = (int) ($rows[$status] ?? 0);
        }

        return $counts;
    }

    #[Computed]
    public function hasAnyQuotation(): bool
    {
        return $this->scopedQuery()->exists();
    }

    #[Computed]
    public function chips(): array
    {
        $chips = [];
        $label = fn (string $group, string $value) => __('quotations.chip.'.$group).': '.$value;

        if ($this->terms !== []) {
            $chips['search'] = $label('search', trim($this->search));
        }

        if ($statuses = $this->activeStatuses()) {
            $chips['statuses'] = $label('statuses', collect($statuses)->map(fn ($s) => __('quotations.status.'.$s))->implode(', '));
        }

        if ($deadline = $this->activeDeadline()) {
            $chips['deadline'] = $label('deadline', __('quotations.deadline.'.$deadline));
        }

        if ($orderState = $this->activeOrderState()) {
            $chips['order'] = $label('order', __('quotations.order_state.'.$orderState));
        }

        $date = fn (?string $value) => $value === null ? null : Carbon::parse($value)->format('d/m/Y');

        [$createdFrom, $createdTo] = $this->createdRange;
        if ($createdFrom !== null || $createdTo !== null) {
            $chips['created'] = $label('created', $this->rangeText($date($createdFrom), $date($createdTo)));
        }

        [$expiryFrom, $expiryTo] = $this->expiryRange;
        if ($expiryFrom !== null || $expiryTo !== null) {
            $chips['expiry'] = $label('expiry', $this->rangeText($date($expiryFrom), $date($expiryTo)));
        }

        if ($this->activeTeam) {
            $chips['team'] = $label('team', $this->activeTeam->team_name);
        }

        if ($this->activeEmployee) {
            $chips['employee'] = $label('employee', $this->activeEmployee->full_name);
        }

        return $chips;
    }

    public function hasPendingChanges(): bool
    {
        $normalize = function (array $filters): array {
            $filters['statuses'] = array_values(array_intersect(Quotation::STATUSES, $this->stringList($filters['statuses'] ?? [])));

            return $filters;
        };

        return $normalize($this->form) != $normalize($this->appliedFilters());
    }

    public function hasFilters(): bool
    {
        return $this->chips !== [] || trim($this->search) !== '' || $this->activeSort() !== 'newest'
            || $this->form != self::FILTER_DEFAULTS || $this->appliedFilters() != self::FILTER_DEFAULTS;
    }

    public function highlight(?string $text): HtmlString
    {
        $text = (string) $text;
        $needles = collect($this->terms)->reject(fn ($t) => $t['exclude'])->map(fn ($t) => self::fold($t['text']))->filter()->all();

        if ($text === '' || $needles === []) {
            return new HtmlString(e($text));
        }

        $haystack = self::fold($text);
        $ranges = [];

        foreach ($needles as $needle) {
            $length = mb_strlen($needle);
            for ($pos = mb_strpos($haystack, $needle); $pos !== false; $pos = mb_strpos($haystack, $needle, $pos + $length)) {
                $ranges[] = [$pos, $pos + $length];
            }
        }

        if ($ranges === []) {
            return new HtmlString(e($text));
        }

        sort($ranges);
        $merged = [array_shift($ranges)];
        foreach ($ranges as [$start, $end]) {
            $last = count($merged) - 1;

            if ($start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        $html = '';
        $cursor = 0;
        foreach ($merged as [$start, $end]) {
            $html .= e(mb_substr($text, $cursor, $start - $cursor))
                .'<mark class="qt-mark">'.e(mb_substr($text, $start, $end - $start)).'</mark>';
            $cursor = $end;
        }

        return new HtmlString($html.e(mb_substr($text, $cursor)));
    }

    public function render()
    {
        $this->sanitizeFilters();

        $quotations = $this->filteredQuery(withStatus: true)
            ->withTotal()
            ->with([
                'voucher',
                'order:order_id,quotation_id',
                'employee:employee_id,full_name',
                'opportunity:opportunity_id,lead_id',
                'opportunity.lead:lead_id,full_name',
            ])
            ->tap(fn ($q) => $this->applySort($q))
            ->paginate(self::PER_PAGE);

        return view('livewire.quotations.index', [
            'quotations' => $quotations,
        ])->title(__('quotations.title'));
    }

    private function viewer(): ?Employee
    {
        return Auth::user()->employee;
    }

    private function scopedQuery(): Builder
    {
        return Quotation::query()->visibleTo($this->viewer());
    }

    private function filteredQuery(bool $withStatus): Builder
    {
        $query = $this->scopedQuery();

        $this->applySearch($query);
        $this->applyFilterConditions($query, $withStatus);

        return $query;
    }

    private function parseTerms(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $pattern = substr_count($search, '"') % 2 === 0 ? '/(-?)"([^"]*)"|(\S+)/u' : '/()()(\S+)/u';
        preg_match_all($pattern, $search, $matches, PREG_SET_ORDER);

        $terms = [];
        foreach ($matches as $match) {
            if (isset($match[3]) && $match[3] !== '') {
                $token = str_replace('"', '', $match[3]);
                $exclude = mb_strlen($token) > 1 && str_starts_with($token, '-');
                $text = $exclude ? mb_substr($token, 1) : $token;
            } else {
                $exclude = $match[1] === '-';
                $text = $match[2];
            }

            $text = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $text)), 0, self::MAX_TERM_LENGTH);
            if ($text === '' || $text === '-') {
                continue;
            }

            $terms[] = ['text' => $text, 'needle' => str_replace(['đ', 'Đ'], ['d', 'D'], $text), 'exclude' => $exclude];

            if (count($terms) >= self::MAX_TERMS) {
                break;
            }
        }

        return $terms;
    }

    private function applySearch(Builder $query): void
    {
        foreach ($this->terms as $term) {
            $like = '%'.addcslashes($term['needle'], '%_\\').'%';
            $statuses = $this->matchingStatuses($term['text']);
            $match = fn (Builder $q) => $this->matchTerm($q, $like, $statuses);

            $term['exclude'] ? $query->whereNot($match) : $query->where($match);
        }
    }

    private function matchingStatuses(string $text): array
    {
        $needle = self::fold($text);

        if (mb_strlen($needle) < 3) {
            return [];
        }

        $pattern = '/(^|\s)'.preg_quote($needle, '/').'/u';

        return array_values(array_filter(Quotation::STATUSES, fn ($status) => collect([
            $status,
            __('quotations.status.'.$status, [], 'vi'),
            __('quotations.status.'.$status, [], 'en'),
        ])->contains(fn ($label) => preg_match($pattern, self::fold($label)) === 1)));
    }

    private function matchTerm(Builder $query, string $like, array $statuses): void
    {
        $query
            ->where('Quotations.quotation_id', 'like', $like)
            ->when($statuses !== [], fn ($q) => $q->orWhereIn('Quotations.status', $statuses))
            ->orWhereRaw($this->foldColumn("COALESCE(Quotations.notes, '')").' LIKE ?', [$like])
            ->orWhere('Quotations.opportunity_id', 'like', $like)
            ->orWhere('Quotations.employee_id', 'like', $like)
            ->orWhereRaw("COALESCE(Quotations.voucher_id, '') LIKE ?", [$like])
            ->orWhereHas('opportunity.lead', fn ($q) => $q
                ->whereRaw($this->foldColumn('Leads.full_name').' LIKE ?', [$like])
                ->orWhere('Leads.email', 'like', $like)
                ->orWhere('Leads.phone', 'like', $like))
            ->orWhereHas('employee', fn ($q) => $q->whereRaw($this->foldColumn('Employees.full_name').' LIKE ?', [$like]))
            ->orWhereHas('order', fn ($q) => $q->where('SalesOrders.order_id', 'like', $like))
            ->orWhereHas('details', fn ($q) => $q
                ->where('QuotationDetails.course_id', 'like', $like)
                ->orWhereHas('course', fn ($q) => $q->whereRaw($this->foldColumn('Courses.course_name').' LIKE ?', [$like])));
    }

    private function applyFilterConditions(Builder $query, bool $withStatus): void
    {
        if ($withStatus && ($statuses = $this->activeStatuses())) {
            $query->whereIn('Quotations.status', $statuses);
        }

        $this->applyDeadline($query);
        $this->applyOrderState($query);
        $this->applyDates($query);

        if ($this->activeTeam) {
            $query->whereHas('employee', fn ($q) => $q->where('Employees.team_id', $this->activeTeam->team_id));
        }

        if ($this->activeEmployee) {
            $query->where('Quotations.employee_id', $this->activeEmployee->employee_id);
        }
    }

    private function applyDeadline(Builder $query): void
    {
        $deadline = $this->activeDeadline();
        if ($deadline === null) {
            return;
        }

        $today = today()->toDateString();

        $query->where('Quotations.status', Quotation::STATUS_DRAFT);

        match ($deadline) {
            'valid'   => $query->whereDate('Quotations.expiry_date', '>=', $today),
            'soon'    => $query->whereBetween('Quotations.expiry_date', [$today, today()->addDays(self::SOON_DAYS)->toDateString()]),
            'overdue' => $query->whereDate('Quotations.expiry_date', '<', $today),
        };
    }

    private function applyOrderState(Builder $query): void
    {
        match ($this->activeOrderState()) {
            'with'    => $query->has('order'),
            'without' => $query->doesntHave('order'),
            default   => null,
        };
    }

    private function applyDates(Builder $query): void
    {
        [$createdFrom, $createdTo] = $this->createdRange;
        [$expiryFrom, $expiryTo] = $this->expiryRange;

        $query
            ->when($createdFrom, fn ($q, $date) => $q->whereDate('Quotations.created_at', '>=', $date))
            ->when($createdTo, fn ($q, $date) => $q->whereDate('Quotations.created_at', '<=', $date))
            ->when($expiryFrom, fn ($q, $date) => $q->whereDate('Quotations.expiry_date', '>=', $date))
            ->when($expiryTo, fn ($q, $date) => $q->whereDate('Quotations.expiry_date', '<=', $date));
    }

    private function applySort(Builder $query): void
    {
        match ($this->activeSort()) {
            'oldest'     => $query->orderBy('Quotations.created_at')->orderBy('Quotations.quotation_id'),
            'expiring'   => $query->orderByRaw('CASE WHEN Quotations.status = ? THEN 0 ELSE 1 END', [Quotation::STATUS_DRAFT])
                ->orderBy('Quotations.expiry_date')->orderBy('Quotations.quotation_id'),
            'code'       => $query->orderBy('Quotations.quotation_id'),
            'total_desc' => $query->orderByDesc('computed_total')->orderBy('Quotations.quotation_id'),
            'total_asc'  => $query->orderBy('computed_total')->orderBy('Quotations.quotation_id'),
            'customer'   => $query->orderBy(
                Lead::query()->select('Leads.full_name')
                    ->join('Opportunities', 'Opportunities.lead_id', '=', 'Leads.lead_id')
                    ->whereColumn('Opportunities.opportunity_id', 'Quotations.opportunity_id')
                    ->limit(1)
            )->orderBy('Quotations.quotation_id'),
            default      => $query->orderByDesc('Quotations.created_at')->orderByDesc('Quotations.quotation_id'),
        };
    }

    private function appliedFilters(): array
    {
        $filters = [];
        foreach (array_keys(self::FILTER_DEFAULTS) as $property) {
            $filters[$property] = $this->{$property};
        }

        return $filters;
    }

    private function sanitizeFilters(): void
    {
        $text = fn ($value, string $default) => is_string($value) ? $value : $default;

        $this->search = $text($this->search, '');
        $this->sort = $text($this->sort, 'newest');

        $form = is_array($this->form) ? $this->form : [];

        foreach (self::FILTER_DEFAULTS as $property => $default) {
            if (is_array($default)) {
                $this->{$property} = $this->stringList($this->{$property});
                $form[$property] = $this->stringList($form[$property] ?? []);
            } else {
                $this->{$property} = $text($this->{$property}, $default);
                $form[$property] = $text($form[$property] ?? $default, $default);
            }
        }

        $this->form = array_intersect_key($form, self::FILTER_DEFAULTS);
    }

    private function findTeam(string $teamId): ?SalesTeam
    {
        return $teamId === '' ? null : $this->teams->firstWhere('team_id', $teamId);
    }

    private function employeesFor(?SalesTeam $team): Collection
    {
        if (! $this->canFilterEmployee) {
            return collect();
        }

        return $this->employeesCache[$team?->team_id ?? ''] ??= Employee::query()
            ->whereHas('quotations', fn ($q) => $q->visibleTo($this->viewer()))
            ->when($team, fn ($q) => $q->where('team_id', $team->team_id))
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name']);
    }

    private function foldColumn(string $column): string
    {
        return "REPLACE(REPLACE($column, 'đ', 'd'), 'Đ', 'D')";
    }

    private static function fold(string $text): string
    {
        static $map = null;

        if ($map === null) {
            $map = [];
            $groups = [
                'a' => 'àáạảãâầấậẩẫăằắặẳẵ',
                'e' => 'èéẹẻẽêềếệểễ',
                'i' => 'ìíịỉĩ',
                'o' => 'òóọỏõôồốộổỗơờớợởỡ',
                'u' => 'ùúụủũưừứựửữ',
                'y' => 'ỳýỵỷỹ',
                'd' => 'đ',
            ];
            foreach ($groups as $base => $chars) {
                foreach (mb_str_split($chars) as $char) {
                    $map[$char] = $base;
                }
            }
        }

        return strtr(mb_strtolower($text), $map);
    }

    private function stringList(mixed $value): array
    {
        return array_values(array_filter(is_array($value) ? $value : [$value], 'is_string'));
    }

    private function rangeText(?string $from, ?string $to): string
    {
        return match (true) {
            $from !== null && $to !== null => __('quotations.chip.range', ['from' => $from, 'to' => $to]),
            $from !== null                 => __('quotations.chip.from', ['value' => $from]),
            default                        => __('quotations.chip.to', ['value' => $to]),
        };
    }

    private function dateRange(string $from, string $to): array
    {
        $from = $this->validDate($from) ? $from : null;
        $to = $this->validDate($to) ? $to : null;

        if ($from !== null && $to !== null && $to < $from) {
            return [null, null, true];
        }

        return [$from, $to, false];
    }

    private function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }
}
