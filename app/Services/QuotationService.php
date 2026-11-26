<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Employee;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Stage;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public const DEFAULT_EXPIRY_DAYS = 3;

    public const MAX_QUANTITY = 999;

    public const REJECT_DECLINED = 'declined';
    public const REJECT_NO_RESPONSE = 'no_response';
    public const REJECT_REASONS = [self::REJECT_DECLINED, self::REJECT_NO_RESPONSE];

    public function blockerFor(Opportunity $opportunity, ?Employee $employee): ?string
    {
        return match (true) {
            $employee?->role_name !== Role::SALESPERSON,
            $opportunity->employee_id !== $employee->employee_id => 'not_owner',
            $opportunity->stage_id !== Stage::CLOSED && ! $opportunity->agreedToRegister() => 'not_closed',
            $opportunity->status === Opportunity::STATUS_WON => 'won',
            $opportunity->status !== Opportunity::STATUS_IN_PROGRESS => 'closed_status',
            $opportunity->quotations()->where('status', Quotation::STATUS_DRAFT)->exists() => 'has_draft',
            default => null,
        };
    }

    public function canEdit(Quotation $quotation, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::SALESPERSON
            && $quotation->employee_id === $employee->employee_id
            && $quotation->status === Quotation::STATUS_DRAFT;
    }

    public function canExportPdf(Quotation $quotation, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::SALESPERSON
            && $quotation->employee_id === $employee->employee_id
            && $quotation->status !== Quotation::STATUS_REJECTED;
    }

    public function activeCourses(): Collection
    {
        return Course::query()->where('status', Course::STATUS_ACTIVE)->orderBy('course_name')->get();
    }

    public function validVouchers(): Collection
    {
        $today = today()->toDateString();

        return Voucher::query()
            ->where('status', Voucher::STATUS_ACTIVE)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('voucher_id')
            ->get();
    }

    public static function lineTotal(float $unitPrice, int $quantity, float $vat): float
    {
        return round($unitPrice * $quantity * (1 + $vat / 100), 2);
    }

    public function save(?Quotation $quotation, Opportunity $opportunity, Employee $employee, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $opportunity, $employee, $data) {
            if ($quotation === null) {
                $locked = Opportunity::query()->whereKey($opportunity->getKey())->lockForUpdate()->firstOrFail();
                abort_if($this->blockerFor($locked, $employee) !== null, 409);

                if ($locked->stage_id !== Stage::CLOSED) {
                    $locked->moveToStage(Stage::CLOSED, 'Khách đồng ý đăng ký - lập báo giá.');
                }

                $quotation = new Quotation([
                    'opportunity_id' => $opportunity->opportunity_id,
                    'employee_id'    => $employee->employee_id,
                    'status'         => Quotation::STATUS_DRAFT,
                ]);
            } else {
                $quotation = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();
                abort_unless($this->canEdit($quotation, $employee), 409);
            }

            $courses = Course::query()
                ->whereIn('course_id', array_keys($data['lines']))
                ->where('status', Course::STATUS_ACTIVE)
                ->get()
                ->keyBy('course_id');

            abort_if($courses->count() !== count($data['lines']), 409);

            $quotation->fill([
                'expiry_date' => $data['expiry_date'],
                'voucher_id'  => $data['voucher_id'] ?: null,
                'notes'       => filled($data['notes']) ? trim($data['notes']) : null,
            ])->save();

            QuotationDetail::query()->where('quotation_id', $quotation->quotation_id)->delete();

            foreach ($data['lines'] as $courseId => $quantity) {
                $course = $courses[$courseId];

                QuotationDetail::query()->insert([
                    'quotation_id' => $quotation->quotation_id,
                    'course_id'    => $courseId,
                    'unit_price'   => $course->unit_price,
                    'quantity'     => $quantity,
                    'VAT'          => $course->VAT,
                    'line_total'   => self::lineTotal((float) $course->unit_price, $quantity, (float) $course->VAT),
                ]);
            }

            $fresh = $quotation->fresh();

            $opportunity->updateQuietly(['expected_value' => $fresh->totalAmount()]);

            return $fresh;
        });
    }

    public function reject(Quotation $quotation, Employee $employee, string $reason = self::REJECT_DECLINED): bool
    {
        return DB::transaction(function () use ($quotation, $employee, $reason) {
            $locked = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($employee->role_name === Role::SALESPERSON
                && $locked->employee_id === $employee->employee_id
                && $locked->status === Quotation::STATUS_DRAFT, 409);

            $locked->update(['status' => Quotation::STATUS_REJECTED]);

            abort_unless(in_array($reason, self::REJECT_REASONS, true), 422);

            $noResponse = $reason === self::REJECT_NO_RESPONSE;

            return $this->closeOpportunity(
                $locked->opportunity,
                __($noResponse ? 'quotations.detail.close_reason_no_response' : 'quotations.detail.close_reason', ['code' => $locked->quotation_id], 'vi'),
                $noResponse ? Opportunity::STATUS_ARCHIVED : Opportunity::STATUS_LOST
            );
        });
    }

    public function confirmBlocker(Quotation $quotation, ?Employee $employee): ?string
    {
        return match (true) {
            $employee?->role_name !== Role::SALESPERSON,
            $quotation->employee_id !== $employee->employee_id => 'not_author',
            $quotation->status !== Quotation::STATUS_DRAFT => 'not_draft',
            $quotation->isExpired() => 'expired',
            $quotation->order()->exists() => 'has_order',
            ! $quotation->details()->exists() => 'no_lines',
            default => null,
        };
    }

    public function confirm(Quotation $quotation, Employee $employee): SalesOrder
    {
        return DB::transaction(function () use ($quotation, $employee) {
            $locked = Quotation::query()->with('voucher')->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();
            abort_if($this->confirmBlocker($locked, $employee) !== null, 409);

            $total = round($locked->totalAmount(), 2);

            $locked->update(['status' => Quotation::STATUS_CONFIRMED]);

            return SalesOrder::create([
                'payment_type'     => null,
                'total_amount'     => $total,
                'paid_amount'      => 0,
                'remaining_amount' => $total,
                'status'           => SalesOrder::STATUS_IN_PROGRESS,
                'quotation_id'     => $locked->quotation_id,
            ])->fresh();
        });
    }

    public function closeOpportunity(Opportunity $opportunity, string $reason, string $status = Opportunity::STATUS_LOST): bool
    {
        if ($opportunity->status !== Opportunity::STATUS_IN_PROGRESS) {
            return false;
        }

        $opportunity->close($status, $reason);

        return true;
    }

}
