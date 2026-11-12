<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;
use ZipArchive;

/**
 * Thêm Lead thủ công + Import Lead từ file Excel (HĐ1 - HĐ6, PhanLead.docx).
 *
 * - Dùng chung bộ kiểm tra dữ liệu cho popup Thêm Lead và từng dòng Excel.
 * - Import 2 bước: preview() đọc file, kiểm tra từng dòng (lỗi dữ liệu, trùng trong file, trùng khách cũ theo
 *   LeadDuplicateService - khớp liên hệ + họ tên) và lưu bảng xem trước, chưa ghi DB; Sale Admin chọn cách xử lý
 *   từng dòng (tạo mới / cùng người / cập nhật Lead cũ / đưa về hàng chờ / bỏ qua) rồi commit() mới ghi nhận
 *   và trả về file kết quả (mỗi dòng có cột Kết quả, Mã Lead, Ghi chú, Lý do lỗi).
 */
class LeadImportService
{
    public function __construct(protected LeadDuplicateService $duplicates)
    {
    }

    // Số dòng dữ liệu tối đa trong một file
    public const MAX_ROWS = 1000;

    // Thư mục lưu file kết quả trên disk local (storage/app/private)
    public const RESULT_DIR = 'lead-imports';

    // Cột trong file mẫu, theo thứ tự
    public const COLUMNS = ['full_name', 'phone', 'email', 'source_name', 'source_url', 'contact_method', 'branch_id'];

    // Cột luôn bắt buộc (đánh dấu * trên tiêu đề); SĐT, Link nguồn bắt buộc tuỳ kênh liên hệ
    public const REQUIRED = ['full_name', 'source_name', 'contact_method', 'branch_id'];

    // Ngăn cách mã và tên chi nhánh trong ô chọn của file mẫu
    public const BRANCH_SEPARATOR = ' - ';

    /**
     * Chuẩn hoá dữ liệu nhập: bỏ khoảng trắng, chuẩn SĐT, kênh liên hệ, mã chi nhánh.
     */
    public function normalize(array $data): array
    {
        $value = fn ($key) => trim((string) ($data[$key] ?? ''));

        return [
            'full_name'      => preg_replace('/\s+/u', ' ', $value('full_name')),
            'phone'          => $value('phone') !== '' ? $this->normalizePhone($value('phone')) : null,
            'email'          => $value('email') !== '' ? Str::lower($value('email')) : null,
            'source_name'    => $this->normalizeSource($value('source_name')),
            'source_url'     => $value('source_url') !== '' ? $value('source_url') : null,
            'contact_method' => $this->normalizeMethod($value('contact_method')),
            // Ô chọn trong file mẫu có dạng "BR001 - Tên chi nhánh" → chỉ lấy mã
            'branch_id'      => Str::upper(trim(Str::before($value('branch_id'), self::BRANCH_SEPARATOR))),
        ];
    }

    /**
     * Nhãn chi nhánh trong ô chọn của file mẫu: "BR001 - Trụ sở chính".
     */
    public function branchLabel(Branch $branch): string
    {
        return $branch->branch_id.self::BRANCH_SEPARATOR.$branch->branch_name;
    }

    /**
     * 0912 345 678, 0912.345.678, +84912345678, 912345678 (Excel mất số 0) → 0912345678.
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s.\-()]/', '', $phone);

        if (preg_match('/^\+?84(\d{9})$/', $phone, $m)) {
            return '0'.$m[1];
        }

        if (preg_match('/^[1-9]\d{8}$/', $phone)) {
            return '0'.$phone;
        }

        return $phone;
    }

    /**
     * Cho phép nhập không phân biệt hoa thường, hoặc nhập "Điện thoại".
     */
    public function normalizeMethod(string $method): string
    {
        $key = Str::lower(Str::ascii($method));

        foreach (Lead::CONTACT_METHODS as $option) {
            if ($key === Str::lower($option) || $key === Str::lower(Str::ascii(__('leads.method.'.$option)))) {
                return $option;
            }
        }

        return $method;
    }

    /**
     * Cho phép nhập nguồn không phân biệt hoa thường, khoảng trắng (VD: "website odin", "Tiktok").
     */
    public function normalizeSource(string $source): string
    {
        $key = Str::lower(preg_replace('/\s+/u', '', Str::ascii($source)));

        foreach (Lead::SOURCES as $option) {
            if ($key === Str::lower(str_replace(' ', '', $option))) {
                return $option;
            }
        }

        return $source;
    }

    /**
     * Luật kiểm tra cho dữ liệu đã chuẩn hoá (theo ràng buộc bảng Leads trong odin.sql).
     */
    public function rules(): array
    {
        return [
            'full_name'      => ['required', 'string', 'max:150'],
            // Trùng khách cũ không kiểm tra ở đây mà ở LeadDuplicateService (cần khớp cả liên hệ và họ tên)
            'phone'        => ['nullable', 'required_if:contact_method,'.implode(',', Lead::METHODS_PHONE_REQUIRED), 'regex:/^0\d{9}$/'],
            'email'          => ['nullable', 'email', 'max:254'],
            'source_name'    => ['required', Rule::in(Lead::SOURCES)],
            // Bắt buộc khi liên hệ qua Facebook; nguồn nào (TikTok, Instagram…) cũng không bắt buộc nếu liên hệ qua kênh khác
            'source_url'     => ['nullable', 'required_if:contact_method,'.implode(',', Lead::METHODS_LINK_REQUIRED), 'string', 'max:2000'],
            'contact_method' => ['required', Rule::in(Lead::CONTACT_METHODS)],
            'branch_id'      => ['required', Rule::exists(Branch::class, 'branch_id')],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required_if' => __('leads.form.phone_required', ['methods' => $this->methodLabels(Lead::METHODS_PHONE_REQUIRED)]),
            'phone.regex'       => __('leads.form.phone_invalid'),
            'source_name.in'    => __('leads.form.source_invalid', ['values' => implode(', ', Lead::SOURCES)]),
            'source_url.required_if' => __('leads.form.source_url_required', ['methods' => $this->methodLabels(Lead::METHODS_LINK_REQUIRED)]),
            'contact_method.in' => __('leads.form.method_invalid', ['values' => implode(', ', Lead::CONTACT_METHODS)]),
            'branch_id.exists'  => __('leads.form.branch_invalid'),
        ];
    }

    protected function methodLabels(array $methods): string
    {
        return implode(', ', array_map(fn ($method) => __('leads.method.'.$method), $methods));
    }

    public function attributes(): array
    {
        return collect(self::COLUMNS)
            ->mapWithKeys(fn ($column) => [$column => __('leads.field.'.$column)])
            ->all();
    }

    public function create(array $data): Lead
    {
        return Lead::create($data + ['status' => Lead::STATUS_NEW]);
    }

    /* ── File mẫu ─────────────────────────────────────────────────────── */

    /**
     * Tạo file mẫu (.xlsx) vào file tạm và trả về đường dẫn.
     */
    public function template(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lead').'.xlsx';
        $writer = $this->writer($path);

        $branches = Branch::query()->orderBy('branch_id')->get(['branch_id', 'branch_name']);
        $branchLabels = $branches->map(fn ($branch) => $this->branchLabel($branch))->all();

        $writer->getCurrentSheet()->setName(__('leads.excel.sheet_data'));
        $writer->addRow($this->headerRow());

        $writer->addRow(Row::fromValues([
            'Nguyễn Văn A', '0912345678', 'nguyenvana@example.com', 'Facebook',
            'https://facebook.com/odin', 'Zalo', $branchLabels[0] ?? 'BR001',
        ], (new Style())->setFontItalic()->setFontColor(Color::rgb(107, 114, 128))));

        // Sheet hướng dẫn: mô tả cột + giá trị hợp lệ
        $writer->addNewSheetAndMakeItCurrent()->setName(__('leads.excel.sheet_guide'));
        $bold = (new Style())->setFontBold();

        $writer->addRow(Row::fromValues([__('leads.excel.guide_column'), __('leads.excel.guide_note')], $bold));
        foreach (self::COLUMNS as $column) {
            $writer->addRow(Row::fromValues([$this->heading($column), __('leads.excel.guide.'.$column)]));
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues([__('leads.excel.guide_sources')], $bold));
        foreach (Lead::SOURCES as $source) {
            $writer->addRow(Row::fromValues([$source]));
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues([__('leads.excel.guide_methods')], $bold));
        foreach (Lead::CONTACT_METHODS as $method) {
            $writer->addRow(Row::fromValues([$method, __('leads.method.'.$method)]));
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues([__('leads.excel.guide_branches')], $bold));
        $branches->each(fn ($branch) => $writer->addRow(Row::fromValues([$branch->branch_id, $branch->branch_name])));

        // Sheet ẩn chứa danh sách giá trị cho ô chọn: cột A = kênh liên hệ, cột B = chi nhánh, cột C = nguồn
        $writer->addNewSheetAndMakeItCurrent()->setName(__('leads.excel.sheet_lists'));
        for ($i = 0; $i < max(count(Lead::CONTACT_METHODS), count($branchLabels), count(Lead::SOURCES)); $i++) {
            $writer->addRow(Row::fromValues([Lead::CONTACT_METHODS[$i] ?? '', $branchLabels[$i] ?? '', Lead::SOURCES[$i] ?? '']));
        }

        $writer->close();

        $this->addDropdowns($path, count(Lead::CONTACT_METHODS), count($branchLabels), count(Lead::SOURCES));

        return $path;
    }

    /**
     * Thêm ô chọn (Data Validation dạng List) cho cột Nguồn Lead, Kênh liên hệ, Mã chi nhánh của sheet nhập liệu
     * và ẩn sheet danh sách. OpenSpout chưa hỗ trợ nên sửa trực tiếp XML trong file .xlsx.
     */
    protected function addDropdowns(string $path, int $methodCount, int $branchCount, int $sourceCount): void
    {
        $listSheet = __('leads.excel.sheet_lists');
        $ref = "'".str_replace("'", "''", $listSheet)."'";
        $lastRow = self::MAX_ROWS + 1;
        $column = fn (string $field) => chr(ord('A') + array_search($field, self::COLUMNS, true));

        $validation = fn (string $field, string $range, string $error) => sprintf(
            '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorStyle="stop"'
            .' errorTitle="%s" error="%s" sqref="%s2:%3$s%d"><formula1>%s!%s</formula1></dataValidation>',
            e(__('leads.field.'.$field)),
            e($error),
            $column($field),
            $lastRow,
            e($ref),
            $range,
        );

        $xml = '<dataValidations count="3">'
            .$validation(
                'source_name',
                '$C$1:$C$'.max($sourceCount, 1),
                __('leads.form.source_invalid', ['values' => implode(', ', Lead::SOURCES)])
            )
            .$validation(
                'contact_method',
                '$A$1:$A$'.max($methodCount, 1),
                __('leads.form.method_invalid', ['values' => implode(', ', Lead::CONTACT_METHODS)])
            )
            .$validation('branch_id', '$B$1:$B$'.max($branchCount, 1), __('leads.form.branch_invalid'))
            .'</dataValidations>';

        $zip = new ZipArchive();
        $zip->open($path);

        // <dataValidations> phải đứng trước các thẻ này theo chuẩn OOXML
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheet = preg_replace_callback(
            '#(?=<(?:hyperlinks|printOptions|pageMargins|pageSetup|headerFooter|drawing|legacyDrawing|tableParts|extLst)[\s>/]|</worksheet>)#',
            fn () => $xml,
            $sheet,
            1,
        );
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);

        $workbook = $zip->getFromName('xl/workbook.xml');
        $workbook = preg_replace(
            '#(<sheet name="'.preg_quote(e($listSheet), '#').'"[^>]*?)state="visible"#u',
            '$1state="hidden"',
            $workbook,
        );
        $zip->addFromString('xl/workbook.xml', $workbook);

        $zip->close();
    }

    /* ── Import: xem trước → xác nhận ─────────────────────────────────── */

    // Loại dòng trong bảng xem trước (ngoài các tình huống của LeadDuplicateService)
    public const ROW_INVALID = 'invalid';       // lỗi dữ liệu
    public const ROW_FILE_DUP = 'file_dup';     // trùng liên hệ + họ tên với dòng trước trong cùng file
    public const ROW_FILE_FAMILY = 'file_family'; // cùng liên hệ, khác họ tên với dòng trước trong file (người thân)

    // Xử lý một dòng (ô chọn trong bảng xem trước); 'same:<lead_id>' = cùng người với Lead đó
    public const ACTION_CREATE = 'create';
    public const ACTION_SKIP = 'skip';
    public const ACTION_REOPEN = 'reopen';
    public const ACTION_UPDATE = 'update';
    public const ACTION_SAME = 'same:';

    // Bảng xem trước được lưu tạm (JSON) trên disk local tới lúc xác nhận
    public const PREVIEW_TTL_HOURS = 6;

    /**
     * Bước 1: đọc file, kiểm tra từng dòng (lỗi dữ liệu, trùng trong file, trùng khách đã có) - chưa ghi gì vào DB.
     * Lưu bảng xem trước tạm trên disk local, trả về mã để xác nhận ở bước 2.
     *
     * @return array{ok: bool, error?: string, token?: string}
     */
    public function preview(string $path): array
    {
        try {
            $rows = $this->readRows($path);
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => __('leads.excel.error_unreadable')];
        }

        $header = array_shift($rows);

        if ($header === null || ! $this->validHeader($header)) {
            return ['ok' => false, 'error' => __('leads.excel.error_template')];
        }

        // Bỏ các dòng trống hoàn toàn, giữ số dòng gốc trên Excel (dòng 1 là tiêu đề)
        $rows = array_filter($rows, fn ($row) => implode('', array_map('trim', $row)) !== '');

        if ($rows === []) {
            return ['ok' => false, 'error' => __('leads.excel.error_empty')];
        }

        if (count($rows) > self::MAX_ROWS) {
            return ['ok' => false, 'error' => __('leads.excel.error_too_many', ['max' => self::MAX_ROWS])];
        }

        $branches = Branch::query()->pluck('branch_name', 'branch_id')->all();
        $seen = [];   // khoá liên hệ → [[dòng, họ tên chuẩn hoá], ...] của các dòng hợp lệ phía trên
        $preview = [];

        foreach ($rows as $index => $values) {
            $raw = array_combine(self::COLUMNS, array_pad(array_slice($values, 0, count(self::COLUMNS)), count(self::COLUMNS), ''));
            $preview[] = $this->previewRow($index + 2, $raw, $seen, $branches);
        }

        $token = (string) Str::uuid();
        Storage::disk('local')->put($this->previewPath($token), json_encode([
            'created_at' => now()->toIso8601String(),
            'rows'       => $preview,
        ], JSON_UNESCAPED_UNICODE));

        return ['ok' => true, 'token' => $token];
    }

    /**
     * Một dòng của bảng xem trước: dữ liệu, lỗi, tình huống phát hiện, các cách xử lý và cách xử lý mặc định.
     */
    protected function previewRow(int $line, array $raw, array &$seen, array $branches): array
    {
        $data = $this->normalize($raw);
        $errors = Validator::make($data, $this->rules(), $this->messages(), $this->attributes())->errors()->all();

        $row = ['line' => $line, 'values' => array_values($raw), 'data' => $data, 'errors' => $errors, 'leads' => [], 'ref_line' => null];

        if ($errors) {
            return $row + ['kind' => self::ROW_INVALID, 'options' => [self::ACTION_SKIP], 'default' => self::ACTION_SKIP];
        }

        // Trùng với dòng phía trên trong cùng file (chưa có trong DB nên LeadDuplicateService không thấy)
        $keys = array_filter([
            $data['phone'] ? 'p:'.$data['phone'] : null,
            $data['email'] ? 'e:'.Str::lower($data['email']) : null,
            ($fb = $this->duplicates->facebookKey($data['source_url'])) ? 'f:'.$fb : null,
        ]);
        $name = $this->duplicates->nameKey($data['full_name']);
        $earlier = collect($keys)->flatMap(fn ($key) => $seen[$key] ?? []);
        foreach ($keys as $key) {
            $seen[$key][] = [$line, $name];
        }

        if ($same = $earlier->first(fn ($e) => $e[1] === $name)) {
            return array_merge($row, ['kind' => self::ROW_FILE_DUP, 'ref_line' => $same[0],
                'options' => [self::ACTION_SKIP, self::ACTION_CREATE], 'default' => self::ACTION_SKIP]);
        }

        $match = $this->duplicates->check($data);
        $case = $match['case'];

        if ($case === LeadDuplicateService::NEW) {
            return array_merge($row, $earlier->isNotEmpty()
                ? ['kind' => self::ROW_FILE_FAMILY, 'ref_line' => $earlier->first()[0],
                    'options' => [self::ACTION_CREATE, self::ACTION_SKIP], 'default' => self::ACTION_CREATE]
                : ['kind' => LeadDuplicateService::NEW, 'options' => [self::ACTION_CREATE, self::ACTION_SKIP], 'default' => self::ACTION_CREATE]);
        }

        // Có khách cũ khớp liên hệ: bảng so sánh với từng Lead; cập nhật được khi đã xác định cùng người
        $sure = $match['lead'] !== null;
        $leads = ($sure ? collect([$match['lead']]) : $match['leads'])
            ->map(fn (Lead $lead) => $this->previewLead($lead, $data, $sure, $branches))
            ->values()->all();
        $canUpdate = $sure && in_array($case, [LeadDuplicateService::IN_QUEUE, LeadDuplicateService::IN_PROGRESS], true)
            && collect($leads[0]['changes'])->contains('editable', true);
        $same = collect($leads)->map(fn ($lead) => self::ACTION_SAME.$lead['lead_id'])->all();

        [$options, $default] = match ($case) {
            LeadDuplicateService::REOPEN       => [[self::ACTION_REOPEN, self::ACTION_CREATE, self::ACTION_SKIP], self::ACTION_REOPEN],
            LeadDuplicateService::IN_QUEUE,
            LeadDuplicateService::IN_PROGRESS  => [array_values(array_filter([self::ACTION_SKIP, $canUpdate ? self::ACTION_UPDATE : null, self::ACTION_CREATE])), self::ACTION_SKIP],
            // Chưa chắc cùng người: mặc định tạo mới (người học khác), có thể chọn "cùng người với LEADxxx"
            LeadDuplicateService::OTHER_PERSON => [[self::ACTION_CREATE, ...$same, self::ACTION_SKIP], self::ACTION_CREATE],
            default                            => [[self::ACTION_SKIP, ...$same, self::ACTION_CREATE], self::ACTION_SKIP], // AMBIGUOUS
        };

        return array_merge($row, ['kind' => $case, 'leads' => $leads, 'options' => $options, 'default' => $default]);
    }

    /**
     * Lead khớp liên hệ trong bảng xem trước: tình trạng + so sánh với dữ liệu dòng.
     */
    protected function previewLead(Lead $lead, array $data, bool $updatable, array $branches): array
    {
        $active = $lead->opportunities->firstWhere('status', Opportunity::STATUS_IN_PROGRESS);
        $state = $this->duplicates->stateOf($lead);
        $waitingTeam = $state === LeadDuplicateService::IN_QUEUE && $active;
        $display = fn (string $field, ?string $value) => match (true) {
            $value === null || $value === '' => '-',
            $field === 'branch_id'           => $branches[$value] ?? $value,
            $field === 'contact_method'      => __('leads.method.'.$value),
            default                          => $value,
        };

        return [
            'lead_id'   => $lead->lead_id,
            'name'      => $lead->full_name,
            'state'     => $waitingTeam ? 'in_queue_team' : $state,
            'owner'     => $waitingTeam ? null : $active?->employee?->full_name,
            // Lý do trường bị khoá khi cập nhật (hiện dưới bảng so sánh)
            'returning' => $lead->isReturning(),
            'contacted' => $this->duplicates->contacted($lead),
            'changes' => collect($this->duplicates->changes($lead, $data, $updatable))
                ->map(fn ($change, $field) => [
                    'label'    => $field === 'branch_id' ? __('leads.branch') : __('leads.field.'.$field),
                    'old'      => $display($field, $change['old']),
                    'new'      => $display($field, $change['new']),
                    'editable' => $change['editable'],
                ])->all(),
        ];
    }

    /**
     * Bảng xem trước đã lưu (null nếu không còn / hết hạn).
     */
    public function loadPreview(string $token): ?array
    {
        $path = $this->previewPath($token);

        if (! Str::isUuid($token) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $preview = json_decode(Storage::disk('local')->get($path), true);

        if (! $preview || now()->subHours(self::PREVIEW_TTL_HOURS)->gt($preview['created_at'] ?? now())) {
            Storage::disk('local')->delete($path);

            return null;
        }

        return $preview;
    }

    public function discardPreview(string $token): void
    {
        if (Str::isUuid($token)) {
            Storage::disk('local')->delete($this->previewPath($token));
        }
    }

    protected function previewPath(string $token): string
    {
        return self::RESULT_DIR.'/preview-'.$token.'.json';
    }

    /**
     * Bước 2: ghi nhận theo cách xử lý đã chọn cho từng dòng ([dòng => cách xử lý], dòng không chọn / chọn sai
     * dùng mặc định) và tạo file kết quả. Tình trạng Lead cũ được kiểm tra lại lúc ghi (có thể đã đổi).
     *
     * @return array{ok: bool, error?: string, total?: int, success?: int, skipped?: int, failed?: int, result?: string}
     */
    public function commit(string $token, array $decisions): array
    {
        $preview = $this->loadPreview($token);

        if (! $preview) {
            return ['ok' => false, 'error' => __('leads.import_review.expired')];
        }

        $results = [];

        DB::transaction(function () use ($preview, $decisions, &$results) {
            foreach ($preview['rows'] as $row) {
                $action = $decisions[$row['line']] ?? $row['default'];
                $action = in_array($action, $row['options'], true) ? $action : $row['default'];

                $results[] = ['line' => $row['line'], 'values' => $row['values']]
                    + ($row['errors'] ? $this->outcome('failed', errors: $row['errors']) : $this->commitRow($row, $action));
            }
        });

        $this->discardPreview($token);
        $count = fn (string $status) => count(array_filter($results, fn ($r) => $r['status'] === $status));
        $failed = $count('failed');
        $skipped = $count('skipped');

        return [
            'ok'      => true,
            'total'   => count($results),
            'success' => count($results) - $failed - $skipped,
            'skipped' => $skipped,
            'failed'  => $failed,
            'result'  => $this->writeResult($results),
        ];
    }

    /**
     * Ghi nhận một dòng hợp lệ theo cách xử lý đã chọn.
     */
    protected function commitRow(array $row, string $action): array
    {
        $data = $row['data'];

        if ($action === self::ACTION_SKIP) {
            return $this->outcome('skipped', notes: [__('leads.import_review.skipped')]);
        }

        if ($action === self::ACTION_CREATE) {
            $lead = $this->create($data);
            $notes = $row['kind'] === LeadDuplicateService::NEW ? [] : [__('leads.import_review.created_other')];

            return $this->outcome('created', $lead->lead_id, $notes);
        }

        // Cùng người với một Lead cụ thể: xử lý theo tình trạng hiện tại của Lead đó
        $leadId = str_starts_with($action, self::ACTION_SAME) ? substr($action, strlen(self::ACTION_SAME)) : ($row['leads'][0]['lead_id'] ?? null);
        $lead = $leadId ? $this->duplicates->find($leadId) : null;

        if (! $lead) {
            return $this->outcome('failed', errors: [__('leads.import_review.lead_missing')]);
        }

        $state = $this->duplicates->stateOf($lead);

        try {
            if ($state === LeadDuplicateService::REOPEN && $action !== self::ACTION_UPDATE) {
                $result = $this->duplicates->reopen($lead, $data);

                return $this->outcome('reopened', $lead->lead_id, array_merge(
                    [__('leads.duplicate.reopened', ['id' => $lead->lead_id, 'name' => $lead->full_name])],
                    $this->duplicates->reopenNotes($result),
                ));
            }

            if (in_array($state, [LeadDuplicateService::IN_QUEUE, LeadDuplicateService::IN_PROGRESS], true) && $action !== self::ACTION_REOPEN) {
                $result = $this->duplicates->updateExisting($lead->lead_id, $data, LeadImportService::COLUMNS, $this);
                $fields = array_map(fn ($f) => $f === 'branch_id' ? __('leads.branch') : __('leads.field.'.$f), $result['fields']);

                return $fields
                    ? $this->outcome('updated', $lead->lead_id, [__('leads.duplicate.updated', ['id' => $lead->lead_id, 'fields' => implode(', ', $fields)])])
                    : $this->outcome('skipped', $lead->lead_id, [__('leads.import_review.nothing_to_update', ['id' => $lead->lead_id])]);
            }
        } catch (\RuntimeException $e) {
            return $this->outcome('failed', $lead->lead_id, errors: [$e->getMessage()]);
        }

        // Tình trạng Lead đã đổi so với lúc xem trước nên cách xử lý không còn phù hợp
        return $this->outcome('failed', $lead->lead_id, errors: [__('leads.import_review.state_changed', ['id' => $lead->lead_id])]);
    }

    protected function outcome(string $status, ?string $leadId = null, array $notes = [], array $errors = []): array
    {
        return ['status' => $status, 'lead_id' => $leadId, 'notes' => $notes, 'errors' => $errors];
    }

    /**
     * Đọc sheet đầu tiên thành mảng các dòng (giá trị dạng chuỗi), khoá = thứ tự dòng từ 0.
     */
    protected function readRows(string $path): array
    {
        // Giữ dòng trống để số dòng báo lỗi khớp với Excel
        $options = new ReaderOptions();
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        $reader = new Reader($options);
        $reader->open($path);

        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($value) => $this->cellToString($value), $row->toArray());

                    // Dừng sớm với file quá lớn (đã chắc chắn vượt giới hạn, kể cả khi có dòng trống)
                    if (count($rows) > self::MAX_ROWS * 2) {
                        break;
                    }
                }
                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    protected function cellToString(mixed $value): string
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_float($value) && floor($value) === $value => number_format($value, 0, '', ''),
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            default => trim((string) $value),
        };
    }

    /**
     * Tiêu đề phải khớp file mẫu (bỏ qua dấu *, hoa thường, khoảng trắng thừa).
     * Chấp nhận file mẫu tải ở bất kỳ ngôn ngữ nào (vi / en).
     */
    protected function validHeader(array $header): bool
    {
        $clean = fn ($text) => Str::lower(trim(str_replace('*', '', preg_replace('/\s+/u', ' ', (string) $text))));

        foreach (['vi', 'en'] as $locale) {
            $matched = collect(self::COLUMNS)->every(
                fn ($column, $i) => $clean($header[$i] ?? '') === $clean(__('leads.field.'.$column, [], $locale))
            );

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    protected function heading(string $column): string
    {
        return __('leads.field.'.$column).(in_array($column, self::REQUIRED, true) ? ' *' : '');
    }

    protected function headerRow(array $extra = []): Row
    {
        $style = (new Style())
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('005E12');

        $titles = array_merge(array_map(fn ($c) => $this->heading($c), self::COLUMNS), $extra);

        return Row::fromValues($titles, $style);
    }

    protected function writer(string $path, bool $result = false): Writer
    {
        $options = new Options();
        $options->setColumnWidth(26, 1, 4);
        $options->setColumnWidth(16, 2, 6, 7);
        $options->setColumnWidth(30, 3, 5);

        if ($result) {
            $options->setColumnWidth(14, 8, 10);
            $options->setColumnWidth(24, 9);
            $options->setColumnWidth(70, 11, 12);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);

        return $writer;
    }

    /**
     * File kết quả: dữ liệu gốc + Dòng trong file + Kết quả + Mã Lead + Ghi chú + Lý do lỗi.
     */
    protected function writeResult(array $results): string
    {
        $relative = self::RESULT_DIR.'/'.Str::uuid().'.xlsx';
        Storage::disk('local')->makeDirectory(self::RESULT_DIR);
        $path = Storage::disk('local')->path($relative);

        $writer = $this->writer($path, true);
        $writer->getCurrentSheet()->setName(__('leads.excel.sheet_result'));
        $writer->addRow($this->headerRow([
            __('leads.excel.col_line'), __('leads.excel.col_result'), __('leads.excel.col_lead_id'),
            __('leads.excel.col_note'), __('leads.excel.col_reason'),
        ]));

        $okStyle = (new Style())->setFontColor('15803D')->setFontBold();
        $failStyle = (new Style())->setFontColor('B91C1C')->setFontBold();
        $failRow = (new Style())->setBackgroundColor('FEF2F2');
        $wrap = (new Style())->setShouldWrapText();

        foreach ($results as $result) {
            $ok = $result['status'] !== 'failed';

            $cells = array_map(fn ($value) => Cell::fromValue($value), $result['values']);
            $cells[] = Cell::fromValue($result['line']);
            $status = __('leads.excel.status_'.$result['status']);
            $bullets = fn (array $lines) => implode("\n", array_map(fn ($line) => '- '.$line, $lines));

            $cells[] = Cell::fromValue($status, $ok ? $okStyle : $failStyle);
            $cells[] = Cell::fromValue($result['lead_id'] ?? '');
            $cells[] = Cell::fromValue($bullets($result['notes']), $wrap);
            $cells[] = Cell::fromValue($bullets($result['errors']), $wrap);

            $writer->addRow(new Row($cells, $ok ? null : $failRow));
        }

        $writer->close();

        return $relative;
    }
}
