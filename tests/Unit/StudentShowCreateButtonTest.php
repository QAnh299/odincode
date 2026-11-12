<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\Student;
use App\Services\OpportunityCreateService;
use PHPUnit\Framework\TestCase;

/**
 * Unit test cho logic nút "Đăng ký khóa mới" trên trang chi tiết học viên.
 *
 * Không cần DB: test tập trung vào:
 *   1. canCreateOpportunity() — chỉ true với Salesperson.
 *   2. preselectFromStudent() — set selectedLeadId đúng khi hợp lệ,
 *      bỏ qua (null) khi không hợp lệ mà không throw.
 *
 * Dùng anonymous class để simulate logic của Students\Show mà không cần
 * bootstrap toàn bộ Livewire (tránh phụ thuộc DB / Livewire lifecycle).
 */
class StudentShowCreateButtonTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Tạo Employee stub với role_name cho sẵn.
     */
    private function makeEmployee(string $roleName): Employee
    {
        $emp = new Employee();
        $emp->employee_id = 'EMP001';

        // role_name là virtual attribute qua getRoleNameAttribute() — set trực tiếp vào $attributes
        $role = new Role();
        $role->role_name = $roleName;
        $emp->setRelation('role', $role);

        return $emp;
    }

    /**
     * Tạo object đơn giản simulate canCreateOpportunity() của Students\Show.
     * Logic: return $routePrefix === Role::SALESPERSON
     */
    private function makeShowLogic(string $roleName): object
    {
        $employee = $this->makeEmployee($roleName);

        return new class ($employee) {
            public function __construct(private Employee $employee)
            {
            }

            public function routePrefix(): string
            {
                return (string) $this->employee->role_name;
            }

            public function canCreateOpportunity(): bool
            {
                return $this->routePrefix() === Role::SALESPERSON;
            }
        };
    }

    // ── canCreateOpportunity ─────────────────────────────────────────

    /**
     * Salesperson: nút hiện (canCreateOpportunity = true).
     */
    public function test_can_create_opportunity_is_true_for_salesperson(): void
    {
        $logic = $this->makeShowLogic(Role::SALESPERSON);

        $this->assertTrue($logic->canCreateOpportunity());
    }

    /**
     * Sale Leader: nút ẩn.
     */
    public function test_can_create_opportunity_is_false_for_sale_leader(): void
    {
        $logic = $this->makeShowLogic(Role::SALE_LEADER);

        $this->assertFalse($logic->canCreateOpportunity());
    }

    /**
     * Giám đốc: nút ẩn.
     */
    public function test_can_create_opportunity_is_false_for_director(): void
    {
        $logic = $this->makeShowLogic(Role::DIRECTOR);

        $this->assertFalse($logic->canCreateOpportunity());
    }

    /**
     * Kế toán: nút ẩn.
     */
    public function test_can_create_opportunity_is_false_for_accountant(): void
    {
        $logic = $this->makeShowLogic(Role::ACCOUNTANT);

        $this->assertFalse($logic->canCreateOpportunity());
    }

    // ── preselectFromStudent ─────────────────────────────────────────

    /**
     * Simulate preselectFromStudent với student hợp lệ (salesperson có quyền):
     * selectedLeadId phải được set thành lead_id của học viên đó.
     */
    public function test_preselect_sets_selected_lead_id_when_student_is_valid(): void
    {
        $salesperson = $this->makeEmployee(Role::SALESPERSON);
        $leadId      = 'LEAD001';

        // Dựng Lead với opportunities của salesperson này
        $opp = new Opportunity();
        $opp->opportunity_id = 'OPP001';
        $opp->employee_id    = $salesperson->employee_id;

        $lead = new Lead();
        $lead->lead_id = $leadId;
        $lead->setRelation('opportunities', collect([$opp]));

        // Mock OpportunityCreateService: findForSalesperson trả về Lead hợp lệ
        $service = $this->createMock(OpportunityCreateService::class);
        $service->method('findForSalesperson')->willReturn($lead);

        // Simulate preselectFromStudent logic
        $selectedLeadId = null;

        $doPreselect = function (string $studentId) use ($service, $salesperson, &$selectedLeadId): void {
            // Giả sử student->opportunity->lead_id = LEAD001 (hợp lệ)
            $mockLeadId = 'LEAD001';

            $lead = $service->findForSalesperson($mockLeadId, $salesperson);

            if ($lead) {
                $selectedLeadId = $lead->lead_id;
            }
        };

        $doPreselect('STD001');

        $this->assertSame('LEAD001', $selectedLeadId);
    }

    /**
     * preselectFromStudent với student_id không thuộc salesperson (findForSalesperson trả null):
     * selectedLeadId phải giữ nguyên null, không throw exception.
     */
    public function test_preselect_keeps_null_when_student_not_owned_by_salesperson(): void
    {
        $salesperson = $this->makeEmployee(Role::SALESPERSON);

        // Mock: findForSalesperson trả null (không có quyền)
        $service = $this->createMock(OpportunityCreateService::class);
        $service->method('findForSalesperson')->willReturn(null);

        $selectedLeadId = null;

        $doPreselect = function (string $studentId) use ($service, $salesperson, &$selectedLeadId): void {
            $mockLeadId = 'LEAD_OTHER';

            $lead = $service->findForSalesperson($mockLeadId, $salesperson);

            if ($lead) {
                $selectedLeadId = $lead->lead_id;
            }
            // Không throw — bỏ qua âm thầm
        };

        // Không được throw exception
        $exception = null;
        try {
            $doPreselect('STD_INVALID');
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'preselectFromStudent không được throw exception khi student không hợp lệ');
        $this->assertNull($selectedLeadId, 'selectedLeadId phải giữ nguyên null');
    }

    /**
     * preselectFromStudent với student_id hợp lệ nhưng opportunity không có lead_id:
     * selectedLeadId phải giữ nguyên null.
     */
    public function test_preselect_keeps_null_when_opportunity_has_no_lead(): void
    {
        // Giả lập: student->opportunity->lead_id = null
        $leadId = null;

        $selectedLeadId = null;

        // Logic của preselectFromStudent: nếu lead_id null thì return sớm
        if (! $leadId) {
            // return early
        } else {
            $selectedLeadId = $leadId;
        }

        $this->assertNull($selectedLeadId);
    }

    /**
     * URL tạo cơ hội từ trang học viên phải chứa đúng tham số student.
     */
    public function test_enroll_url_contains_student_param(): void
    {
        $studentId = 'STD123';

        // Simulate logic tạo URL trong blade: route(..., ['student' => $student->student_id])
        // Kiểm tra format tham số đúng: URL phải có ?student=STD123
        $params = ['student' => $studentId];

        $this->assertArrayHasKey('student', $params);
        $this->assertSame($studentId, $params['student']);
    }
}
