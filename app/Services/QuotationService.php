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
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ báo giá giai đoạn A – C của quy trình (docs/quy-trinh-bao-gia-thanh-toan.md):
 * kiểm tra điều kiện lập (HĐ2), tính tiền và lưu báo giá Nháp (HĐ6, HĐ10), từ chối (HĐ12 – HĐ13),
 * kết thúc cơ hội khi khách không còn nhu cầu (HĐ14), tự từ chối báo giá quá hạn (HĐ15)
 * và xác nhận báo giá + tạo đơn hàng (HĐ16 – HĐ17; đơn mới có payment_type NULL = chưa xác định, mục 0.4).
 */
class QuotationService
{
    // Hạn mặc định của báo giá Nháp (ngày, tính từ ngày lập)
    const DEFAULT_EXPIRY_DAYS = 3;

    // Số lượng tối đa một dòng khóa học (chặn nhập nhầm)
    const MAX_QUANTITY = 999;

    /**
     * HĐ2: lý do không lập được báo giá trên cơ hội (khóa lang quotations.form.blocked.*), null = được lập.
     * Chỉ Salesperson phụ trách cơ hội; cơ hội đã Chốt, InProgress hoặc Won, và không còn báo giá Nháp.
     */
    public function blockerFor(Opportunity $opportunity, ?Employee $employee): ?string
    {
        return match (true) {
            $employee?->role_name !== Role::SALESPERSON,
            $opportunity->employee_id !== $employee->employee_id => 'not_owner',
            $opportunity->stage_id !== Stage::CLOSED => 'not_closed',
            ! in_array($opportunity->status, [Opportunity::STATUS_IN_PROGRESS, Opportunity::STATUS_WON], true) => 'closed_status',
            $opportunity->quotations()->where('status', Quotation::STATUS_DRAFT)->exists() => 'has_draft',
            default => null,
        };
    }

    /**
     * Báo giá Nháp mà nhân viên được sửa (HĐ10): chính Salesperson lập, còn Nháp, chưa hết hạn.
     */
    public function canEdit(Quotation $quotation, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::SALESPERSON
            && $quotation->employee_id === $employee->employee_id
            && $quotation->status === Quotation::STATUS_DRAFT
            && ! $quotation->isExpired();
    }

    /**
     * Khóa học đang kích hoạt (danh sách chọn trên biểu mẫu).
     */
    public function activeCourses(): Collection
    {
        return Course::query()->where('status', Course::STATUS_ACTIVE)->orderBy('course_name')->get();
    }

    /**
     * Voucher còn hiệu lực hôm nay (Active và trong thời gian áp dụng).
     */
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

    /**
     * Thành tiền một dòng = đơn giá × số lượng × (1 + VAT/100), làm tròn 2 số lẻ (khớp dữ liệu QuotationDetails).
     */
    public static function lineTotal(float $unitPrice, int $quantity, float $vat): float
    {
        return round($unitPrice * $quantity * (1 + $vat / 100), 2);
    }

    /**
     * HĐ6 (lập mới) / HĐ10 (sửa Nháp): tính thành tiền theo giá và VAT hiện tại của khóa học, lưu báo giá Nháp
     * và toàn bộ dòng chi tiết trong một giao dịch. $lines = [course_id => số lượng], đã kiểm tra ở biểu mẫu (HĐ4).
     * Lập mới: khóa dòng cơ hội rồi kiểm tra lại HĐ2 để hai lần bấm cùng lúc không tạo hai báo giá Nháp.
     *
     * @param  array{expiry_date: string, voucher_id: ?string, notes: ?string, lines: array<string, int>}  $data
     */
    public function save(?Quotation $quotation, Opportunity $opportunity, Employee $employee, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $opportunity, $employee, $data) {
            if ($quotation === null) {
                $locked = Opportunity::query()->whereKey($opportunity->getKey())->lockForUpdate()->firstOrFail();
                abort_if($this->blockerFor($locked, $employee) !== null, 409);

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

            return $quotation->fresh();
        });
    }

    /**
     * Salesperson từ chối báo giá Nháp khi khách không đồng ý: báo giá → Từ chối và kết thúc cơ hội trong cùng giao dịch
     * (đang xử lý → Lost, đã Won giữ nguyên; chốt 23/10/2026). Trả về true nếu cơ hội đã chuyển Lost.
     */
    public function reject(Quotation $quotation, Employee $employee): bool
    {
        return DB::transaction(function () use ($quotation, $employee) {
            $locked = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($employee->role_name === Role::SALESPERSON
                && $locked->employee_id === $employee->employee_id
                && $locked->status === Quotation::STATUS_DRAFT, 409);

            $locked->update(['status' => Quotation::STATUS_REJECTED]);

            // Khách không đồng ý = không mua: kết thúc luôn (đổi khóa học / số lượng thì SP sửa báo giá Nháp)
            return $this->closeOpportunity(
                $locked->opportunity,
                __('quotations.detail.close_reason', ['code' => $locked->quotation_id], 'vi')
            );
        });
    }

    /**
     * HĐ17: lý do không xác nhận được (khóa lang quotations.detail.confirm_blocked.*), null = được xác nhận.
     * Chỉ Salesperson lập báo giá; báo giá còn Nháp, chưa hết hạn, chưa có đơn hàng.
     * Voucher KHÔNG kiểm tra lại: voucher hợp lệ ngày lập báo giá được giữ nguyên tới khi xác nhận, vì khách đã
     * đồng ý mức giá đó (quyết định 18/10/2026).
     */
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

    /**
     * HĐ16 → HĐ17: một giao dịch gồm báo giá → Đã xác nhận + tạo đơn hàng: tổng tiền = tổng báo giá (tính lúc xác nhận
     * và LƯU vào đơn), đã thanh toán 0, còn lại = tổng, Đang xử lý, hình thức NULL (chọn ở hóa đơn đầu tiên – HĐ21).
     * Khóa dòng báo giá và kiểm tra lại điều kiện để hai lần bấm cùng lúc không tạo hai đơn; lỗi thì hoàn tác cả hai.
     */
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

    /**
     * Kết thúc cơ hội khi báo giá bị từ chối: cơ hội đang xử lý → Lost (stage Lưu trữ, ghi lý do); đã Won → giữ Won.
     * Trả về true nếu cơ hội đã chuyển Lost.
     */
    public function closeOpportunity(Opportunity $opportunity, string $reason): bool
    {
        if ($opportunity->status !== Opportunity::STATUS_IN_PROGRESS) {
            return false;
        }

        $opportunity->close(Opportunity::STATUS_LOST, $reason);

        return true;
    }

    /**
     * HĐ15: báo giá Nháp quá hạn (hết hạn trước hôm nay) → Từ chối; cơ hội đang xử lý không còn báo giá Nháp
     * nào khác → Lost; cơ hội đã Won giữ nguyên. Chạy hằng ngày (lệnh quotations:expire). Trả về số báo giá đã xử lý.
     */
    public function expireOverdue(?Carbon $today = null): int
    {
        $today ??= today();
        $count = 0;

        Quotation::query()
            ->where('status', Quotation::STATUS_DRAFT)
            ->whereDate('expiry_date', '<', $today->toDateString())
            ->orderBy('quotation_id')
            ->each(function (Quotation $quotation) use (&$count) {
                DB::transaction(function () use ($quotation, &$count) {
                    $locked = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->first();

                    if ($locked?->status !== Quotation::STATUS_DRAFT) {
                        return;
                    }

                    $locked->update(['status' => Quotation::STATUS_REJECTED]);
                    $count++;

                    $opportunity = $locked->opportunity;
                    if ($opportunity && ! $opportunity->quotations()->where('status', Quotation::STATUS_DRAFT)->exists()) {
                        $this->closeOpportunity($opportunity, __('quotations.expired_reason', ['code' => $locked->quotation_id], 'vi'));
                    }
                });
            });

        return $count;
    }
}
