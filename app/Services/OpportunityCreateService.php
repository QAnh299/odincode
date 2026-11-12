<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Tạo cơ hội mới cho khách cũ quay lại đăng ký thêm khóa.
 *
 * Luồng:
 *   1. candidates()  – tìm danh sách khách trong phạm vi salesperson (gộp theo khách).
 *   2. check()       – phân loại tình trạng khách: chặn / cảnh báo / được tạo.
 *   3. create()      – tạo cơ hội trong transaction có khóa dòng, kiểm tra lại sau khi khóa.
 *
 * Không tạo Lead mới. Không phá luồng phân Lead của Sale Admin.
 * Dùng lại LeadDuplicateService::stateOf() để phân loại tình trạng, không viết logic trùng.
 */
class OpportunityCreateService
{
    public function __construct(
        protected LeadDuplicateService $duplicate,
        protected LeadImportService $import,
    ) {
    }

    // ── Tìm khách ─────────────────────────────────────────────────────

    /**
     * Tìm khách trong phạm vi salesperson: học viên của mình + cơ hội Lost/Archived của mình (chưa có học viên).
     * Gộp theo khách (Lead), trả về Collection của Lead kèm thông tin hiển thị.
     * Mỗi dòng là một khách duy nhất — dù khách vừa có học viên vừa có cơ hội cũ cũng chỉ hiện một dòng.
     *
     * @return Collection<int, Lead>
     */
    public function candidates(Employee $salesperson, string $search = ''): Collection
    {
        $search = trim($search);

        // Lead ID của học viên salesperson phụ trách
        $viaStudent = Opportunity::query()
            ->where('employee_id', $salesperson->employee_id)
            ->whereHas('student')
            ->select('lead_id');

        // Lead ID của cơ hội Lost/Archived salesperson phụ trách (chưa có học viên)
        $viaOldOpp = Opportunity::query()
            ->where('employee_id', $salesperson->employee_id)
            ->whereIn('status', [Opportunity::STATUS_LOST, Opportunity::STATUS_ARCHIVED])
            ->whereDoesntHave('student')
            ->select('lead_id');

        $query = Lead::query()
            ->whereIn('lead_id', $viaStudent->union($viaOldOpp))
            ->with($this->leadDetails());

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('full_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhereHas('opportunities', fn (Builder $o) => $o
                    ->where('employee_id', $salesperson->employee_id)
                    ->where('opportunity_id', 'like', $like))
                ->orWhereHas('opportunities.student', fn (Builder $s) => $s
                    ->where('student_id', 'like', $like)));
        }

        return $query->orderBy('full_name')->get();
    }

    /**
     * Tìm một khách theo Lead ID, kiểm tra salesperson có quyền với khách này không.
     * Trả về Lead (kèm chi tiết) hoặc null nếu không có quyền.
     */
    public function findForSalesperson(string $leadId, Employee $salesperson): ?Lead
    {
        $lead = Lead::query()
            ->whereKey($leadId)
            ->with($this->leadDetails())
            ->first();

        if (! $lead) {
            return null;
        }

        // Kiểm tra salesperson đã từng có ít nhất một cơ hội với khách này
        if (! $lead->opportunities->contains('employee_id', $salesperson->employee_id)) {
            return null;
        }

        return $lead;
    }

    // ── Kiểm tra tình trạng ───────────────────────────────────────────

    /**
     * Phân loại tình trạng của khách:
     * [
     *   'state'    => 'blocked' | 'warning' | 'ok',
     *   'blocks'   => [ ['type' => ..., ...] ],   // chặn tạo
     *   'warnings' => [ ['type' => ..., ...] ],   // cảnh báo không chặn
     * ]
     *
     * Loại block:
     *   'own_in_progress'   – cơ hội đang tư vấn của chính mình (kèm opportunity_id, url)
     *   'other_in_progress' – cơ hội đang tư vấn của người khác (kèm owner_name, owner_phone)
     *   'in_queue'          – khách đang chờ phân
     *
     * Loại warning:
     *   'studying'  – học viên đang học (kèm course names)
     *   'debt'      – còn nợ học phí (kèm danh sách ['course' => ..., 'remaining' => ...])
     */
    public function check(Lead $lead, Employee $salesperson): array
    {
        // Caller chịu trách nhiệm load quan hệ cần thiết (findForSalesperson và candidates đã làm điều này).
        // Nếu gọi trực tiếp với Lead chưa load, các quan hệ sẽ lazy-load khi cần.
        $state = $this->duplicate->stateOf($lead);

        $blocks   = [];
        $warnings = [];

        if ($state === LeadDuplicateService::IN_QUEUE) {
            $blocks[] = ['type' => 'in_queue'];
        } elseif ($state === LeadDuplicateService::IN_PROGRESS) {
            $active = $lead->opportunities->firstWhere('status', Opportunity::STATUS_IN_PROGRESS);

            if ($active && $active->employee_id === $salesperson->employee_id) {
                $blocks[] = [
                    'type'           => 'own_in_progress',
                    'opportunity_id' => $active->opportunity_id,
                ];
            } elseif ($active) {
                $owner = $active->employee;
                $blocks[] = [
                    'type'        => 'other_in_progress',
                    'owner_name'  => $owner?->full_name ?? '-',
                    'owner_phone' => $owner?->phone ?? null,
                ];
            }
        }

        // Cảnh báo: học viên đang học
        $studyingCourses = $this->studyingCourses($lead);
        if ($studyingCourses->isNotEmpty()) {
            $warnings[] = [
                'type'    => 'studying',
                'courses' => $studyingCourses->all(),
            ];
        }

        // Cảnh báo: đơn trả góp còn nợ (chỉ tính đơn đã đóng ít nhất một lần, paid_amount > 0)
        $debts = $this->outstandingDebts($lead);
        if ($debts->isNotEmpty()) {
            $warnings[] = [
                'type'  => 'debt',
                'items' => $debts->all(),
            ];
        }

        return [
            'state'    => $blocks ? 'blocked' : ($warnings ? 'warning' : 'ok'),
            'blocks'   => $blocks,
            'warnings' => $warnings,
        ];
    }

    // ── Tạo cơ hội ────────────────────────────────────────────────────

    /**
     * Tạo cơ hội mới cho khách trong một transaction có khóa dòng Lead.
     * Kiểm tra lại tình trạng sau khi khóa để chống trùng đồng thời.
     * Cập nhật thông tin liên hệ (phone/email/source_url) nếu có thay đổi, cùng transaction.
     * Không tạo Lead mới, Lead giữ nguyên trạng thái Converted.
     *
     * @param  array{phone?: string, email?: string, source_url?: string}  $contact  Dữ liệu liên hệ mới
     * @throws RuntimeException  Khi tình trạng khách đã thay đổi (bị chặn), salesperson không có quyền, hoặc dữ liệu liên hệ vi phạm luật Lead.
     */
    public function create(Lead $lead, Employee $salesperson, array $contact = []): Opportunity
    {
        return DB::transaction(function () use ($lead, $salesperson, $contact) {
            // Khóa dòng Lead rồi đọc lại cùng cơ hội để tránh trùng đồng thời
            $locked = Lead::query()
                ->whereKey($lead->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->load($this->leadDetails());

            // Kiểm tra quyền sau khi khóa: salesperson phải từng có cơ hội với khách này
            if (! $locked->opportunities->contains('employee_id', $salesperson->employee_id)) {
                throw new RuntimeException(__('opportunities.create.error_no_permission'));
            }

            // Kiểm tra lại tình trạng sau khi khóa
            $state = $this->duplicate->stateOf($locked);

            if ($state === LeadDuplicateService::IN_QUEUE) {
                throw new RuntimeException(__('opportunities.create.blocked_in_queue'));
            }

            if ($state === LeadDuplicateService::IN_PROGRESS) {
                $active = $locked->opportunities->firstWhere('status', Opportunity::STATUS_IN_PROGRESS);

                if ($active && $active->employee_id === $salesperson->employee_id) {
                    throw new RuntimeException(__('opportunities.create.blocked_own_in_progress', [
                        'opp' => $active->opportunity_id,
                    ]));
                }

                $owner = $active?->employee;
                throw new RuntimeException(__('opportunities.create.blocked_other_in_progress', [
                    'name'  => $owner?->full_name ?? '-',
                    'phone' => $owner?->phone ?? __('opportunities.create.no_phone'),
                ]));
            }

            // Cập nhật thông tin liên hệ nếu có thay đổi (cùng transaction với tạo cơ hội)
            $contactUpdate = $this->resolveContactUpdate($locked, $contact);
            if ($contactUpdate !== []) {
                $locked->update($contactUpdate);
            }

            // Tạo cơ hội mới — giống luồng assignToSalespersons: insert trực tiếp, không ghi StageHistory ban đầu
            [$id] = Opportunity::nextIds(1);

            Opportunity::insert([
                'opportunity_id'  => $id,
                'conversion_date' => now(),
                'status'          => Opportunity::STATUS_IN_PROGRESS,
                'lead_id'         => $locked->lead_id,
                'stage_id'        => Stage::UNTOUCHED,
                'employee_id'     => $salesperson->employee_id,
            ]);

            return Opportunity::find($id);
        });
    }

    /**
     * Xác định các trường liên hệ cần cập nhật trên Lead.
     * Chỉ cập nhật phone / email / source_url (CONTACT_FIELDS trừ full_name).
     * Kiểm tra bản ghi sau khi merge theo luật hiện có của Lead (LeadImportService::rules()).
     *
     * @param  array{phone?: string, email?: string, source_url?: string}  $contact
     * @return array<string, string|null>  Các trường thực sự thay đổi (để update), rỗng nếu không có gì đổi.
     * @throws RuntimeException  Nếu dữ liệu mới vi phạm luật Lead (VD: kênh Facebook nhưng xóa source_url).
     */
    public function resolveContactUpdate(Lead $lead, array $contact): array
    {
        // Chỉ các trường được phép sửa từ form tạo cơ hội (không cho sửa full_name qua đây)
        $allowed = array_diff(LeadDuplicateService::CONTACT_FIELDS, ['full_name']);

        $update = [];

        foreach ($allowed as $field) {
            if (! array_key_exists($field, $contact)) {
                continue;
            }

            $new = $contact[$field] === '' ? null : $contact[$field];
            $old = $lead->$field;

            // Bỏ qua nếu giá trị không thay đổi (dùng sameValue để so sánh email/URL đúng chuẩn)
            if ($old === $new || ($new !== null && $this->duplicate->sameValue($field, $old, $new))) {
                continue;
            }

            $update[$field] = $new;
        }

        if ($update === []) {
            return [];
        }

        // Kiểm tra bản ghi sau khi merge không vi phạm luật Lead
        $merged = array_merge(
            $lead->only(array_keys(array_flip(LeadImportService::COLUMNS))),
            $update,
        );

        $validator = Validator::make(
            $merged,
            $this->import->rules(),
            $this->import->messages(),
            $this->import->attributes(),
        );

        if ($validator->fails()) {
            throw new RuntimeException($validator->errors()->first());
        }

        return $update;
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Tên khóa học của các học viên đang học (Student::Studying) của khách.
     *
     * @return Collection<int, string>
     */
    public function studyingCourses(Lead $lead): Collection
    {
        return $lead->opportunities
            ->filter(fn (Opportunity $opp) => $opp->student?->status === Student::STATUS_STUDYING)
            ->flatMap(fn (Opportunity $opp) => $opp->quotations
                ->flatMap(fn ($q) => $q->details->map(fn ($d) => $d->course?->course_name ?? $d->course_id)))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Các khoản nợ còn lại theo khóa học: đơn đã đóng tiền ít nhất một lần (paid_amount > 0),
     * còn remaining_amount > 0. Trả về Collection của ['course' => string, 'remaining' => float].
     *
     * @return Collection<int, array{course: string, remaining: float}>
     */
    public function outstandingDebts(Lead $lead): Collection
    {
        $debts = collect();

        foreach ($lead->opportunities as $opp) {
            foreach ($opp->quotations as $quotation) {
                $order = $quotation->order;

                if (! $order
                    || (float) $order->paid_amount <= 0
                    || (float) $order->remaining_amount <= 0) {
                    continue;
                }

                // Tên khóa học từ chi tiết báo giá
                $courses = $quotation->details
                    ->map(fn ($d) => $d->course?->course_name ?? $d->course_id)
                    ->filter()
                    ->implode(', ') ?: '-';

                $debts->push([
                    'course'    => $courses,
                    'remaining' => (float) $order->remaining_amount,
                ]);
            }
        }

        return $debts;
    }

    /**
     * Quan hệ cần load cho Lead để xét tình trạng và cảnh báo.
     */
    protected function leadDetails(): array
    {
        return [
            'opportunities'                              => fn ($q) => $q->orderByDesc('conversion_date'),
            'opportunities.employee:employee_id,full_name,phone',
            'opportunities.student:student_id,status,opportunity_id',
            'opportunities.quotations.details.course:course_id,course_name',
            'opportunities.quotations.order',
        ];
    }
}
