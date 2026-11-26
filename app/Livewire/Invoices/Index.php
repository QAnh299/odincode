<?php

namespace App\Livewire\Invoices;

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesTeam;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public const SORTS = ['newest', 'oldest', 'due', 'amount_desc', 'amount_asc', 'code', 'customer'];

    public const DEADLINES = ['valid', 'soon', 'overdue'];

    public const SOON_DAYS = 3;

    public const METHOD_NONE = 'none';

    public const MAX_TERMS = 8;
    public const MAX_TERM_LENGTH = 100;

    public const MIN_AMOUNT_DIGITS = 3;
    public const MAX_AMOUNT_DIGITS = 15;

    public const FILTER_DEFAULTS = [
        'statuses'    => [],
        'methods'     => [],
        'deadline'    => '',
        'paymentType' => '',
        'amountFrom'  => '',
        'amountTo'    => '',
        'issuedFrom'  => '',
        'issuedTo'    => '',
        'dueFrom'     => '',
        'dueTo'       => '',
        'paidFrom'    => '',
        'paidTo'      => '',
        'team'        => '',
        'employee'    => '',
        'cashPending' => false,
    ];

    #[Url(as: 'q', except: '')]
    public $search = '';

    #[Url(as: 'status')]
    public $statuses = [];

    #[Url(as: 'method')]
    public $methods = [];

    #[Url(as: 'deadline', except: '')]
    public $deadline = '';

    #[Url(as: 'payment', except: '')]
    public $paymentType = '';

    #[Url(as: 'amount_from', except: '')]
    public $amountFrom = '';

    #[Url(as: 'amount_to', except: '')]
    public $amountTo = '';

    #[Url(as: 'issued_from', except: '')]
    public $issuedFrom = '';

    #[Url(as: 'issued_to', except: '')]
    public $issuedTo = '';

    #[Url(as: 'due_from', except: '')]
    public $dueFrom = '';

    #[Url(as: 'due_to', except: '')]
    public $dueTo = '';

    #[Url(as: 'paid_from', except: '')]
    public $paidFrom = '';

    #[Url(as: 'paid_to', except: '')]
    public $paidTo = '';

    #[Url(except: '')]
    public $team = '';

    #[Url(except: '')]
    public $employee = '';

    #[Url(as: 'cash_pending', except: false)]
    public $cashPending = false;

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

        [, , $fromInvalid, $toInvalid, $rangeInvalid] = $this->amountRangeOf($this->form['amountFrom'], $this->form['amountTo']);

        if ($fromInvalid) {
            $this->addError('form.amountFrom', __('invoices.invalid_amount'));
        }

        if ($toInvalid) {
            $this->addError('form.amountTo', __('invoices.invalid_amount'));
        }

        if ($rangeInvalid) {
            $this->addError('form.amountTo', __('invoices.invalid_amount_range'));
        }

        foreach ([['issuedFrom', 'issuedTo'], ['dueFrom', 'dueTo'], ['paidFrom', 'paidTo']] as [$from, $to]) {
            if ($this->dateRange($this->form[$from], $this->form[$to])[2]) {
                $this->addError('form.'.$to, __('invoices.invalid_range'));
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        foreach (self::FILTER_DEFAULTS as $property => $default) {
            $this->{$property} = $this->form[$property];
        }

        $this->resetPage();

        $this->dispatch('invoice-filters-applied');
    }

    public function resetForm(): void
    {
        $this->form = self::FILTER_DEFAULTS;
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
            'search'       => ['search'],
            'statuses'     => ['statuses'],
            'methods'      => ['methods'],
            'deadline'     => ['deadline'],
            'payment_type' => ['paymentType'],
            'amount'       => ['amountFrom', 'amountTo'],
            'issued'       => ['issuedFrom', 'issuedTo'],
            'due'          => ['dueFrom', 'dueTo'],
            'paid'         => ['paidFrom', 'paidTo'],
            'team'         => ['team'],
            'employee'     => ['employee'],
            'cash_pending' => ['cashPending'],
            default        => [],
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
            ? SalesTeam::query()
                ->whereHas('employees.invoicesCreated', fn ($q) => $q->visibleTo($this->viewer()))
                ->orderBy('team_name')
                ->get(['team_id', 'team_name'])
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
        return array_values(array_intersect(Invoice::STATUSES, $this->stringList($this->statuses)));
    }

    public function activeMethods(): array
    {
        return array_values(array_intersect([...Invoice::METHODS, self::METHOD_NONE], $this->stringList($this->methods)));
    }

    public function activeDeadline(): ?string
    {
        return in_array($this->deadline, self::DEADLINES, true) ? $this->deadline : null;
    }

    public function activePaymentType(): ?string
    {
        return in_array($this->paymentType, SalesOrder::PAYMENT_TYPES, true) ? $this->paymentType : null;
    }

    public function activeSort(): string
    {
        return in_array($this->sort, self::SORTS, true) ? $this->sort : 'newest';
    }

    #[Computed]
    public function amountRange(): array
    {
        return $this->amountRangeOf($this->amountFrom, $this->amountTo);
    }

    #[Computed]
    public function issuedRange(): array
    {
        return $this->dateRange($this->issuedFrom, $this->issuedTo);
    }

    #[Computed]
    public function dueRange(): array
    {
        return $this->dateRange($this->dueFrom, $this->dueTo);
    }

    #[Computed]
    public function paidRange(): array
    {
        return $this->dateRange($this->paidFrom, $this->paidTo);
    }

    #[Computed]
    public function terms(): array
    {
        return $this->parseTerms($this->search);
    }

    #[Computed]
    public function summary(): array
    {
        $rows = $this->filteredQuery(withStatus: false)
            ->selectRaw('Invoices.status, COUNT(*) AS total, COALESCE(SUM(Invoices.payment_amount), 0) AS amount')
            ->groupBy('Invoices.status')
            ->get()
            ->keyBy('status');

        $counts = ['all' => (int) $rows->sum('total')];

        foreach (Invoice::STATUSES as $status) {
            $counts[$status] = (int) ($rows[$status]->total ?? 0);
        }

        $amount = fn (string $status) => (float) ($rows[$status]->amount ?? 0);

        return [
            'counts'    => $counts,
            'collected' => $amount(Invoice::STATUS_PAID),
            'pending'   => $amount(Invoice::STATUS_UNPAID) + $amount(Invoice::STATUS_OVERDUE),
        ];
    }

    #[Computed]
    public function hasAnyInvoice(): bool
    {
        return $this->scopedQuery()->exists();
    }

    #[Computed]
    public function chips(): array
    {
        $chips = [];
        $label = fn (string $group, string $value) => __('invoices.chip.'.$group).': '.$value;
        $list = fn (array $values, string $prefix) => collect($values)->map(fn ($v) => __($prefix.$v))->implode(', ');

        if ($this->terms !== []) {
            $chips['search'] = $label('search', trim($this->search));
        }

        if ($statuses = $this->activeStatuses()) {
            $chips['statuses'] = $label('statuses', $list($statuses, 'invoices.status.'));
        }

        if ($methods = $this->activeMethods()) {
            $chips['methods'] = $label('methods', $list($methods, 'invoices.method.'));
        }

        if ($deadline = $this->activeDeadline()) {
            $chips['deadline'] = $label('deadline', __('invoices.deadline.'.$deadline));
        }

        if ($paymentType = $this->activePaymentType()) {
            $chips['payment_type'] = $label('payment_type', __('invoices.payment_type.'.$paymentType));
        }

        [$amountFrom, $amountTo] = $this->amountRange;
        if ($amountFrom !== null || $amountTo !== null) {
            $number = fn (?int $value) => $value === null ? null : number_format($value, 0, ',', '.');
            $chips['amount'] = $label('amount', $this->rangeText($number($amountFrom), $number($amountTo)).' ₫');
        }

        $date = fn (?string $value) => $value === null ? null : Carbon::parse($value)->format('d/m/Y');

        foreach (['issued' => $this->issuedRange, 'due' => $this->dueRange, 'paid' => $this->paidRange] as $key => [$from, $to]) {
            if ($from !== null || $to !== null) {
                $chips[$key] = $label($key, $this->rangeText($date($from), $date($to)));
            }
        }

        if ($this->activeTeam) {
            $chips['team'] = $label('team', $this->activeTeam->team_name);
        }

        if ($this->activeEmployee) {
            $chips['employee'] = $label('employee', $this->activeEmployee->full_name);
        }

        if ($this->cashPending) {
            $chips['cash_pending'] = __('invoices.cash_pending');
        }

        return $chips;
    }

    public function hasPendingChanges(): bool
    {
        $normalize = function (array $filters): array {
            $filters['statuses'] = array_values(array_intersect(Invoice::STATUSES, $this->stringList($filters['statuses'] ?? [])));
            $filters['methods'] = array_values(array_intersect([...Invoice::METHODS, self::METHOD_NONE], $this->stringList($filters['methods'] ?? [])));

            return $filters;
        };

        return $normalize($this->form) != $normalize($this->appliedFilters());
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
                .'<mark class="iv-mark">'.e(mb_substr($text, $start, $end - $start)).'</mark>';
            $cursor = $end;
        }

        return new HtmlString($html.e(mb_substr($text, $cursor)));
    }

    public function render()
    {
        $this->sanitizeFilters();

        $invoices = $this->filteredQuery(withStatus: true)
            ->with([
                'order:order_id,quotation_id,payment_type',
                'order.quotation:quotation_id,opportunity_id',
                'order.quotation.opportunity:opportunity_id,lead_id',
                'order.quotation.opportunity.lead:lead_id,full_name',
                'createdBy:employee_id,full_name',
                'accountant:employee_id,full_name',
            ])
            ->tap(fn ($q) => $this->applySort($q))
            ->paginate(self::PER_PAGE);

        return view('livewire.invoices.index', [
            'invoices' => $invoices,
        ])->title(__('invoices.title'));
    }

    private function viewer(): ?Employee
    {
        return Auth::user()->employee;
    }

    private function scopedQuery(): Builder
    {
        return Invoice::query()->visibleTo($this->viewer());
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
            $codes = [
                'statuses' => $this->matchingCodes($term['text'], Invoice::STATUSES, 'invoices.status.'),
                'methods'  => $this->matchingCodes($term['text'], Invoice::METHODS, 'invoices.method.'),
                'payments' => $this->matchingCodes($term['text'], SalesOrder::PAYMENT_TYPES, 'invoices.payment_type.'),
            ];
            $amount = $this->searchAmount($term['text']);
            $match = fn (Builder $q) => $this->matchTerm($q, $like, $codes, $amount);

            $term['exclude'] ? $query->whereNot($match) : $query->where($match);
        }
    }

    private function matchingCodes(string $text, array $codes, string $langPrefix): array
    {
        $needle = self::fold($text);

        if (mb_strlen($needle) < 3) {
            return [];
        }

        $pattern = '/(^|\s)'.preg_quote($needle, '/').'/u';

        return array_values(array_filter($codes, fn ($code) => collect([
            $code,
            __($langPrefix.$code, [], 'vi'),
            __($langPrefix.$code, [], 'en'),
        ])->contains(fn ($label) => preg_match($pattern, self::fold($label)) === 1)));
    }

    private function searchAmount(string $text): ?string
    {
        if (! preg_match('/^\d+([.,]\d+)*$/', $text)) {
            return null;
        }

        $digits = ltrim(str_replace(['.', ','], '', $text), '0');

        return strlen($digits) >= self::MIN_AMOUNT_DIGITS && strlen($digits) <= self::MAX_AMOUNT_DIGITS ? $digits : null;
    }

    private function matchTerm(Builder $query, string $like, array $codes, ?string $amount): void
    {
        $query
            ->where('Invoices.invoice_id', 'like', $like)
            ->when($codes['statuses'] !== [], fn ($q) => $q->orWhereIn('Invoices.status', $codes['statuses']))
            ->when($codes['methods'] !== [], fn ($q) => $q->orWhereIn(
                DB::raw("COALESCE(Invoices.payment_method, '')"),
                $codes['methods']
            ))
            ->when($amount !== null, fn ($q) => $q->orWhere('Invoices.payment_amount', $amount))
            ->orWhere('Invoices.order_id', 'like', $like)
            ->orWhere('Invoices.created_by_employee_id', 'like', $like)
            ->orWhereRaw("COALESCE(Invoices.accountant_id, '') LIKE ?", [$like])
            ->orWhereHas('order', fn ($q) => $q
                ->where('SalesOrders.quotation_id', 'like', $like)
                ->when($codes['payments'] !== [], fn ($q) => $q->orWhereIn('SalesOrders.payment_type', $codes['payments'])))
            ->orWhereHas('order.quotation', fn ($q) => $q->where('Quotations.opportunity_id', 'like', $like))
            ->orWhereHas('order.quotation.opportunity.lead', fn ($q) => $q
                ->whereRaw($this->foldColumn('Leads.full_name').' LIKE ?', [$like])
                ->orWhere('Leads.email', 'like', $like)
                ->orWhere('Leads.phone', 'like', $like))
            ->orWhereHas('createdBy', fn ($q) => $q->whereRaw($this->foldColumn('Employees.full_name').' LIKE ?', [$like]))
            ->orWhereHas('accountant', fn ($q) => $q->whereRaw($this->foldColumn('Employees.full_name').' LIKE ?', [$like]))
            ->orWhereHas('order.quotation.details', fn ($q) => $q
                ->where('QuotationDetails.course_id', 'like', $like)
                ->orWhereHas('course', fn ($q) => $q->whereRaw($this->foldColumn('Courses.course_name').' LIKE ?', [$like])));
    }

    private function applyFilterConditions(Builder $query, bool $withStatus): void
    {
        if ($withStatus && ($statuses = $this->activeStatuses())) {
            $query->whereIn('Invoices.status', $statuses);
        }

        $this->applyMethods($query);
        $this->applyDeadline($query);

        if ($paymentType = $this->activePaymentType()) {
            $query->whereHas('order', fn ($q) => $q->where('SalesOrders.payment_type', $paymentType));
        }

        [$amountFrom, $amountTo] = $this->amountRange;
        $query
            ->when($amountFrom !== null, fn ($q) => $q->where('Invoices.payment_amount', '>=', $amountFrom))
            ->when($amountTo !== null, fn ($q) => $q->where('Invoices.payment_amount', '<=', $amountTo));

        $this->applyDates($query);

        if ($this->activeTeam) {
            $query->whereHas('createdBy', fn ($q) => $q->where('Employees.team_id', $this->activeTeam->team_id));
        }

        if ($this->activeEmployee) {
            $query->where('Invoices.created_by_employee_id', $this->activeEmployee->employee_id);
        }

        if ($this->cashPending) {
            $query->where('Invoices.status', Invoice::STATUS_UNPAID)
                ->where('Invoices.payment_method', Invoice::METHOD_CASH);
        }
    }

    private function applyMethods(Builder $query): void
    {
        $methods = $this->activeMethods();
        if ($methods === []) {
            return;
        }

        $codes = array_values(array_diff($methods, [self::METHOD_NONE]));
        $withNone = in_array(self::METHOD_NONE, $methods, true);

        $query->where(fn ($q) => $q
            ->when($codes !== [], fn ($q) => $q->whereIn('Invoices.payment_method', $codes))
            ->when($withNone, fn ($q) => $q->orWhereNull('Invoices.payment_method')));
    }

    private function applyDeadline(Builder $query): void
    {
        $deadline = $this->activeDeadline();
        if ($deadline === null) {
            return;
        }

        $today = today()->toDateString();

        match ($deadline) {
            'valid'   => $query->where('Invoices.status', Invoice::STATUS_UNPAID)
                ->whereDate('Invoices.due_date', '>=', $today),
            'soon'    => $query->where('Invoices.status', Invoice::STATUS_UNPAID)
                ->whereBetween('Invoices.due_date', [$today, today()->addDays(self::SOON_DAYS)->toDateString()]),
            'overdue' => $query->whereIn('Invoices.status', [Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE])
                ->where(fn ($q) => $q->where('Invoices.status', Invoice::STATUS_OVERDUE)
                    ->orWhereDate('Invoices.due_date', '<', $today)),
        };
    }

    private function applyDates(Builder $query): void
    {
        [$issuedFrom, $issuedTo] = $this->issuedRange;
        [$dueFrom, $dueTo] = $this->dueRange;
        [$paidFrom, $paidTo] = $this->paidRange;

        $query
            ->when($issuedFrom, fn ($q, $date) => $q->whereDate('Invoices.issued_date', '>=', $date))
            ->when($issuedTo, fn ($q, $date) => $q->whereDate('Invoices.issued_date', '<=', $date))
            ->when($dueFrom, fn ($q, $date) => $q->whereDate('Invoices.due_date', '>=', $date))
            ->when($dueTo, fn ($q, $date) => $q->whereDate('Invoices.due_date', '<=', $date))
            ->when($paidFrom || $paidTo, fn ($q) => $q->whereNotNull('Invoices.payment_date'))
            ->when($paidFrom, fn ($q, $date) => $q->whereDate('Invoices.payment_date', '>=', $date))
            ->when($paidTo, fn ($q, $date) => $q->whereDate('Invoices.payment_date', '<=', $date));
    }

    private function applySort(Builder $query): void
    {
        match ($this->activeSort()) {
            'oldest'      => $query->orderBy('Invoices.issued_date')->orderBy('Invoices.invoice_id'),
            'due'         => $query->orderByRaw('CASE WHEN Invoices.status = ? THEN 1 ELSE 0 END', [Invoice::STATUS_PAID])
                ->orderBy('Invoices.due_date')->orderBy('Invoices.invoice_id'),
            'amount_desc' => $query->orderByDesc('Invoices.payment_amount')->orderBy('Invoices.invoice_id'),
            'amount_asc'  => $query->orderBy('Invoices.payment_amount')->orderBy('Invoices.invoice_id'),
            'code'        => $query->orderBy('Invoices.invoice_id'),
            'customer'    => $query->orderBy(
                Lead::query()->select('Leads.full_name')
                    ->join('Opportunities', 'Opportunities.lead_id', '=', 'Leads.lead_id')
                    ->join('Quotations', 'Quotations.opportunity_id', '=', 'Opportunities.opportunity_id')
                    ->join('SalesOrders', 'SalesOrders.quotation_id', '=', 'Quotations.quotation_id')
                    ->whereColumn('SalesOrders.order_id', 'Invoices.order_id')
                    ->limit(1)
            )->orderBy('Invoices.invoice_id'),
            default       => $query->orderByDesc('Invoices.issued_date')->orderByDesc('Invoices.invoice_id'),
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
        $text = fn ($value, string $default) => match (true) {
            is_string($value)                   => $value,
            is_int($value) || is_float($value) => (string) $value,
            default                             => $default,
        };
        $flag = fn ($value) => is_scalar($value) && filter_var($value, FILTER_VALIDATE_BOOLEAN);

        $this->search = $text($this->search, '');
        $this->sort = $text($this->sort, 'newest');

        $form = is_array($this->form) ? $this->form : [];

        foreach (self::FILTER_DEFAULTS as $property => $default) {
            if (is_array($default)) {
                $this->{$property} = $this->stringList($this->{$property});
                $form[$property] = $this->stringList($form[$property] ?? []);
            } elseif (is_bool($default)) {
                $this->{$property} = $flag($this->{$property});
                $form[$property] = $flag($form[$property] ?? $default);
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

        $viewer = $this->viewer();

        return $this->employeesCache[$team?->team_id ?? ''] ??= Employee::query()
            ->whereHas('invoicesCreated', fn ($q) => $q->visibleTo($viewer))
            ->when($this->routePrefix === Role::SALE_LEADER, fn ($q) => $q->where('team_id', $viewer?->team_id))
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
            $from !== null && $to !== null => __('invoices.chip.range', ['from' => $from, 'to' => $to]),
            $from !== null                 => __('invoices.chip.from', ['value' => $from]),
            default                        => __('invoices.chip.to', ['value' => $to]),
        };
    }

    private function amountRangeOf(string $from, string $to): array
    {
        $fromValue = $this->parseAmount($from);
        $toValue = $this->parseAmount($to);
        $fromInvalid = $fromValue === false;
        $toInvalid = $toValue === false;
        $fromValue = $fromInvalid ? null : $fromValue;
        $toValue = $toInvalid ? null : $toValue;

        if ($fromValue !== null && $toValue !== null && $toValue < $fromValue) {
            return [null, null, $fromInvalid, $toInvalid, true];
        }

        return [$fromValue, $toValue, $fromInvalid, $toInvalid, false];
    }

    private function parseAmount(string $value): int|false|null
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\d+([., ]\d+)*$/', $value)) {
            return false;
        }

        $digits = ltrim(str_replace(['.', ',', ' '], '', $value), '0');

        return strlen($digits) > self::MAX_AMOUNT_DIGITS ? false : (int) $digits;
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
        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
