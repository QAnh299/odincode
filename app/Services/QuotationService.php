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

/**
 * Nghiệp vụ báo giá giai đoạn A – C của quy trình (docs/quy-trinh-bao-gia-thanh-toan.md):
 * kiểm tra điều kiện lập (HĐ2), tính tiền và lưu báo giá Nháp (HĐ6 – HĐ10, sửa / gia hạn ở HĐ14),
 * từ chối và kết thúc cơ hội (HĐ15 – HĐ16), kiểm tra hạn khi xác nhận (HĐ18)
 * và xác nhận báo giá + tạo đơn hàng (HĐ19 – HĐ20; đơn mới có payment_type NULL = chưa xác định, mục 0.4).
 */
class QuotationService
{
    // Hạn mặc định của báo giá Nháp (ngày, tính từ ngày lập)
    const DEFAULT_EXPIRY_DAYS = 3;

    // Số lượng tối đa một dòng khóa học (chặn nhập nhầm)
    const MAX_QUANTITY = 999;

    // Lý do từ chối báo giá (HĐ15): khách không đồng ý → Lost; khách không phản hồi đến khi quá hạn → Archived,
    // cùng quy tắc với quy trình xử lý cơ hội (không liên lạc được → Lưu trữ). Chốt 26/10/2026.
    const REJECT_DECLINED = 'declined';
    const REJECT_NO_RESPONSE = 'no_response';
    const REJECT_REASONS = [self::REJECT_DECLINED, self::REJECT_NO_RESPONSE];

    /**
     * HĐ2: lý do không lập được báo giá trên cơ hội (khóa lang quotations.form.blocked.*), null = được lập.
     * Chỉ Salesperson phụ trách cơ hội; cơ hội đã Chốt, InProgress hoặc Won, và không còn báo giá Nháp
     * (một cơ hội có một hoặc nhiều báo giá, nhưng mỗi lúc chỉ một báo giá Nháp).
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
     * Báo giá mà nhân viên được sửa: chính Salesperson lập, còn Nháp. Báo giá đã quá hạn vẫn sửa được để gia hạn
     * ngày hết hạn (chốt 25/10/2026).
     */
    public function canEdit(Quotation $quotation, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::SALESPERSON
            && $quotation->employee_id === $employee->employee_id
            && $quotation->status === Quotation::STATUS_DRAFT;
    }

    /**
     * Xuất PDF: Salesperson lập báo giá, báo giá chưa bị từ chối (Nháp – kể cả đã quá hạn – hoặc Đã xác nhận).
     */
    public function canExportPdf(Quotation $quotation, ?Employee $employee): bool
    {
        return $employee?->role_name === Role::SALESPERSON
            && $quotation->employee_id === $employee->employee_id
            && $quotation->status !== Quotation::STATUS_REJECTED;
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
     * HĐ15 – HĐ16: Salesperson từ chối báo giá Nháp: báo giá → Từ chối và kết thúc cơ hội trong cùng giao dịch.
     * Cơ hội đang xử lý → Lost (khách không đồng ý) hoặc Archived (khách không phản hồi đến khi báo giá quá hạn);
     * đã Won giữ nguyên. Trả về true nếu cơ hội đã được kết thúc.
     */
    public function reject(Quotation $quotation, Employee $employee, string $reason = self::REJECT_DECLINED): bool
    {
        return DB::transaction(function () use ($quotation, $employee, $reason) {
            $locked = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($employee->role_name === Role::SALESPERSON
                && $locked->employee_id === $employee->employee_id
                && $locked->status === Quotation::STATUS_DRAFT, 409);

            $locked->update(['status' => Quotation::STATUS_REJECTED]);

            abort_unless(in_array($reason, self::REJECT_REASONS, true), 422);

            // Khách không mua: kết thúc luôn (đổi khóa học / số lượng thì SP sửa báo giá Nháp)
            $noResponse = $reason === self::REJECT_NO_RESPONSE;

            return $this->closeOpportunity(
                $locked->opportunity,
                __($noResponse ? 'quotations.detail.close_reason_no_response' : 'quotations.detail.close_reason', ['code' => $locked->quotation_id], 'vi'),
                $noResponse ? Opportunity::STATUS_ARCHIVED : Opportunity::STATUS_LOST
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
     * Kết thúc cơ hội khi báo giá bị từ chối: cơ hội đang xử lý → Lost / Archived (stage Lưu trữ, ghi lý do); đã Won → giữ Won.
     * Trả về true nếu cơ hội đã được kết thúc.
     */
    public function closeOpportunity(Opportunity $opportunity, string $reason, string $status = Opportunity::STATUS_LOST): bool
    {
        if ($opportunity->status !== Opportunity::STATUS_IN_PROGRESS) {
            return false;
        }

        $opportunity->close($status, $reason);

        return true;
    }

}
