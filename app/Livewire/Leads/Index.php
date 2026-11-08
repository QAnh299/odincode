<?php

namespace App\Livewire\Leads;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Role;
use App\Services\LeadDuplicateService;
use App\Services\LeadImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Danh sách Lead + bộ lọc + thống kê theo bộ lọc.
 * Dùng chung cho Giám đốc, Sale Admin, Sale Leader.
 *
 * Quyền theo vai trò:
 *   - Giám đốc: chỉ xem, tất cả chi nhánh (lọc được theo chi nhánh).
 *   - Sale Admin: tất cả chi nhánh; có nút Thêm, Import Excel, Phân chia Lead cho Sale Team.
 *   - Sale Leader: chỉ Lead thuộc chi nhánh của mình; có nút Phân chia Lead cho Salesperson.
 * Thêm Lead (popup), Import Excel (tải file mẫu, tải lên, trả file kết quả);
 * Phân chia Lead là popup riêng (Leads\Distribute), xong thì báo về qua sự kiện 'leads-distributed'.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    const PER_PAGE = 10;

    // Tiến độ phân chia (lọc từ thẻ thống kê, không phải trạng thái Lead):
    // chưa phân cho đội / đội đã nhận, chờ phân cho Salesperson
    const UNASSIGNED = 'Unassigned';

    const PENDING_SALESPERSON = 'PendingSales';

    const ASSIGN_FILTERS = [self::UNASSIGNED, self::PENDING_SALESPERSON];

    // Các bộ lọc có thể bị sửa từ client → chỉ áp dụng giá trị nằm trong danh sách hợp lệ

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    // Tiến độ phân chia: '' | ASSIGN_FILTERS
    #[Url(except: '')]
    public string $assign = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $method = '';

    // Chi nhánh (Giám đốc, Sale Admin) - '' = Tất cả
    #[Url(except: '')]
    public string $branch = '';

    // Khoảng ngày tạo (Y-m-d)
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    const FILTERS = ['search', 'status', 'assign', 'source', 'method', 'branch', 'from', 'to'];

    // Popup đang mở: '' | 'create' | 'import'
    public string $modal = '';

    // Dữ liệu popup Thêm Lead
    public array $form = [];

    // Khách cũ khớp với dữ liệu đang nhập (LeadDuplicateService): ['case', 'message', 'lead' => mã Lead cùng người | null,
    // 'leads' => [['lead_id', 'name', 'learner', 'state', 'owner', 'url'], ...]]; null = chưa kiểm tra / không trùng
    #[Locked]
    public ?array $duplicate = null;

    // Các trường được chọn để cập nhật vào Lead cũ (cùng người, Lead đang chờ phân / đang tư vấn)
    public array $updateFields = [];

    // File Excel tải lên (popup Import)
    public $file = null;

    // Kết quả import gần nhất: total, success, failed, result (đường dẫn file kết quả)
    #[Locked]
    public ?array $importResult = null;

    #[Locked]
    public string $importError = '';

    // Import 2 bước - bảng xem trước (LeadImportService::preview): mã file tạm, cách xử lý đã chọn [dòng => cách xử lý],
    // bộ lọc (all | review | invalid | new), trang, dòng đang mở so sánh
    #[Locked]
    public string $previewToken = '';

    public array $decisions = [];

    public string $previewFilter = 'review';

    public int $previewPage = 1;

    public ?int $previewOpen = null;

    const PREVIEW_PER_PAGE = 20;

    // Thông báo sau khi thêm / import: ['type' => success|warning|danger, 'text' => ...]
    public ?array $notice = null;

    public function updated(string $property): void
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }

        // Sửa dữ liệu popup Thêm Lead → kết quả kiểm tra trùng cũ không còn đúng
        if (str_starts_with($property, 'form')) {
            $this->duplicate = null;
        }
    }

    /**
     * Bấm vào thẻ thống kê: lọc theo trạng thái hoặc tiến độ phân chia (chỉ một thẻ), bấm lại thì bỏ lọc.
     */
    public function filterCard(string $key): void
    {
        $active = $this->activeCard() === $key;
        $this->status = ! $active && in_array($key, Lead::STATUSES, true) ? $key : '';
        $this->assign = ! $active && in_array($key, self::ASSIGN_FILTERS, true) ? $key : '';
        $this->resetPage();
    }

    /**
     * Thẻ thống kê đang chọn ('' = Tổng Lead).
     */
    public function activeCard(): string
    {
        return $this->assign !== '' ? $this->assign : $this->status;
    }

    public function resetFilters(): void
    {
        $this->reset(...self::FILTERS);
        $this->resetPage();
    }

    #[Computed]
    public function routePrefix(): string
    {
        return (string) Auth::user()->role_name;
    }

    /**
     * Có nút Phân chia Lead (Sale Admin, Sale Leader).
     */
    #[Computed]
    public function canManage(): bool
    {
        return in_array($this->routePrefix, [Role::SALE_ADMIN, Role::SALE_LEADER], true);
    }

    /**
     * Có nút Thêm Lead / Import Excel - chỉ Sale Admin.
     */
    #[Computed]
    public function canCreate(): bool
    {
        return $this->routePrefix === Role::SALE_ADMIN;
    }

    /**
     * Có nút "Phân Lead" ở dòng (xét nhanh trên cơ hội gần nhất đã nạp sẵn; popup Leads\AssignOne kiểm tra lại đầy đủ):
     * Sale Admin - Lead chưa phân cho đội; Sale Leader - Lead đội mình đang giữ, chờ phân cho Salesperson.
     */
    public function canAssign(Lead $lead): bool
    {
        if ($lead->status !== Lead::STATUS_NEW) {
            return false;
        }

        $latest = $lead->latestOpportunity;
        $active = $latest?->status === Opportunity::STATUS_IN_PROGRESS;

        return match ($this->routePrefix) {
            Role::SALE_ADMIN  => ! $active,
            Role::SALE_LEADER => $active && $latest->stage_id === \App\Models\Stage::UNTOUCHED
                && $latest->employee_id === Auth::user()->employee?->employee_id,
            default           => false,
        };
    }

    protected function authorizeCreate(): void
    {
        abort_unless($this->canCreate, 403);
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->duplicate = null;
        $this->resetValidation();
    }

    /* ── Thêm Lead ────────────────────────────────────────────────────── */

    public function openCreate(): void
    {
        $this->authorizeCreate();

        $this->form = [
            'full_name'      => '',
            'phone'          => '',
            'email'          => '',
            'source_name'    => '',
            'source_url'     => '',
            'contact_method' => '',
            'branch_id'      => '',
        ];
        $this->duplicate = null;
        $this->resetValidation();
        $this->modal = 'create';
    }

    /**
     * Lưu Lead: kiểm tra trùng khách cũ trước; không trùng thì tạo luôn, trùng thì hiện lựa chọn trong popup.
     */
    public function saveLead(LeadImportService $service, LeadDuplicateService $duplicates): void
    {
        $this->authorizeCreate();

        $validated = $this->validatedForm($service);
        $match = $duplicates->check($validated);

        if ($match['case'] === LeadDuplicateService::NEW) {
            $this->createLead($service, $validated);

            return;
        }

        $this->duplicate = $this->describeMatch($duplicates, $match, $validated);
    }

    /**
     * "Người học khác": vẫn tạo Lead mới dù trùng liên hệ (VD con thứ hai của phụ huynh).
     */
    public function createAsOther(LeadImportService $service, LeadDuplicateService $duplicates): void
    {
        $this->authorizeCreate();

        // Dữ liệu vừa bị sửa (updated() đã xoá kết quả cũ) → kiểm tra lại thay vì tạo luôn
        if (! $this->duplicate) {
            $this->saveLead($service, $duplicates);

            return;
        }

        $this->createLead($service, $this->validatedForm($service));
    }

    /**
     * "Cùng người" với Lead $leadId: không còn cơ hội đang tư vấn → đưa Lead cũ về hàng chờ phân công;
     * còn thì báo tình trạng của Lead đó (không tạo Lead mới).
     */
    public function sameAs(string $leadId, LeadImportService $service, LeadDuplicateService $duplicates): void
    {
        $this->authorizeCreate();

        if (! $this->duplicate) {
            $this->saveLead($service, $duplicates);

            return;
        }

        abort_unless(in_array($leadId, array_column($this->duplicate['leads'], 'lead_id'), true), 403);

        $validated = $this->validatedForm($service);
        $lead = $duplicates->find($leadId);
        abort_unless($lead, 404);

        $state = $duplicates->stateOf($lead);

        if ($state !== LeadDuplicateService::REOPEN) {
            // Đã xác nhận cùng người → không còn hỏi "người học khác"; hiện thông tin khác để cập nhật vào Lead cũ
            $this->duplicate = $this->describeMatch($duplicates, ['case' => $state, 'lead' => $lead, 'leads' => collect([$lead])], $validated, confirmed: true);

            return;
        }

        try {
            $result = $duplicates->reopen($lead, $validated);
        } catch (RuntimeException) {
            // Lead vừa đổi tình trạng (VD vừa được phân) → hiện lại tình trạng mới
            $lead = $duplicates->find($leadId);
            $this->duplicate = $this->describeMatch($duplicates, ['case' => $duplicates->stateOf($lead), 'lead' => $lead, 'leads' => collect([$lead])], $validated, confirmed: true);

            return;
        }

        $notes = $duplicates->reopenNotes($result);
        $text = __('leads.duplicate.reopened', ['id' => $result['lead']->lead_id, 'name' => $result['lead']->full_name]);

        $this->modal = '';
        $this->duplicate = null;
        // Có thông tin khác Lead cũ → hiện thành cảnh báo trên trang để Sale Admin đọc kỹ
        $this->announce(['type' => $notes ? 'warning' : 'success', 'text' => trim($text.' '.implode(' ', $notes))]);
        $this->resetPage();
    }

    /**
     * Cùng người, Lead đang chờ phân: cập nhật các trường đã chọn vào Lead cũ (không tạo Lead mới).
     */
    public function updateExisting(LeadImportService $service, LeadDuplicateService $duplicates): void
    {
        $this->authorizeCreate();

        if (! $this->duplicate || ! $this->duplicate['lead']) {
            $this->saveLead($service, $duplicates);

            return;
        }

        $validated = $this->validatedForm($service);

        try {
            $result = $duplicates->updateExisting($this->duplicate['lead'], $validated, $this->updateFields, $service);
        } catch (RuntimeException $e) {
            $this->addError('duplicate', $e->getMessage());

            return;
        }

        if (! $result['fields']) {
            $this->addError('duplicate', __('leads.duplicate.update_none'));

            return;
        }

        $fields = implode(', ', array_map(fn ($field) => $this->fieldLabel($field), $result['fields']));

        $this->modal = '';
        $this->duplicate = null;
        $this->announce(['type' => 'success', 'text' => __('leads.duplicate.updated', ['id' => $result['lead']->lead_id, 'fields' => $fields])]);
        $this->resetPage();
    }

    /**
     * Nhãn trường trong bảng so sánh: chi nhánh hiện tên cơ sở nên ghi "Chi nhánh" thay vì "Mã chi nhánh".
     */
    protected function fieldLabel(string $field): string
    {
        return $field === 'branch_id' ? __('leads.branch') : __('leads.field.'.$field);
    }

    /**
     * Giá trị hiển thị trong bảng so sánh: chi nhánh hiện tên, kênh liên hệ hiện nhãn.
     */
    protected function displayValue(string $field, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return match ($field) {
            'branch_id'      => $this->branches->firstWhere('branch_id', $value)?->branch_name ?? $value,
            'contact_method' => __('leads.method.'.$value),
            default          => $value,
        };
    }

    protected function validatedForm(LeadImportService $service): array
    {
        $data = $service->normalize($this->form);

        return Validator::make($data, $service->rules(), $service->messages(), $service->attributes())
            ->validate();
    }

    protected function createLead(LeadImportService $service, array $validated): void
    {
        $lead = $service->create($validated);

        $this->modal = '';
        $this->duplicate = null;
        $this->announce(['type' => 'success', 'text' => __('leads.form.created', ['id' => $lead->lead_id, 'name' => $lead->full_name])]);
        $this->resetPage();
    }

    /**
     * Kết quả kiểm tra trùng → dữ liệu hiển thị trong popup (giữ trong state Livewire nên chỉ gồm giá trị đơn giản).
     */
    protected function describeMatch(LeadDuplicateService $duplicates, array $match, array $data, bool $confirmed = false): array
    {
        $leads = $match['lead'] ? collect([$match['lead']]) : $match['leads'];

        // Đã xác định cùng người (khớp liên hệ + họ tên, hoặc bấm "Cùng người") và Lead đang chờ phân / đang tư vấn
        // → bảng so sánh cho chọn cập nhật (LeadDuplicateService::updatableFields); còn lại bảng chỉ để đối chiếu
        $updatable = $match['lead'] !== null
            && in_array($match['case'], [LeadDuplicateService::IN_QUEUE, LeadDuplicateService::IN_PROGRESS], true);

        $items = $leads->map(function (Lead $lead) use ($duplicates, $data, $updatable) {
            $active = $lead->opportunities->firstWhere('status', Opportunity::STATUS_IN_PROGRESS);
            $state = $duplicates->stateOf($lead);
            // Lead "Mới" mà đã có cơ hội đang mở = đội đang giữ, chờ phân Salesperson (người giữ là trưởng nhóm, không hiện tên)
            $waitingTeam = $state === LeadDuplicateService::IN_QUEUE && $active;

            return [
                'lead_id'   => $lead->lead_id,
                'name'      => $lead->full_name,
                'learner'   => $duplicates->learnerName($lead),
                'contact'   => collect([$lead->phone, $lead->email])->filter()->implode(' · '),
                'state'     => $waitingTeam ? 'in_queue_team' : $state,
                'owner'     => $waitingTeam ? null : $active?->employee?->full_name,
                'url'       => route($this->routePrefix.'.leads.show', $lead),
                // Salesperson đã tương tác → không cập nhật từ đây (bảng chỉ để xem)
                'contacted' => $updatable && $duplicates->contacted($lead),
                'returning' => $lead->isReturning(),
                'changes'   => collect($duplicates->changes($lead, $data, $updatable))
                    ->map(fn ($change, $field) => $change + [
                        'label'    => $this->fieldLabel($field),
                        'old_text' => $this->displayValue($field, $change['old']),
                        'new_text' => $this->displayValue($field, $change['new']),
                    ])->all(),
            ];
        })->values()->all();

        $this->updateFields = $updatable
            ? array_keys(array_filter($items[0]['changes'], fn ($change) => $change['editable']))
            : [];

        return [
            'case'      => $match['case'],
            // Chỉ ẩn "Tạo Lead mới" khi Sale Admin tự bấm "Cùng người". Hệ thống tự khớp liên hệ + họ tên vẫn giữ nút:
            // Lead có thể mang tên phụ huynh, phụ huynh đăng ký thêm cho con khác thì trùng cả tên lẫn SĐT
            'confirmed' => $confirmed,
            'updatable' => $updatable,
            'message'   => $duplicates->message($match['case'], $match['lead'], $match['leads']),
            'lead'      => $match['lead']?->lead_id,
            'leads'     => $items,
        ];
    }

    /* ── Import Excel ─────────────────────────────────────────────────── */

    public function openImport(): void
    {
        $this->authorizeCreate();

        // Mở lại popup: bỏ bảng xem trước cũ (nếu có)
        if ($this->previewToken !== '') {
            app(LeadImportService::class)->discardPreview($this->previewToken);
        }

        $this->reset('file', 'importResult', 'importError', 'previewToken', 'decisions', 'previewFilter', 'previewPage', 'previewOpen');
        unset($this->preview);
        $this->resetValidation();
        $this->modal = 'import';
    }

    public function downloadTemplate(LeadImportService $service)
    {
        $this->authorizeCreate();

        return response()
            ->download($service->template(), 'mau-import-lead.xlsx')
            ->deleteFileAfterSend();
    }

    public function updatedFile(): void
    {
        $this->reset('importResult', 'importError');
        $this->validateFile();
    }

    protected function validateFile(): void
    {
        $this->validate(
            ['file' => ['required', 'file', 'extensions:xlsx', 'max:5120']],
            [
                'file.required'   => __('leads.excel.file_required'),
                'file.extensions' => __('leads.excel.file_type'),
                'file.max'        => __('leads.excel.file_size'),
            ],
        );
    }

    /**
     * Bước 1: kiểm tra file → bảng xem trước (chưa ghi DB).
     */
    public function checkFile(LeadImportService $service): void
    {
        $this->authorizeCreate();
        $this->validateFile();

        $result = $service->preview($this->file->getRealPath());

        $this->file->delete();
        $this->reset('file');

        if (! $result['ok']) {
            $this->importError = $result['error'];

            return;
        }

        $this->previewToken = $result['token'];
        unset($this->preview);
        // Chọn sẵn cách xử lý mặc định cho từng dòng (ô chọn hiển thị đúng giá trị)
        $this->decisions = collect($this->preview['rows'] ?? [])->mapWithKeys(fn ($row) => [$row['line'] => $row['default']])->all();
        $this->previewFilter = 'review';
        $this->previewPage = 1;
        $this->previewOpen = null;
        unset($this->preview);

        // Không có dòng nào cần kiểm tra → mở sẵn tất cả
        if (! $this->previewCount('review')) {
            $this->previewFilter = 'all';
        }
    }

    /**
     * Bảng xem trước đang mở (đọc từ file tạm), null nếu không có / hết hạn.
     */
    #[Computed]
    public function preview(): ?array
    {
        return $this->previewToken !== '' ? app(LeadImportService::class)->loadPreview($this->previewToken) : null;
    }

    /**
     * Số dòng theo bộ lọc: all | review (cần kiểm tra: trùng trong file / trùng khách cũ) | invalid (lỗi dữ liệu) | new.
     */
    public function previewCount(string $filter): int
    {
        return count($this->previewRows($filter));
    }

    protected function previewRows(string $filter): array
    {
        $rows = $this->preview['rows'] ?? [];

        return array_values(match ($filter) {
            'review'  => array_filter($rows, fn ($r) => ! in_array($r['kind'], [LeadDuplicateService::NEW, LeadImportService::ROW_INVALID], true)),
            'invalid' => array_filter($rows, fn ($r) => $r['kind'] === LeadImportService::ROW_INVALID),
            'new'     => array_filter($rows, fn ($r) => $r['kind'] === LeadDuplicateService::NEW),
            default   => $rows,
        });
    }

    /**
     * Các dòng của trang hiện tại theo bộ lọc.
     */
    public function previewPageRows(): array
    {
        return array_slice($this->previewRows($this->previewFilter), ($this->previewPage - 1) * self::PREVIEW_PER_PAGE, self::PREVIEW_PER_PAGE);
    }

    public function previewPages(): int
    {
        return max(1, (int) ceil($this->previewCount($this->previewFilter) / self::PREVIEW_PER_PAGE));
    }

    public function setPreviewFilter(string $filter): void
    {
        $this->previewFilter = in_array($filter, ['all', 'review', 'invalid', 'new'], true) ? $filter : 'all';
        $this->previewPage = 1;
        $this->previewOpen = null;
    }

    public function gotoPreviewPage(int $page): void
    {
        $this->previewPage = min(max(1, $page), $this->previewPages());
        $this->previewOpen = null;
    }

    public function togglePreviewRow(int $line): void
    {
        $this->previewOpen = $this->previewOpen === $line ? null : $line;
    }

    /**
     * Cách xử lý đang chọn của một dòng (chọn sai / chưa chọn → mặc định).
     */
    public function decisionFor(array $row): string
    {
        $chosen = $this->decisions[$row['line']] ?? null;

        return in_array($chosen, $row['options'], true) ? $chosen : $row['default'];
    }

    /**
     * Tóm tắt sẽ ghi nhận theo lựa chọn hiện tại: số dòng tạo mới / cập nhật / về hàng chờ / bỏ qua.
     */
    public function previewSummary(): array
    {
        $summary = ['create' => 0, 'update' => 0, 'reopen' => 0, 'skip' => 0];

        foreach ($this->preview['rows'] ?? [] as $row) {
            $action = $this->decisionFor($row);
            $key = str_starts_with($action, LeadImportService::ACTION_SAME) ? 'update' : $action;
            $summary[$key] = ($summary[$key] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * Huỷ bảng xem trước, quay lại chọn file.
     */
    public function cancelPreview(LeadImportService $service): void
    {
        $this->authorizeCreate();

        if ($this->previewToken !== '') {
            $service->discardPreview($this->previewToken);
        }

        $this->reset('previewToken', 'decisions', 'previewFilter', 'previewPage', 'previewOpen', 'importError');
        unset($this->preview);
    }

    /**
     * Bước 2: ghi nhận theo lựa chọn từng dòng, tạo file kết quả.
     */
    public function confirmImport(LeadImportService $service): void
    {
        $this->authorizeCreate();

        $result = $service->commit($this->previewToken, $this->decisions);

        $this->reset('previewToken', 'decisions', 'previewFilter', 'previewPage', 'previewOpen');
        unset($this->preview);

        if (! $result['ok']) {
            $this->importError = $result['error'];

            return;
        }

        $this->importResult = $result;
        $this->announce(match (true) {
            $result['failed'] === 0 && $result['skipped'] === 0 => ['type' => 'success', 'text' => __('leads.excel.done_all', ['count' => $result['success']])],
            $result['success'] === 0 => ['type' => 'danger', 'text' => __('leads.import_review.done_none', ['skipped' => $result['skipped'], 'failed' => $result['failed']])],
            default => ['type' => 'warning', 'text' => __('leads.import_review.done_partial', [
                'total' => $result['total'], 'success' => $result['success'], 'skipped' => $result['skipped'], 'failed' => $result['failed'],
            ])],
        });

        $this->resetPage();
    }

    public function downloadResult()
    {
        $this->authorizeCreate();

        $path = $this->importResult['result'] ?? null;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            $this->importError = __('leads.excel.result_missing');

            return null;
        }

        return Storage::disk('local')->download($path, 'ket-qua-import-lead-'.now()->format('Ymd-His').'.xlsx');
    }

    /**
     * Popup Phân chia Lead báo đã phân xong.
     */
    #[On('leads-distributed')]
    public function distributed(array $notice): void
    {
        $this->announce($notice);
        $this->resetPage();
    }

    /**
     * Ghi nhận thông báo; thành công thì hiện toast thay vì banner trên trang.
     */
    private function announce(array $notice): void
    {
        $this->notice = $notice;

        if ($notice['type'] === 'success') {
            $this->dispatch('lead-toast', text: $notice['text']);
        }
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
    }

    #[Computed]
    public function canFilterBranch(): bool
    {
        return in_array($this->routePrefix, [Role::DIRECTOR, Role::SALE_ADMIN], true);
    }

    /**
     * Chi nhánh cố định của Sale Leader (null với vai trò khác).
     */
    #[Computed]
    public function fixedBranch(): ?Branch
    {
        if ($this->routePrefix !== Role::SALE_LEADER) {
            return null;
        }

        return Branch::find(Auth::user()->employee?->branch_id);
    }

    #[Computed]
    public function branches(): Collection
    {
        return $this->canFilterBranch
            ? Branch::query()->orderBy('branch_id')->get(['branch_id', 'branch_name'])
            : collect();
    }

    /**
     * Nguồn Lead cho bộ lọc Nguồn.
     */
    #[Computed]
    public function sources(): array
    {
        return Lead::SOURCES;
    }

    /**
     * Thống kê theo bộ lọc hiện tại (trừ bộ lọc trạng thái, để các thẻ trạng thái luôn có số).
     */
    #[Computed]
    public function stats(): array
    {
        $row = $this->filtered(Lead::query(), withStatus: false)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(status = ?) AS converted_count', [Lead::STATUS_CONVERTED])
            ->first();

        return [
            'all'                     => (int) $row->total,
            self::UNASSIGNED          => $this->filtered(Lead::query(), withStatus: false)->unassigned()->count(),
            self::PENDING_SALESPERSON => $this->filtered(Lead::query(), withStatus: false)->pendingSalesperson()->count(),
            Lead::STATUS_CONVERTED    => (int) $row->converted_count,
        ];
    }

    public function hasFilters(): bool
    {
        foreach (self::FILTERS as $filter) {
            if ($this->$filter !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Phạm vi dữ liệu theo vai trò - Lead::visibleTo().
     */
    protected function scoped(Builder $query): Builder
    {
        return $query->visibleTo(Auth::user()->employee);
    }

    /**
     * Phạm vi vai trò + các bộ lọc trên màn hình.
     */
    protected function filtered(Builder $query, bool $withStatus = true): Builder
    {
        $search = trim($this->search);

        return $this->scoped($query)
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('lead_id', 'like', $like)
                    ->orWhere('full_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->when($withStatus && in_array($this->status, Lead::STATUSES, true),
                fn ($q) => $q->where('status', $this->status))
            ->when($withStatus && $this->assign === self::UNASSIGNED,
                fn ($q) => $q->unassigned())
            ->when($withStatus && $this->assign === self::PENDING_SALESPERSON,
                fn ($q) => $q->pendingSalesperson())
            ->when(in_array($this->source, $this->sources, true),
                fn ($q) => $q->where('source_name', $this->source))
            ->when(in_array($this->method, Lead::CONTACT_METHODS, true),
                fn ($q) => $q->where('contact_method', $this->method))
            ->when($this->branches->contains('branch_id', $this->branch),
                fn ($q) => $q->where('branch_id', $this->branch))
            ->when($this->validDate($this->from), fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->validDate($this->to), fn ($q) => $q->whereDate('created_at', '<=', $this->to));
    }

    protected function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    public function render()
    {
        $leads = $this->filtered(Lead::query())
            ->with(['branch:branch_id,branch_name', 'latestOpportunity.employee:employee_id,full_name', 'latestOpportunity.employee.ledTeam:team_id,team_name,team_leader_id'])
            ->orderByDesc('created_at')
            ->orderByDesc('lead_id')
            ->paginate(self::PER_PAGE);

        return view('livewire.leads.index', [
            'leads' => $leads,
        ])->title(__('leads.title'));
    }
}
