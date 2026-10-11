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

/**
 * Danh sách hóa đơn + tìm kiếm nâng cao + bộ lọc. Chỉ xem: tạo hóa đơn (từ đơn hàng, HĐ18), sửa,
 * QR, duyệt tiền mặt, ghi nhận thanh toán làm ở bước sau. Quy trình: docs/quy-trinh-bao-gia-thanh-toan.md.
 * Dùng chung cho Giám đốc, Sale Admin, Sale Leader, Salesperson, Kế toán.
 *
 * Phạm vi dữ liệu luôn theo Invoice::visibleTo() (theo báo giá của đơn hàng): Giám đốc / Sale Admin /
 * Kế toán xem tất cả, Sale Leader xem của đội mình, Salesperson xem của chính mình.
 *
 * Tìm kiếm (cập nhật ngay): nhiều từ khóa (AND), mỗi từ khóa khớp ít nhất một trường (OR);
 * "cụm từ" giữ nguyên; -từ khóa để loại trừ; không phân biệt hoa/thường và dấu tiếng Việt.
 *
 * Bộ lọc (bấm "Áp dụng" mới áp dụng): người dùng chọn trên $form; applyFilters() mới chép sang
 * các thuộc tính đã áp dụng (lưu trên URL) và danh sách chỉ đọc các thuộc tính đã áp dụng.
 *
 * Nhãn "còn N ngày" / "trễ N ngày" chỉ tính ra từ due_date, không đổi dữ liệu
 * (chưa có bước chuyển Unpaid → Overdue, xem mục 0.3 của quy trình).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    const PER_PAGE = 10;

    const SORTS = ['newest', 'oldest', 'due', 'amount_desc', 'amount_asc', 'code', 'customer'];

    // Tình trạng hạn (chỉ xét hóa đơn chưa thanh toán: Unpaid + Overdue)
    const DEADLINES = ['valid', 'soon', 'overdue'];

    // "Sắp đến hạn": còn 0 – SOON_DAYS ngày
    const SOON_DAYS = 3;

    // Giá trị bộ lọc phương thức cho hóa đơn chưa chọn phương thức (payment_method IS NULL)
    const METHOD_NONE = 'none';

    // Giới hạn tìm kiếm: số từ khóa, độ dài mỗi từ khóa (phần vượt quá bỏ qua)
    const MAX_TERMS = 8;
    const MAX_TERM_LENGTH = 100;

    // Từ khóa số tiền: ít nhất 3 chữ số; ô số tiền của bộ lọc: tối đa 15 chữ số (DECIMAL(18,2))
    const MIN_AMOUNT_DIGITS = 3;
    const MAX_AMOUNT_DIGITS = 15;

    // Giá trị mặc định của các bộ lọc (key của $form = tên thuộc tính đã áp dụng)
    const FILTER_DEFAULTS = [
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

    // Các giá trị có thể bị sửa từ client → luôn đọc qua các hàm active*() / *Range đã kiểm tra lại.
    // Không khai báo kiểu: URL kiểu ?q[]=x sẽ gán mảng và gây lỗi 500 trước khi kịp kiểm tra;
    // giá trị sai kiểu được đưa về mặc định ở sanitizeFilters().

    #[Url(as: 'q', except: '')]
    public $search = '';

    // ── Bộ lọc ĐÃ ÁP DỤNG (danh sách đọc các giá trị này) ──

    // Trạng thái (chọn nhiều, thuộc Invoice::STATUSES)
    #[Url(as: 'status')]
    public $statuses = [];

    // Phương thức (chọn nhiều, thuộc Invoice::METHODS hoặc METHOD_NONE)
    #[Url(as: 'method')]
    public $methods = [];

    // Tình trạng hạn: '' hoặc một trong DEADLINES
    #[Url(as: 'deadline', except: '')]
    public $deadline = '';

    // Hình thức thanh toán của đơn: '' hoặc một trong SalesOrder::PAYMENT_TYPES
    #[Url(as: 'payment', except: '')]
    public $paymentType = '';

    // Khoảng số tiền (VNĐ)
    #[Url(as: 'amount_from', except: '')]
    public $amountFrom = '';

    #[Url(as: 'amount_to', except: '')]
    public $amountTo = '';

    // Khoảng ngày lập
    #[Url(as: 'issued_from', except: '')]
    public $issuedFrom = '';

    #[Url(as: 'issued_to', except: '')]
    public $issuedTo = '';

    // Khoảng hạn thanh toán
    #[Url(as: 'due_from', except: '')]
    public $dueFrom = '';

    #[Url(as: 'due_to', except: '')]
    public $dueTo = '';

    // Khoảng ngày thanh toán (chỉ hóa đơn có payment_date)
    #[Url(as: 'paid_from', except: '')]
    public $paidFrom = '';

    #[Url(as: 'paid_to', except: '')]
    public $paidTo = '';

    // Đội của người lập (Giám đốc, Sale Admin, Kế toán), '' = Tất cả
    #[Url(except: '')]
    public $team = '';

    // Nhân viên lập (Giám đốc, Sale Admin, Kế toán, Sale Leader), '' = Tất cả
    #[Url(except: '')]
    public $employee = '';

    // Tiền mặt chờ kế toán duyệt (Unpaid + Cash, HĐ30 – HĐ32)
    #[Url(as: 'cash_pending', except: false)]
    public $cashPending = false;

    #[Url(except: 'newest')]
    public $sort = 'newest';

    // ── Bộ lọc ĐANG CHỌN trên form (chưa áp dụng cho tới khi bấm "Áp dụng") ──
    public array $form = self::FILTER_DEFAULTS;

    // Danh sách nhân viên theo đội (tránh truy vấn lặp trong một request)
    private array $employeesCache = [];

    public function mount(): void
    {
        $this->sanitizeFilters();
        $this->form = $this->appliedFilters();
    }

    /**
     * Ô tìm kiếm / sắp xếp áp dụng ngay nên về trang 1; đổi bộ lọc trên form thì chưa đổi danh sách.
     * Đổi đội trên form thì bỏ chọn nhân viên không còn thuộc đội đó.
     */
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

    /**
     * Nút "Áp dụng": kiểm tra số tiền và các khoảng ngày trên form; hợp lệ thì áp dụng toàn bộ bộ lọc và về trang 1.
     */
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

        // Giao diện: áp dụng thành công thì đóng panel bộ lọc
        $this->dispatch('invoice-filters-applied');
    }

    /**
     * Nút "Đặt lại" trong panel: đưa các ô của panel về mặc định, CHƯA áp dụng (bấm "Áp dụng" mới đổi danh sách).
     */
    public function resetForm(): void
    {
        $this->form = self::FILTER_DEFAULTS;
        $this->resetErrorBag();
    }

    /**
     * Bấm vào thẻ thống kê: lọc ngay đúng một trạng thái, bấm lại thì bỏ lọc ('' = Tất cả).
     * Ô Trạng thái trên form cũng đổi theo.
     */
    public function filterStatus(string $status): void
    {
        $this->statuses = $status === '' || $this->activeStatuses() === [$status] ? [] : [$status];
        $this->form['statuses'] = $this->statuses;
        $this->resetPage();
    }

    /**
     * Nút × trên chip: bỏ riêng một nhóm bộ lọc (cả giá trị đã áp dụng lẫn trên form).
     */
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

    /**
     * Xóa tất cả: từ khóa, bộ lọc (đã áp dụng và trên form) và đưa sắp xếp về mặc định.
     */
    public function resetFilters(): void
    {
        $this->reset('search', 'sort', ...array_keys(self::FILTER_DEFAULTS));
        $this->form = self::FILTER_DEFAULTS;
        $this->resetErrorBag();
        $this->resetPage();
    }

    // ── Quyền và danh sách lựa chọn của bộ lọc ─────────────────────────

    /**
     * Prefix route theo vai trò, VD: 'director' → route 'director.invoices'.
     */
    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Có bộ lọc Đội (Giám đốc, Sale Admin, Kế toán – các vai trò xem toàn bộ hóa đơn).
     */
    #[Computed]
    public function canFilterTeam(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN, Role::ACCOUNTANT], true);
    }

    /**
     * Có bộ lọc và cột Nhân viên lập (Salesperson chỉ thấy hóa đơn của mình nên ẩn).
     */
    #[Computed]
    public function canFilterEmployee(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN, Role::ACCOUNTANT, Role::SALE_LEADER], true);
    }

    /**
     * Đội có người lập hóa đơn trong phạm vi quyền.
     */
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

    /**
     * Đội đã áp dụng: phải có trong danh sách đội, giá trị lạ (sửa URL) coi như Tất cả.
     */
    #[Computed]
    public function activeTeam(): ?SalesTeam
    {
        return $this->findTeam($this->team);
    }

    /**
     * Nhân viên cho ô chọn trên form: thu hẹp theo đội đang chọn trên form.
     */
    #[Computed]
    public function formEmployeeOptions(): Collection
    {
        return $this->employeesFor($this->findTeam($this->form['team']));
    }

    /**
     * Nhân viên đã áp dụng: phải thuộc danh sách được phép của đội đã áp dụng, giá trị lạ coi như Tất cả.
     */
    #[Computed]
    public function activeEmployee(): ?Employee
    {
        return $this->employee === '' ? null : $this->employeesFor($this->activeTeam)->firstWhere('employee_id', $this->employee);
    }

    // ── Giá trị bộ lọc đã kiểm tra phía server ─────────────────────────

    /**
     * Trạng thái đang lọc (bỏ giá trị lạ), theo thứ tự của Invoice::STATUSES.
     *
     * @return array<int, string>
     */
    public function activeStatuses(): array
    {
        return array_values(array_intersect(Invoice::STATUSES, $this->stringList($this->statuses)));
    }

    /**
     * Phương thức đang lọc (bỏ giá trị lạ), theo thứ tự Invoice::METHODS rồi METHOD_NONE.
     *
     * @return array<int, string>
     */
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

    /**
     * Khoảng số tiền đã áp dụng: [from, to, from sai?, to sai?, khoảng sai?].
     */
    #[Computed]
    public function amountRange(): array
    {
        return $this->amountRangeOf($this->amountFrom, $this->amountTo);
    }

    /**
     * Khoảng ngày lập đã áp dụng: [from, to, có lỗi?].
     */
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

    /**
     * Từ khóa tìm kiếm đã phân tích (xem parseTerms).
     *
     * @return array<int, array{text: string, needle: string, exclude: bool}>
     */
    #[Computed]
    public function terms(): array
    {
        return $this->parseTerms($this->search);
    }

    // ── Số liệu hiển thị ───────────────────────────────────────────────

    /**
     * Số hóa đơn và tổng tiền theo từng trạng thái (thẻ thống kê + dải tổng tiền), một truy vấn GROUP BY:
     * áp mọi điều kiện tìm kiếm / lọc đã áp dụng TRỪ trạng thái.
     * Đã thu = tổng hóa đơn Paid; Chờ thu = tổng hóa đơn Unpaid + Overdue.
     */
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

    /**
     * Có hóa đơn nào trong phạm vi quyền không (phân biệt 2 trạng thái rỗng).
     */
    #[Computed]
    public function hasAnyInvoice(): bool
    {
        return $this->scopedQuery()->exists();
    }

    /**
     * Chip của các bộ lọc ĐÃ áp dụng: [key => nhãn]. Key dùng cho clearFilter().
     *
     * @return array<string, string>
     */
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

    /**
     * Form có thay đổi chưa bấm "Áp dụng" (hiện nhắc người dùng).
     */
    public function hasPendingChanges(): bool
    {
        $normalize = function (array $filters): array {
            $filters['statuses'] = array_values(array_intersect(Invoice::STATUSES, $this->stringList($filters['statuses'] ?? [])));
            $filters['methods'] = array_values(array_intersect([...Invoice::METHODS, self::METHOD_NONE], $this->stringList($filters['methods'] ?? [])));

            return $filters;
        };

        return $normalize($this->form) != $normalize($this->appliedFilters());
    }

    /**
     * Đánh dấu <mark> các từ khóa (không loại trừ) trong một ô của bảng.
     * Escape HTML trước, so khớp không phân biệt hoa/thường và dấu tiếng Việt.
     */
    public function highlight(?string $text): HtmlString
    {
        $text = (string) $text;
        $needles = collect($this->terms)->reject(fn ($t) => $t['exclude'])->map(fn ($t) => self::fold($t['text']))->filter()->all();

        if ($text === '' || $needles === []) {
            return new HtmlString(e($text));
        }

        // fold() giữ nguyên số ký tự nên vị trí trong chuỗi đã bỏ dấu dùng được cho chuỗi gốc
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

        // Gộp các đoạn chồng lên nhau rồi ghép lại
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

    // ── Truy vấn ───────────────────────────────────────────────────────

    private function viewer(): ?Employee
    {
        return Auth::user()->employee;
    }

    /**
     * Hóa đơn trong phạm vi quyền của người đang đăng nhập (mọi truy vấn đều bắt đầu từ đây).
     */
    private function scopedQuery(): Builder
    {
        return Invoice::query()->visibleTo($this->viewer());
    }

    /**
     * Phạm vi quyền + từ khóa + mọi bộ lọc đã áp dụng. $withStatus = false dùng cho thẻ thống kê và tổng tiền.
     */
    private function filteredQuery(bool $withStatus): Builder
    {
        $query = $this->scopedQuery();

        $this->applySearch($query);
        $this->applyFilterConditions($query, $withStatus);

        return $query;
    }

    /**
     * Phân tích chuỗi tìm kiếm thành các từ khóa:
     *   - tách theo khoảng trắng; "cụm từ" trong ngoặc kép giữ nguyên (ngoặc kép lẻ thì coi như văn bản thường);
     *   - từ khóa bắt đầu bằng "-" là loại trừ (VD: -INV001, -"Nguyễn Văn A");
     *   - tối đa MAX_TERMS từ khóa, mỗi từ khóa tối đa MAX_TERM_LENGTH ký tự.
     * needle là từ khóa đã đổi đ/Đ → d/D để so với cột cũng đã đổi (xem foldColumn).
     *
     * @return array<int, array{text: string, needle: string, exclude: bool}>
     */
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

    /**
     * Áp từ khóa: mọi từ khóa phải khớp (AND); từ khóa loại trừ thì hóa đơn khớp bị bỏ.
     */
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

    /**
     * Mã (trạng thái, phương thức, hình thức) có mã hoặc nhãn (vi / en) bắt đầu một từ bằng từ khóa,
     * VD "paid", "cho thanh", "chuyen khoan", "tra gop" (so khớp bỏ dấu). Từ khóa dưới 3 ký tự không xét,
     * tránh "a", "da"… khớp mọi giá trị.
     *
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
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

    /**
     * Từ khóa là số tiền: chỉ gồm chữ số, có thể có dấu chấm / phẩy ngăn cách nghìn, ít nhất MIN_AMOUNT_DIGITS chữ số.
     * VD "5400000" hoặc "5.400.000" → "5400000"; không phải số tiền → null.
     */
    private function searchAmount(string $text): ?string
    {
        if (! preg_match('/^\d+([.,]\d+)*$/', $text)) {
            return null;
        }

        $digits = ltrim(str_replace(['.', ','], '', $text), '0');

        return strlen($digits) >= self::MIN_AMOUNT_DIGITS && strlen($digits) <= self::MAX_AMOUNT_DIGITS ? $digits : null;
    }

    /**
     * Một từ khóa khớp ít nhất một trường (OR). Dùng EXISTS (whereHas), không join nhân bản dòng.
     * Cột có thể NULL bọc COALESCE để NOT (...) của từ khóa loại trừ không bỏ sót dòng.
     *
     * @param  array{statuses: array<int, string>, methods: array<int, string>, payments: array<int, string>}  $codes
     */
    private function matchTerm(Builder $query, string $like, array $codes, ?string $amount): void
    {
        $query
            ->where('Invoices.invoice_id', 'like', $like)
            ->when($codes['statuses'] !== [], fn ($q) => $q->orWhereIn('Invoices.status', $codes['statuses']))
            ->when($codes['methods'] !== [], fn ($q) => $q->orWhereIn(
                DB::raw("COALESCE(Invoices.payment_method, '')"), $codes['methods']))
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

    /**
     * Áp các nhóm bộ lọc đã áp dụng (kết hợp AND).
     */
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

        // Hàng đợi duyệt tiền mặt của kế toán (HĐ30 – HĐ32)
        if ($this->cashPending) {
            $query->where('Invoices.status', Invoice::STATUS_UNPAID)
                ->where('Invoices.payment_method', Invoice::METHOD_CASH);
        }
    }

    /**
     * Phương thức: các mã đã chọn, "Chưa chọn" = payment_method IS NULL.
     */
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

    /**
     * Tình trạng hạn, chỉ xét hóa đơn chưa thanh toán (Unpaid + Overdue):
     * Còn hạn (Unpaid, hạn ≥ hôm nay), Sắp đến hạn (Unpaid, còn 0 – SOON_DAYS ngày),
     * Quá hạn (Overdue, hoặc hạn < hôm nay).
     */
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

    /**
     * Sắp xếp theo danh sách trắng SORTS, luôn có thứ tự phụ theo mã để phân trang ổn định.
     * "Hạn thanh toán gần nhất": hóa đơn chưa thanh toán lên đầu, theo hạn tăng dần.
     */
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

    // ── Tiện ích ───────────────────────────────────────────────────────

    /**
     * Bộ lọc đã áp dụng dưới dạng mảng cùng key với $form.
     */
    private function appliedFilters(): array
    {
        $filters = [];
        foreach (array_keys(self::FILTER_DEFAULTS) as $property) {
            $filters[$property] = $this->{$property};
        }

        return $filters;
    }

    /**
     * Giá trị dạng chuỗi nhận giá trị không phải chuỗi (sửa URL / payload) thì đưa về mặc định
     * (số thì đổi thành chuỗi: Livewire đọc ?q=5400000 / ?amount_from=3000000 trên URL thành kiểu số);
     * giá trị dạng danh sách luôn là mảng chuỗi; công tắc luôn là true / false.
     * Áp cho cả thuộc tính đã áp dụng lẫn $form.
     */
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

    /**
     * Nhân viên được phép chọn: người lập hóa đơn trong phạm vi quyền (Sale Leader: đội mình),
     * thu hẹp theo đội nếu có. Salesperson không có bộ lọc này.
     */
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

    /**
     * Collation utf8mb4_unicode_ci đã bỏ qua dấu khi so sánh, trừ đ/Đ (khác d) → đổi đ/Đ thành d/D ở cả hai phía.
     * $column chỉ là tên cột cố định trong code, không phải dữ liệu người dùng.
     */
    private function foldColumn(string $column): string
    {
        return "REPLACE(REPLACE($column, 'đ', 'd'), 'Đ', 'D')";
    }

    /**
     * Chữ thường + bỏ dấu tiếng Việt, giữ nguyên số ký tự (dùng cho đánh dấu từ khóa).
     */
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

    /**
     * Ép giá trị từ URL / client thành danh sách chuỗi (giá trị không phải mảng chuỗi thì bỏ).
     *
     * @return array<int, string>
     */
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

    /**
     * Khoảng số tiền: ô trống = không giới hạn; số âm / không phải số thì bỏ qua ô đó (cờ lỗi);
     * "từ" > "đến" thì không lọc theo khoảng này (cờ lỗi).
     *
     * @return array{0: ?int, 1: ?int, 2: bool, 3: bool, 4: bool}
     */
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

    /**
     * Số tiền nhập tay: cho phép dấu chấm / phẩy / khoảng trắng ngăn cách nghìn (VD 3.000.000).
     * Trống → null; số âm, chữ, quá MAX_AMOUNT_DIGITS chữ số → false.
     */
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

    /**
     * Ngày kết thúc nhỏ hơn ngày bắt đầu thì không lọc theo khoảng đó (trả về cờ lỗi).
     *
     * @return array{0: ?string, 1: ?string, 2: bool}
     */
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
