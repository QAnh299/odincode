<?php

namespace App\Livewire\Quotations;

use App\Models\Employee;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\Voucher;
use App\Services\QuotationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Biểu mẫu lập báo giá (HĐ1 – HĐ6) và sửa báo giá Nháp (HĐ10). Chỉ Salesperson.
 * Quy trình: docs/quy-trinh-bao-gia-thanh-toan.md.
 *
 *   - Lập mới (route salesperson.quotations.create/{opportunity}): kiểm tra điều kiện HĐ2; không đạt thì
 *     hiện lý do và kết thúc, đạt thì hiện biểu mẫu (khách và cơ hội chỉ đọc, hạn mặc định 3 ngày).
 *   - Sửa (route salesperson.quotations.edit/{quotation}): chỉ người lập, báo giá còn Nháp và chưa hết hạn.
 *   - Lưu (HĐ4 → HĐ6): kiểm tra phía server rồi QuotationService::save() tính tiền và lưu trong một giao dịch.
 *
 * Đơn giá và VAT luôn lấy từ khóa học lúc lưu, client chỉ gửi mã khóa học + số lượng.
 */
#[Layout('layouts.app')]
class Form extends Component
{
    #[Locked]
    public string $opportunityId = '';

    #[Locked]
    public ?string $quotationId = null;

    // Lý do không lập được (HĐ2), null = hiện biểu mẫu
    #[Locked]
    public ?string $blocked = null;

    public string $expiryDate = '';

    public string $voucherId = '';

    public string $notes = '';

    // Bổ sung email khách khi hồ sơ còn thiếu (cần để gửi email hóa đơn sau khi thanh toán)
    public string $customerEmail = '';

    // Dòng khóa học: [['course_id' => ..., 'quantity' => ...], ...]
    public array $lines = [];

    // Khóa học đang chọn ở ô "Thêm khóa học"
    public string $pickCourse = '';

    /**
     * Nhận MÃ từ URL (create/{opportunity}, {quotation}/edit) rồi tự tìm bản ghi: hai route dùng chung component,
     * nếu khai báo kiểu model có thể null thì route binding của Laravel gán lệch tham số và trả 404.
     */
    public function mount(?string $opportunity = null, ?string $quotation = null): void
    {
        $service = app(QuotationService::class);
        $employee = $this->viewer();

        if ($quotation !== null) {
            $quotation = Quotation::query()->findOrFail($quotation);
            abort_unless($service->canEdit($quotation, $employee), 403);

            $this->quotationId = $quotation->quotation_id;
            $this->opportunityId = $quotation->opportunity_id;
            $this->expiryDate = $quotation->expiry_date->toDateString();
            $this->voucherId = (string) $quotation->voucher_id;
            $this->notes = (string) $quotation->notes;
            $this->lines = $quotation->details()->orderBy('course_id')->get()
                ->map(fn ($d) => ['course_id' => $d->course_id, 'quantity' => (int) $d->quantity])
                ->all();

            return;
        }

        $opportunity = Opportunity::query()->findOrFail((string) $opportunity);

        $this->opportunityId = $opportunity->opportunity_id;
        $this->blocked = $service->blockerFor($opportunity, $employee);
        $this->expiryDate = today()->addDays(QuotationService::DEFAULT_EXPIRY_DAYS)->toDateString();
    }

    // ── Dữ liệu hiển thị ───────────────────────────────────────────────

    #[Computed]
    public function opportunity(): Opportunity
    {
        return Opportunity::query()->with(['lead', 'stage'])->findOrFail($this->opportunityId);
    }

    #[Computed]
    public function quotation(): ?Quotation
    {
        return $this->quotationId ? Quotation::query()->find($this->quotationId) : null;
    }

    #[Computed]
    public function courses(): Collection
    {
        return app(QuotationService::class)->activeCourses()->keyBy('course_id');
    }

    #[Computed]
    public function vouchers(): Collection
    {
        $vouchers = app(QuotationService::class)->validVouchers()->keyBy('voucher_id');

        // Sửa Nháp: voucher đã thống nhất với khách lúc lập báo giá được giữ nguyên dù nay đã hết hiệu lực
        $current = $this->quotation?->voucher;
        if ($current && ! $vouchers->has($current->voucher_id)) {
            $vouchers->put($current->voucher_id, $current);
        }

        return $vouchers;
    }

    /**
     * Ngày lập: lập mới = hôm nay; sửa = ngày lập của báo giá (hạn không được trước ngày này, HĐ4).
     */
    #[Computed]
    public function createdDate(): string
    {
        return $this->quotation?->created_at?->toDateString() ?? today()->toDateString();
    }

    /**
     * Dòng hiển thị kèm đơn giá, VAT, thành tiền (bỏ qua dòng có khóa học không còn kích hoạt).
     *
     * @return array<int, array{index: int, course: \App\Models\Course, quantity: int, total: float}>
     */
    #[Computed]
    public function rows(): array
    {
        $rows = [];

        foreach ($this->lines as $index => $line) {
            $course = $this->courses->get($line['course_id'] ?? '');
            if (! $course) {
                continue;
            }

            $quantity = max(0, (int) ($line['quantity'] ?? 0));
            $rows[] = [
                'index'    => $index,
                'course'   => $course,
                'quantity' => $quantity,
                'total'    => QuotationService::lineTotal((float) $course->unit_price, $quantity, (float) $course->VAT),
            ];
        }

        return $rows;
    }

    /**
     * Tạm tính, giảm giá voucher, tổng tiền (cùng công thức Quotation::totalAmount()).
     *
     * @return array{subtotal: float, discount: float, total: float, voucher: ?Voucher}
     */
    #[Computed]
    public function totals(): array
    {
        $subtotal = (float) collect($this->rows)->sum('total');
        $voucher = $this->vouchers->get($this->voucherId);
        $discount = $voucher?->discountFor($subtotal) ?? 0.0;

        return ['subtotal' => $subtotal, 'discount' => $discount, 'total' => max(0, $subtotal - $discount), 'voucher' => $voucher];
    }

    /**
     * Khóa học còn chọn được ở ô "Thêm khóa học" (chưa có trong báo giá).
     */
    #[Computed]
    public function availableCourses(): Collection
    {
        $chosen = collect($this->lines)->pluck('course_id')->all();

        return $this->courses->reject(fn ($course) => in_array($course->course_id, $chosen, true));
    }

    // ── Thao tác trên biểu mẫu ─────────────────────────────────────────

    /**
     * Chọn khóa học ở ô chọn là thêm ngay một dòng (số lượng 1), tạm tính và tổng cập nhật luôn.
     */
    public function updatedPickCourse(): void
    {
        $this->addCourse();
    }

    public function addCourse(): void
    {
        if ($this->blocked || ! $this->availableCourses->has($this->pickCourse)) {
            return;
        }

        $this->lines[] = ['course_id' => $this->pickCourse, 'quantity' => 1];
        $this->pickCourse = '';
        $this->resetErrorBag('lines');
        $this->forgetLineCache();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->forgetLineCache();
    }

    /**
     * Xóa kết quả computed đã nhớ trong request (danh sách còn chọn được, dòng, tổng) sau khi đổi $lines.
     */
    private function forgetLineCache(): void
    {
        unset($this->availableCourses, $this->rows, $this->totals);
    }

    /**
     * Kiểm tra thông tin báo giá và hồ sơ khách hàng (đủ trường, ít nhất một khóa, số lượng > 0, hạn không trước ngày lập,
     * voucher còn hiệu lực, khách có email); sai thì báo lỗi để sửa, đúng thì lưu Nháp (kèm email bổ sung) và mở trang chi tiết.
     */
    public function save(QuotationService $service)
    {
        abort_if($this->blocked !== null, 403);

        $courseIds = $this->courses->keys()->all();
        $voucherIds = $this->vouchers->keys()->all();

        $this->validate([
            'expiryDate'         => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->createdDate],
            'voucherId'          => ['nullable', 'string', 'in:'.implode(',', $voucherIds)],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'lines'              => ['required', 'array', 'min:1'],
            'lines.*.course_id'  => ['required', 'string', 'distinct', 'in:'.implode(',', $courseIds)],
            'lines.*.quantity'   => ['required', 'integer', 'min:1', 'max:'.QuotationService::MAX_QUANTITY],
            'customerEmail'      => $this->needsEmail() ? ['required', 'email:rfc', 'max:254'] : ['nullable'],
        ], [], [
            'expiryDate'        => __('quotations.form.expiry_date'),
            'voucherId'         => __('quotations.form.voucher'),
            'notes'             => __('quotations.form.notes'),
            'lines'             => __('quotations.form.courses'),
            'lines.*.course_id' => __('quotations.form.course'),
            'lines.*.quantity'  => __('quotations.form.quantity'),
            'customerEmail'     => __('quotations.form.customer_email'),
        ]);

        $quotation = DB::transaction(function () use ($service) {
            if ($this->needsEmail()) {
                $this->opportunity->lead->update(['email' => trim($this->customerEmail)]);
            }

            return $service->save($this->quotation, $this->opportunity, $this->viewer(), [
                'expiry_date' => $this->expiryDate,
                'voucher_id'  => $this->voucherId !== '' ? $this->voucherId : null,
                'notes'       => $this->notes,
                'lines'       => collect($this->lines)->mapWithKeys(fn ($l) => [$l['course_id'] => (int) $l['quantity']])->all(),
            ]);
        });

        session()->flash('quotation_notice', __($this->quotationId ? 'quotations.form.updated' : 'quotations.form.created', ['code' => $quotation->quotation_id]));

        return $this->redirectRoute('salesperson.quotations.show', $quotation, navigate: false);
    }

    public function render()
    {
        $title = $this->quotationId
            ? __('quotations.form.edit_title', ['code' => $this->quotationId])
            : __('quotations.form.create_title');

        return view('livewire.quotations.form')->title($title);
    }

    /**
     * Khách chưa có email: biểu mẫu hiện ô bổ sung email và bắt buộc nhập trước khi lưu.
     */
    public function needsEmail(): bool
    {
        return blank($this->opportunity->lead?->email);
    }

    private function viewer(): ?Employee
    {
        return Auth::user()->employee;
    }
}
