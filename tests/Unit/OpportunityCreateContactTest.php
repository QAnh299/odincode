<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Services\LeadDuplicateService;
use App\Services\LeadImportService;
use App\Services\OpportunityCreateService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit test cho OpportunityCreateService::resolveContactUpdate().
 *
 * resolveContactUpdate dùng Validator::make() — cần Laravel container.
 * Bootstrap app một lần qua setUpBeforeClass để tránh overhead DB.
 */
class OpportunityCreateContactTest extends TestCase
{
    // ── Bootstrap Laravel container (chỉ một lần) ────────────────────

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (! function_exists('app') || ! app()->bound('validator')) {
            $app = require __DIR__.'/../../bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function makeLead(array $attrs = []): Lead
    {
        $defaults = [
            'lead_id'        => 'LEAD001',
            'full_name'      => 'Nguyễn Văn A',
            'phone'          => '0901234567',
            'email'          => null,
            'source_url'     => null,
            'source_name'    => 'Facebook',
            'contact_method' => 'Zalo',
            'branch_id'      => 'BR001',
        ];

        $lead = new Lead();
        foreach (array_merge($defaults, $attrs) as $key => $value) {
            $lead->$key = $value;
        }

        return $lead;
    }

    /**
     * Tạo service với LeadImportService mock: rules() thật, messages/attributes rỗng
     * (không cần __() dịch để chạy được trong môi trường test).
     */
    private function makeService(): OpportunityCreateService
    {
        $import = $this->createMock(LeadImportService::class);

        // Lấy rules() từ class thật (không dùng __())
        $realRules = (new class () extends LeadImportService {
            public function __construct()
            {
            }
        })->rules();

        $import->method('rules')->willReturn($realRules);
        $import->method('messages')->willReturn([]);
        $import->method('attributes')->willReturn([]);

        return new OpportunityCreateService(new LeadDuplicateService(), $import);
    }

    // ── Không có thay đổi ─────────────────────────────────────────────

    public function test_returns_empty_when_no_contact_data(): void
    {
        $result = $this->makeService()->resolveContactUpdate($this->makeLead(), []);

        $this->assertSame([], $result);
    }

    public function test_returns_empty_when_phone_unchanged(): void
    {
        $lead   = $this->makeLead(['phone' => '0901234567']);
        $result = $this->makeService()->resolveContactUpdate($lead, ['phone' => '0901234567']);

        $this->assertSame([], $result);
    }

    public function test_returns_empty_when_email_unchanged_case_insensitive(): void
    {
        $lead   = $this->makeLead(['email' => 'test@example.com']);
        $result = $this->makeService()->resolveContactUpdate($lead, ['email' => 'TEST@example.com']);

        $this->assertSame([], $result);
    }

    // ── Cập nhật hợp lệ ──────────────────────────────────────────────

    public function test_updates_phone_when_changed(): void
    {
        $lead   = $this->makeLead(['phone' => '0901234567', 'contact_method' => 'Zalo']);
        $result = $this->makeService()->resolveContactUpdate($lead, ['phone' => '0909999999']);

        $this->assertSame(['phone' => '0909999999'], $result);
    }

    public function test_updates_email_when_changed(): void
    {
        $lead   = $this->makeLead(['email' => 'old@example.com']);
        $result = $this->makeService()->resolveContactUpdate($lead, ['email' => 'new@example.com']);

        $this->assertSame(['email' => 'new@example.com'], $result);
    }

    public function test_updates_source_url_when_changed(): void
    {
        $lead = $this->makeLead([
            'source_url'     => 'https://facebook.com/old',
            'contact_method' => 'Facebook',
        ]);
        $result = $this->makeService()->resolveContactUpdate($lead, [
            'source_url' => 'https://facebook.com/new',
        ]);

        $this->assertSame(['source_url' => 'https://facebook.com/new'], $result);
    }

    public function test_sets_email_to_null_when_empty_string(): void
    {
        $lead   = $this->makeLead(['email' => 'old@example.com', 'contact_method' => 'Zalo']);
        $result = $this->makeService()->resolveContactUpdate($lead, ['email' => '']);

        $this->assertSame(['email' => null], $result);
    }

    public function test_updates_multiple_fields_at_once(): void
    {
        $lead = $this->makeLead([
            'phone'          => '0901234567',
            'email'          => null,
            'contact_method' => 'Phone',
        ]);
        $result = $this->makeService()->resolveContactUpdate($lead, [
            'phone' => '0909111111',
            'email' => 'new@example.com',
        ]);

        $this->assertSame(['phone' => '0909111111', 'email' => 'new@example.com'], $result);
    }

    // ── full_name không được phép ─────────────────────────────────────

    public function test_ignores_full_name_field(): void
    {
        $result = $this->makeService()->resolveContactUpdate($this->makeLead(), [
            'full_name' => 'Tên Mới',
            'email'     => 'new@example.com',
        ]);

        $this->assertArrayNotHasKey('full_name', $result);
        $this->assertArrayHasKey('email', $result);
    }

    // ── Validation lỗi ───────────────────────────────────────────────

    public function test_throws_when_phone_format_invalid(): void
    {
        $this->expectException(RuntimeException::class);

        $this->makeService()->resolveContactUpdate(
            $this->makeLead(['contact_method' => 'Zalo']),
            ['phone' => '123']
        );
    }

    public function test_throws_when_facebook_channel_loses_source_url(): void
    {
        $this->expectException(RuntimeException::class);

        $this->makeService()->resolveContactUpdate(
            $this->makeLead(['contact_method' => 'Facebook', 'source_url' => 'https://facebook.com/test']),
            ['source_url' => '']
        );
    }

    public function test_throws_when_email_format_invalid(): void
    {
        $this->expectException(RuntimeException::class);

        $this->makeService()->resolveContactUpdate(
            $this->makeLead(),
            ['email' => 'not-an-email']
        );
    }
}
