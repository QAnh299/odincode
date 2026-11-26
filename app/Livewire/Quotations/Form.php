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

#[Layout('layouts.app')]
class Form extends Component
{
    #[Locked]
    public string $opportunityId = '';

    #[Locked]
    public ?string $quotationId = null;

    #[Locked]
    public ?string $blocked = null;

    public string $expiryDate = '';

    public string $voucherId = '';

    public string $notes = '';

    public string $customerEmail = '';

    public array $lines = [];

    public string $pickCourse = '';

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

        $current = $this->quotation?->voucher;
        if ($current && ! $vouchers->has($current->voucher_id)) {
            $vouchers->put($current->voucher_id, $current);
        }

        return $vouchers;
    }

    #[Computed]
    public function createdDate(): string
    {
        return $this->quotation?->created_at?->toDateString() ?? today()->toDateString();
    }

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

    #[Computed]
    public function totals(): array
    {
        $subtotal = (float) collect($this->rows)->sum('total');
        $voucher = $this->vouchers->get($this->voucherId);
        $discount = $voucher?->discountFor($subtotal) ?? 0.0;

        return ['subtotal' => $subtotal, 'discount' => $discount, 'total' => max(0, $subtotal - $discount), 'voucher' => $voucher];
    }

    #[Computed]
    public function purchasedCourses(): array
    {
        $leadId = $this->opportunity->lead_id;

        if (! $leadId) {
            return [];
        }

        $result = [];

        DB::table('QuotationDetails as d')
            ->join('Quotations as q', 'q.quotation_id', '=', 'd.quotation_id')
            ->join('Opportunities as op', 'op.opportunity_id', '=', 'q.opportunity_id')
            ->join('SalesOrders as o', 'o.quotation_id', '=', 'q.quotation_id')
            ->where('op.lead_id', $leadId)
            ->orderByRaw('LENGTH(o.order_id), o.order_id')
            ->get(['d.course_id', 'o.order_id', 'o.paid_amount'])
            ->each(function ($row) use (&$result) {
                $paid = (float) $row->paid_amount > 0;

                if (! isset($result[$row->course_id]) || $paid || ! $result[$row->course_id]['paid']) {
                    $result[$row->course_id] = ['order' => $row->order_id, 'paid' => $paid];
                }
            });

        return $result;
    }

    #[Computed]
    public function availableCourses(): Collection
    {
        $chosen = collect($this->lines)->pluck('course_id')->all();

        return $this->courses->reject(fn ($course) => in_array($course->course_id, $chosen, true));
    }

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

    private function forgetLineCache(): void
    {
        unset($this->availableCourses, $this->rows, $this->totals);
    }

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

    public function needsEmail(): bool
    {
        return blank($this->opportunity->lead?->email);
    }

    private function viewer(): ?Employee
    {
        return Auth::user()->employee;
    }
}
