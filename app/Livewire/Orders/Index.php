<?php

namespace App\Livewire\Orders;

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
 * Danh sách đơn hàng + tìm kiếm nâng cao + bộ lọc. Chỉ xem: đơn được tạo khi xác nhận báo giá (HĐ16 – HĐ17),
 * hóa đơn tạo từ đơn (HĐ18) và ghi nhận thanh toán làm ở bước sau. Quy trình: docs/quy-trinh-bao-gia-thanh-toan.md.
 * Dùng chung cho Giám đốc, Sale Admin, Sale Leader, Salesperson, Kế toán.
 *
 * Phạm vi dữ liệu luôn theo SalesOrder::visibleTo() (theo báo giá của đơn): Giám đốc / Sale Admin /
 * Kế toán xem tất cả, Sale Leader xem của đội mình, Salesperson xem của chính mình.
 *
 * Tổng tiền, đã thanh toán, còn lại là giá trị ĐÃ LƯU trong SalesOrders (không tính lại từ báo giá).
 *
 * Tìm kiếm (cập nhật ngay): nhiều từ khóa (AND), mỗi từ khóa khớp ít nhất một trường (OR);
 * "cụm từ" giữ nguyên; -từ khóa để loại trừ; không phân biệt hoa/thường và dấu tiếng Việt.
 *
 * Bộ lọc (bấm "Áp dụng" mới áp dụng): người dùng chọn trên $form; applyFilters() mới chép sang
 * các thuộc tính đã áp dụng (lưu trên URL) và danh sách chỉ đọc các thuộc tính đã áp dụng.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    const PER_PAGE = 10;

    const SORTS = ['newest', 'oldest', 'remaining_desc', 'total_desc', 'total_asc', 'code', 'customer'];

    // Giá trị bộ lọc hình thức cho đơn chưa xác định hình thức (payment_type NULL hoặc rỗng)
    const PAYMENT_NONE = 'none';

    // Tiến độ thanh toán: chưa thanh toán (đã TT = 0), đang thanh toán (0 < đã TT < tổng), đã thanh toán đủ (còn lại = 0)
    const PROGRESSES = ['unpaid', 'partial', 'paid'];

    // Hóa đơn: có hóa đơn đang chờ (Unpaid + Overdue), có hóa đơn quá hạn, chưa có hóa đơn, cần tạo hóa đơn tiếp
    const INVOICE_STATES = ['pending', 'overdue', 'none', 'needs_invoice'];

    // Hóa đơn "đang chờ" thanh toán
    const PENDING_INVOICE_STATUSES = [Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE];

    // Giới hạn tìm kiếm: số từ khóa, độ dài mỗi từ khóa (phần vượt quá bỏ qua)
    const MAX_TERMS = 8;
    const MAX_TERM_LENGTH = 100;

    // Từ khóa số tiền: ít nhất 3 chữ số; ô số tiền của bộ lọc: tối đa 15 chữ số (DECIMAL(18,2))
    const MIN_AMOUNT_DIGITS = 3;
    const MAX_AMOUNT_DIGITS = 15;

    // Giá trị mặc định của các bộ lọc (key của $form = tên thuộc tính đã áp dụng)
    const FILTER_DEFAULTS = [
        'statuses'     => [],
        'paymentTypes' => [],
        'progress'     => '',
        'invoiceState' => '',
        'amountFrom'   => '',
        'amountTo'     => '',
        'createdFrom'  => '',
        'createdTo'    => '',
        'team'         => '',
        'employee'     => '',
    ];

    // Các giá trị có thể bị sửa từ client → luôn đọc qua các hàm active*() / *Range đã kiểm tra lại.
    // Không khai báo kiểu: URL kiểu ?q[]=x sẽ gán mảng và gây lỗi 500 trước khi kịp kiểm tra;
    // giá trị sai kiểu được đưa về mặc định ở sanitizeFilters().

    #[Url(as: 'q', except: '')]
    public $search = '';

    // ── Bộ lọc ĐÃ ÁP DỤNG (danh sách đọc các giá trị này) ──

    // Trạng thái (chọn nhiều, thuộc SalesOrder::STATUSES)
    #[Url(as: 'status')]
    public $statuses = [];

    // Hình thức thanh toán (chọn nhiều, thuộc SalesOrder::PAYMENT_TYPES hoặc PAYMENT_NONE)
    #[Url(as: 'payment')]
    public $paymentTypes = [];

    // Tiến độ thanh toán: '' hoặc một trong PROGRESSES
    #[Url(as: 'progress', except: '')]
    public $progress = '';

    // Hóa đơn: '' hoặc một trong INVOICE_STATES
    #[Url(as: 'invoice', except: '')]
    public $invoiceState = '';

    // Khoảng tổng tiền (VNĐ)
    #[Url(as: 'amount_from', except: '')]
    public $amountFrom = '';

    #[Url(as: 'amount_to', except: '')]
    public $amountTo = '';

    // Khoảng ngày tạo đơn
    #[Url(as: 'created_from', except: '')]
    public $createdFrom = '';

    #[Url(as: 'created_to', except: '')]
    public $createdTo = '';

    // Đội của nhân viên lập báo giá (Giám đốc, Sale Admin, Kế toán), '' = Tất cả
    #[Url(except: '')]
    public $team = '';

    // Nhân viên lập báo giá (Giám đốc, Sale Admin, Kế toán, Sale Leader), '' = Tất cả
    #[Url(except: '')]
    public $employee = '';

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
     * Nút "Áp dụng": kiểm tra số tiền và khoảng ngày trên form; hợp lệ thì áp dụng toàn bộ bộ lọc và về trang 1.
     */
    public function applyFilters(): void
    {
        $this->sanitizeFilters();
        $this->resetErrorBag();

        [, , $fromInvalid, $toInvalid, $rangeInvalid] = $this->amountRangeOf($this->form['amountFrom'], $this->form['amountTo']);

        if ($fromInvalid) {
            $this->addError('form.amountFrom', __('orders.invalid_amount'));
        }

        if ($toInvalid) {
            $this->addError('form.amountTo', __('orders.invalid_amount'));
        }

        if ($rangeInvalid) {
            $this->addError('form.amountTo', __('orders.invalid_amount_range'));
        }

        if ($this->dateRange($this->form['createdFrom'], $this->form['createdTo'])[2]) {
            $this->addError('form.createdTo', __('orders.invalid_range'));
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        foreach (array_keys(self::FILTER_DEFAULTS) as $property) {
            $this->{$property} = $this->form[$property];
        }

        $this->resetPage();

        // Giao diện: áp dụng thành công thì đóng panel bộ lọc
        $this->dispatch('order-filters-applied');
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
            'search'   => ['search'],
            'statuses' => ['statuses'],
            'payment'  => ['paymentTypes'],
            'progress' => ['progress'],
            'invoice'  => ['invoiceState'],
            'amount'   => ['amountFrom', 'amountTo'],
            'created'  => ['createdFrom', 'createdTo'],
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
     * Prefix route theo vai trò, VD: 'director' → route 'director.orders'.
     */
    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Có bộ lọc Đội (Giám đốc, Sale Admin, Kế toán – các vai trò xem toàn bộ đơn hàng).
     */
    #[Computed]
    public function canFilterTeam(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN, Role::ACCOUNTANT], true);
    }

    /**
     * Có bộ lọc và cột Nhân viên (Salesperson chỉ thấy đơn của mình nên ẩn).
     */
    #[Computed]
    public function canFilterEmployee(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN, Role::ACCOUNTANT, Role::SALE_LEADER], true);
    }

    /**
     * Đội có nhân viên lập báo giá của đơn trong phạm vi quyền.
     */
    #[Computed]
    public function teams(): Collection
    {
        return $this->canFilterTeam
            ? SalesTeam::query()
                ->whereHas('employees.quotations.order', fn ($q) => $q->visibleTo($this->viewer()))
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
     * Trạng thái đang lọc (bỏ giá trị lạ), theo thứ tự của SalesOrder::STATUSES.
     *
     * @return array<int, string>
     */
    public function activeStatuses(): array
    {
        return array_values(array_intersect(SalesOrder::STATUSES, $this->stringList($this->statuses)));
    }

    /**
     * Hình thức đang lọc (bỏ giá trị lạ), theo thứ tự SalesOrder::PAYMENT_TYPES rồi PAYMENT_NONE.
     *
     * @return array<int, string>
     */
    public function activePaymentTypes(): array
    {
        return array_values(array_intersect([...SalesOrder::PAYMENT_TYPES, self::PAYMENT_NONE], $this->stringList($this->paymentTypes)));
    }

    public function activeProgress(): ?string
    {
        return in_array($this->progress, self::PROGRESSES, true) ? $this->progress : null;
    }

    public function activeInvoiceState(): ?string
    {
        return in_array($this->invoiceState, self::INVOICE_STATES, true) ? $this->invoiceState : null;
    }

    public function activeSort(): string
    {
        return in_array($this->sort, self::SORTS, true) ? $this->sort : 'newest';
    }

    /**
     * Khoảng tổng tiền đã áp dụng: [from, to, from sai?, to sai?, khoảng sai?].
     */
    #[Computed]
    public function amountRange(): array
    {
        return $this->amountRangeOf($this->amountFrom, $this->amountTo);
    }

    /**
     * Khoảng ngày tạo đơn đã áp dụng: [from, to, có lỗi?].
     */
    #[Computed]
    public function createdRange(): array
    {
        return $this->dateRange($this->createdFrom, $this->createdTo);
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
     * Số đơn theo trạng thái (thẻ thống kê): áp mọi điều kiện tìm kiếm / lọc đã áp dụng TRỪ trạng thái.
     */
    #[Computed]
    public function counts(): array
    {
        $rows = $this->filteredQuery(withStatus: false)
            ->selectRaw('SalesOrders.status, COUNT(*) AS total')
            ->groupBy('SalesOrders.status')
            ->pluck('total', 'status');

        $counts = ['all' => (int) $rows->sum()];

        foreach (SalesOrder::STATUSES as $status) {
            $counts[$status] = (int) ($rows[$status] ?? 0);
        }

        return $counts;
    }

    /**
     * Có đơn hàng nào trong phạm vi quyền không (phân biệt 2 trạng thái rỗng).
     */
    #[Computed]
    public function hasAnyOrder(): bool
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
        $label = fn (string $group, string $value) => __('orders.chip.'.$group).': '.$value;
        $list = fn (array $values, string $prefix) => collect($values)->map(fn ($v) => __($prefix.$v))->implode(', ');

        if ($this->terms !== []) {
            $chips['search'] = $label('search', trim($this->search));
        }

        if ($statuses = $this->activeStatuses()) {
            $chips['statuses'] = $label('statuses', $list($statuses, 'orders.status.'));
        }

        if ($paymentTypes = $this->activePaymentTypes()) {
            $chips['payment'] = $label('payment', $list($paymentTypes, 'orders.payment_type.'));
        }

        if ($progress = $this->activeProgress()) {
            $chips['progress'] = $label('progress', __('orders.progress.'.$progress));
        }

        if ($invoiceState = $this->activeInvoiceState()) {
            $chips['invoice'] = $label('invoice', __('orders.invoice_state.'.$invoiceState));
        }

        [$amountFrom, $amountTo] = $this->amountRange;
        if ($amountFrom !== null || $amountTo !== null) {
            $number = fn (?int $value) => $value === null ? null : number_format($value, 0, ',', '.');
            $chips['amount'] = $label('amount', $this->rangeText($number($amountFrom), $number($amountTo)).' ₫');
        }

        $date = fn (?string $value) => $value === null ? null : Carbon::parse($value)->format('d/m/Y');

        [$createdFrom, $createdTo] = $this->createdRange;
        if ($createdFrom !== null || $createdTo !== null) {
            $chips['created'] = $label('created', $this->rangeText($date($createdFrom), $date($createdTo)));
        }

        if ($this->activeTeam) {
            $chips['team'] = $label('team', $this->activeTeam->team_name);
        }

        if ($this->activeEmployee) {
            $chips['employee'] = $label('employee', $this->activeEmployee->full_name);
        }

        return $chips;
    }

    /**
     * Form có thay đổi chưa bấm "Áp dụng" (hiện nhắc người dùng).
     */
    public function hasPendingChanges(): bool
    {
        $normalize = function (array $filters): array {
            $filters['statuses'] = array_values(array_intersect(SalesOrder::STATUSES, $this->stringList($filters['statuses'] ?? [])));
            $filters['paymentTypes'] = array_values(array_intersect([...SalesOrder::PAYMENT_TYPES, self::PAYMENT_NONE], $this->stringList($filters['paymentTypes'] ?? [])));

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
                .'<mark class="od-mark">'.e(mb_substr($text, $start, $end - $start)).'</mark>';
            $cursor = $end;
        }

        return new HtmlString($html.e(mb_substr($text, $cursor)));
    }

    public function render()
    {
        $this->sanitizeFilters();

        $orders = $this->filteredQuery(withStatus: true)
            ->with([
                'quotation:quotation_id,opportunity_id,employee_id',
                'quotation.opportunity:opportunity_id,lead_id',
                'quotation.opportunity.lead:lead_id,full_name',
                'quotation.employee:employee_id,full_name',
            ])
            ->withCount([
                'invoices',
                'invoices as pending_invoices_count' => fn ($q) => $q->whereIn('Invoices.status', self::PENDING_INVOICE_STATUSES),
                'invoices as overdue_invoices_count' => fn ($q) => $q->where('Invoices.status', Invoice::STATUS_OVERDUE),
            ])
            ->tap(fn ($q) => $this->applySort($q))
            ->paginate(self::PER_PAGE);

        return view('livewire.orders.index', [
            'orders' => $orders,
        ])->title(__('orders.title'));
    }

    // ── Truy vấn ───────────────────────────────────────────────────────

    private function viewer(): ?Employee
    {
        return Auth::user()->employee;
    }

    /**
     * Đơn hàng trong phạm vi quyền của người đang đăng nhập (mọi truy vấn đều bắt đầu từ đây).
     */
    private function scopedQuery(): Builder
    {
        return SalesOrder::query()->visibleTo($this->viewer());
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
     *   - từ khóa bắt đầu bằng "-" là loại trừ (VD: -ORD001, -"Nguyễn Văn A");
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
     * Áp từ khóa: mọi từ khóa phải khớp (AND); từ khóa loại trừ thì đơn khớp bị bỏ.
     */
    private function applySearch(Builder $query): void
    {
        foreach ($this->terms as $term) {
            $like = '%'.addcslashes($term['needle'], '%_\\').'%';
            $codes = [
                'statuses' => $this->matchingCodes($term['text'], SalesOrder::STATUSES, 'orders.status.'),
                'payments' => $this->matchingCodes($term['text'], [...SalesOrder::PAYMENT_TYPES, self::PAYMENT_NONE], 'orders.payment_type.'),
            ];
            $amount = $this->searchAmount($term['text']);
            $match = fn (Builder $q) => $this->matchTerm($q, $like, $codes, $amount);

            $term['exclude'] ? $query->whereNot($match) : $query->where($match);
        }
    }

    /**
     * Mã (trạng thái, hình thức) có mã hoặc nhãn (vi / en) bắt đầu một từ bằng từ khóa,
     * VD "completed", "hoan tat", "tra gop" (so khớp bỏ dấu). Từ khóa 1 ký tự không xét; từ khóa 2 ký tự
     * phải trùng NGUYÊN một từ của nhãn (để "toan bo" tìm ra "Toàn bộ" mà "ho", "ta"… không khớp bừa).
     *
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    private function matchingCodes(string $text, array $codes, string $langPrefix): array
    {
        $needle = self::fold($text);
        $length = mb_strlen($needle);

        if ($length < 2) {
            return [];
        }

        $pattern = '/(^|\s)'.preg_quote($needle, '/').($length < 3 ? '(\s|$)' : '').'/u';

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
     * @param  array{statuses: array<int, string>, payments: array<int, string>}  $codes
     */
    private function matchTerm(Builder $query, string $like, array $codes, ?string $amount): void
    {
        $paymentCodes = array_values(array_diff($codes['payments'], [self::PAYMENT_NONE]));
        $paymentNone = in_array(self::PAYMENT_NONE, $codes['payments'], true);

        $query
            ->where('SalesOrders.order_id', 'like', $like)
            ->when($codes['statuses'] !== [], fn ($q) => $q->orWhereIn('SalesOrders.status', $codes['statuses']))
            ->when($paymentCodes !== [], fn ($q) => $q->orWhereIn(DB::raw("COALESCE(SalesOrders.payment_type, '')"), $paymentCodes))
            ->when($paymentNone, fn ($q) => $q->orWhereRaw("COALESCE(SalesOrders.payment_type, '') = ''"))
            ->when($amount !== null, fn ($q) => $q
                ->orWhere('SalesOrders.total_amount', $amount)
                ->orWhere('SalesOrders.paid_amount', $amount)
                ->orWhere('SalesOrders.remaining_amount', $amount))
            ->orWhereRaw($this->foldColumn("COALESCE(SalesOrders.notes, '')").' LIKE ?', [$like])
            ->orWhere('SalesOrders.quotation_id', 'like', $like)
            ->orWhereHas('quotation', fn ($q) => $q
                ->where('Quotations.opportunity_id', 'like', $like)
                ->orWhere('Quotations.employee_id', 'like', $like)
                ->orWhereRaw("COALESCE(Quotations.voucher_id, '') LIKE ?", [$like]))
            ->orWhereHas('quotation.opportunity.lead', fn ($q) => $q
                ->whereRaw($this->foldColumn('Leads.full_name').' LIKE ?', [$like])
                ->orWhere('Leads.email', 'like', $like)
                ->orWhere('Leads.phone', 'like', $like))
            ->orWhereHas('quotation.employee', fn ($q) => $q->whereRaw($this->foldColumn('Employees.full_name').' LIKE ?', [$like]))
            ->orWhereHas('quotation.details', fn ($q) => $q
                ->where('QuotationDetails.course_id', 'like', $like)
                ->orWhereHas('course', fn ($q) => $q->whereRaw($this->foldColumn('Courses.course_name').' LIKE ?', [$like])))
            ->orWhereHas('invoices', fn ($q) => $q->where('Invoices.invoice_id', 'like', $like));
    }

    /**
     * Áp các nhóm bộ lọc đã áp dụng (kết hợp AND).
     */
    private function applyFilterConditions(Builder $query, bool $withStatus): void
    {
        if ($withStatus && ($statuses = $this->activeStatuses())) {
            $query->whereIn('SalesOrders.status', $statuses);
        }

        $this->applyPaymentTypes($query);
        $this->applyProgress($query);
        $this->applyInvoiceState($query);

        [$amountFrom, $amountTo] = $this->amountRange;
        [$createdFrom, $createdTo] = $this->createdRange;

        $query
            ->when($amountFrom !== null, fn ($q) => $q->where('SalesOrders.total_amount', '>=', $amountFrom))
            ->when($amountTo !== null, fn ($q) => $q->where('SalesOrders.total_amount', '<=', $amountTo))
            ->when($createdFrom, fn ($q, $date) => $q->whereDate('SalesOrders.created_at', '>=', $date))
            ->when($createdTo, fn ($q, $date) => $q->whereDate('SalesOrders.created_at', '<=', $date));

        if ($this->activeTeam) {
            $query->whereHas('quotation.employee', fn ($q) => $q->where('Employees.team_id', $this->activeTeam->team_id));
        }

        if ($this->activeEmployee) {
            $query->whereHas('quotation', fn ($q) => $q->where('Quotations.employee_id', $this->activeEmployee->employee_id));
        }
    }

    /**
     * Hình thức: các mã đã chọn, "Chưa xác định" = payment_type NULL hoặc rỗng.
     */
    private function applyPaymentTypes(Builder $query): void
    {
        $types = $this->activePaymentTypes();
        if ($types === []) {
            return;
        }

        $codes = array_values(array_diff($types, [self::PAYMENT_NONE]));
        $withNone = in_array(self::PAYMENT_NONE, $types, true);

        $query->where(fn ($q) => $q
            ->when($codes !== [], fn ($q) => $q->whereIn('SalesOrders.payment_type', $codes))
            ->when($withNone, fn ($q) => $q->orWhereRaw("COALESCE(SalesOrders.payment_type, '') = ''")));
    }

    /**
     * Tiến độ thanh toán: chưa thanh toán (đã TT = 0), đang thanh toán (0 < đã TT < tổng), đã thanh toán đủ (còn lại = 0).
     */
    private function applyProgress(Builder $query): void
    {
        match ($this->activeProgress()) {
            'unpaid'  => $query->where('SalesOrders.paid_amount', 0),
            'partial' => $query->where('SalesOrders.paid_amount', '>', 0)
                ->whereColumn('SalesOrders.paid_amount', '<', 'SalesOrders.total_amount'),
            'paid'    => $query->where('SalesOrders.remaining_amount', 0),
            default   => null,
        };
    }

    /**
     * Hóa đơn của đơn. "Cần tạo hóa đơn tiếp": đơn Đang xử lý mà còn lại > tổng các hóa đơn đang chờ
     * (còn phần nợ chưa được lập hóa đơn, xem HĐ25).
     */
    private function applyInvoiceState(Builder $query): void
    {
        match ($this->activeInvoiceState()) {
            'pending'       => $query->whereHas('invoices', fn ($q) => $q->whereIn('Invoices.status', self::PENDING_INVOICE_STATUSES)),
            'overdue'       => $query->whereHas('invoices', fn ($q) => $q->where('Invoices.status', Invoice::STATUS_OVERDUE)),
            'none'          => $query->doesntHave('invoices'),
            'needs_invoice' => $query->where('SalesOrders.status', SalesOrder::STATUS_IN_PROGRESS)
                ->where('SalesOrders.remaining_amount', '>', Invoice::query()
                    ->selectRaw('COALESCE(SUM(Invoices.payment_amount), 0)')
                    ->whereColumn('Invoices.order_id', 'SalesOrders.order_id')
                    ->whereIn('Invoices.status', self::PENDING_INVOICE_STATUSES)
                    ->toBase()),
            default         => null,
        };
    }

    /**
     * Sắp xếp theo danh sách trắng SORTS, luôn có thứ tự phụ theo mã để phân trang ổn định.
     */
    private function applySort(Builder $query): void
    {
        match ($this->activeSort()) {
            'oldest'         => $query->orderBy('SalesOrders.created_at')->orderBy('SalesOrders.order_id'),
            'remaining_desc' => $query->orderByDesc('SalesOrders.remaining_amount')->orderBy('SalesOrders.order_id'),
            'total_desc'     => $query->orderByDesc('SalesOrders.total_amount')->orderBy('SalesOrders.order_id'),
            'total_asc'      => $query->orderBy('SalesOrders.total_amount')->orderBy('SalesOrders.order_id'),
            'code'           => $query->orderBy('SalesOrders.order_id'),
            'customer'       => $query->orderBy(
                Lead::query()->select('Leads.full_name')
                    ->join('Opportunities', 'Opportunities.lead_id', '=', 'Leads.lead_id')
                    ->join('Quotations', 'Quotations.opportunity_id', '=', 'Opportunities.opportunity_id')
                    ->whereColumn('Quotations.quotation_id', 'SalesOrders.quotation_id')
                    ->limit(1)
            )->orderBy('SalesOrders.order_id'),
            default          => $query->orderByDesc('SalesOrders.created_at')->orderByDesc('SalesOrders.order_id'),
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
     * giá trị dạng danh sách luôn là mảng chuỗi. Áp cho cả thuộc tính đã áp dụng lẫn $form.
     */
    private function sanitizeFilters(): void
    {
        $text = fn ($value, string $default) => match (true) {
            is_string($value)                   => $value,
            is_int($value) || is_float($value) => (string) $value,
            default                             => $default,
        };

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

    /**
     * Nhân viên được phép chọn: người lập báo giá có đơn trong phạm vi quyền (Sale Leader: đội mình),
     * thu hẹp theo đội nếu có. Salesperson không có bộ lọc này.
     */
    private function employeesFor(?SalesTeam $team): Collection
    {
        if (! $this->canFilterEmployee) {
            return collect();
        }

        $viewer = $this->viewer();

        return $this->employeesCache[$team?->team_id ?? ''] ??= Employee::query()
            ->whereHas('quotations.order', fn ($q) => $q->visibleTo($viewer))
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
            $from !== null && $to !== null => __('orders.chip.range', ['from' => $from, 'to' => $to]),
            $from !== null                 => __('orders.chip.from', ['value' => $from]),
            default                        => __('orders.chip.to', ['value' => $to]),
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
