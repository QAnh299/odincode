<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Lead;
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
 * - Import ghi nhận các dòng hợp lệ, bỏ qua dòng lỗi và trả về file kết quả
 *   (mỗi dòng có cột Kết quả, Mã Lead, Lý do lỗi).
 */
class LeadImportService
{
    // Số dòng dữ liệu tối đa trong một file
    const MAX_ROWS = 1000;

    // Thư mục lưu file kết quả trên disk local (storage/app/private)
    const RESULT_DIR = 'lead-imports';

    // Cột trong file mẫu, theo thứ tự
    const COLUMNS = ['full_name', 'phone', 'email', 'source_name', 'source_url', 'contact_method', 'branch_id'];

    // Cột luôn bắt buộc (đánh dấu * trên tiêu đề); SĐT, Link nguồn bắt buộc tuỳ kênh liên hệ
    const REQUIRED = ['full_name', 'source_name', 'contact_method', 'branch_id'];

    // Ngăn cách mã và tên chi nhánh trong ô chọn của file mẫu
    const BRANCH_SEPARATOR = ' - ';

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
            // Không kiểm tra trùng: học viên đăng ký thêm khoá học → tạo Lead mới cùng thông tin
            'phone'          => ['nullable', 'required_if:contact_method,'.implode(',', Lead::METHODS_PHONE_REQUIRED), 'regex:/^0\d{9}$/'],
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
            e(__('leads.field.'.$field)), e($error), $column($field), $lastRow, e($ref), $range,
        );

        $xml = '<dataValidations count="3">'
            .$validation('source_name', '$C$1:$C$'.max($sourceCount, 1),
                __('leads.form.source_invalid', ['values' => implode(', ', Lead::SOURCES)]))
            .$validation('contact_method', '$A$1:$A$'.max($methodCount, 1),
                __('leads.form.method_invalid', ['values' => implode(', ', Lead::CONTACT_METHODS)]))
            .$validation('branch_id', '$B$1:$B$'.max($branchCount, 1), __('leads.form.branch_invalid'))
            .'</dataValidations>';

        $zip = new ZipArchive();
        $zip->open($path);

        // <dataValidations> phải đứng trước các thẻ này theo chuẩn OOXML
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheet = preg_replace_callback(
            '#(?=<(?:hyperlinks|printOptions|pageMargins|pageSetup|headerFooter|drawing|legacyDrawing|tableParts|extLst)[\s>/]|</worksheet>)#',
            fn () => $xml, $sheet, 1,
        );
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);

        $workbook = $zip->getFromName('xl/workbook.xml');
        $workbook = preg_replace(
            '#(<sheet name="'.preg_quote(e($listSheet), '#').'"[^>]*?)state="visible"#u',
            '$1state="hidden"', $workbook,
        );
        $zip->addFromString('xl/workbook.xml', $workbook);

        $zip->close();
    }

    /* ── Import ───────────────────────────────────────────────────────── */

    /**
     * Đọc file Excel, ghi nhận dòng hợp lệ, tạo file kết quả.
     *
     * @return array{ok: bool, error?: string, total?: int, success?: int, failed?: int, result?: string}
     *               result = đường dẫn file kết quả trên disk local.
     */
    public function import(string $path): array
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

        $results = [];

        DB::transaction(function () use ($rows, &$results) {
            foreach ($rows as $index => $values) {
                $line = $index + 2;
                $raw = array_combine(self::COLUMNS, array_pad(array_slice($values, 0, count(self::COLUMNS)), count(self::COLUMNS), ''));

                $data = $this->normalize($raw);
                $errors = Validator::make($data, $this->rules(), $this->messages(), $this->attributes())
                    ->errors();

                $leadId = null;

                if ($errors->isEmpty()) {
                    $leadId = $this->create($data)->lead_id;
                }

                $results[] = [
                    'line'    => $line,
                    'values'  => array_values($raw),
                    'lead_id' => $leadId,
                    'errors'  => $errors->all(),
                ];
            }
        });

        $success = count(array_filter($results, fn ($r) => $r['errors'] === []));

        return [
            'ok'      => true,
            'total'   => count($results),
            'success' => $success,
            'failed'  => count($results) - $success,
            'result'  => $this->writeResult($results),
        ];
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
            $options->setColumnWidth(14, 8, 9, 10);
            $options->setColumnWidth(70, 11);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);

        return $writer;
    }

    /**
     * File kết quả: dữ liệu gốc + Dòng trong file + Kết quả + Mã Lead + Lý do lỗi.
     */
    protected function writeResult(array $results): string
    {
        $relative = self::RESULT_DIR.'/'.Str::uuid().'.xlsx';
        Storage::disk('local')->makeDirectory(self::RESULT_DIR);
        $path = Storage::disk('local')->path($relative);

        $writer = $this->writer($path, true);
        $writer->getCurrentSheet()->setName(__('leads.excel.sheet_result'));
        $writer->addRow($this->headerRow([
            __('leads.excel.col_line'), __('leads.excel.col_result'), __('leads.excel.col_lead_id'), __('leads.excel.col_reason'),
        ]));

        $okStyle = (new Style())->setFontColor('15803D')->setFontBold();
        $failStyle = (new Style())->setFontColor('B91C1C')->setFontBold();
        $failRow = (new Style())->setBackgroundColor('FEF2F2');
        $wrap = (new Style())->setShouldWrapText();

        foreach ($results as $result) {
            $ok = $result['errors'] === [];

            $cells = array_map(fn ($value) => Cell::fromValue($value), $result['values']);
            $cells[] = Cell::fromValue($result['line']);
            $cells[] = Cell::fromValue($ok ? __('leads.excel.status_ok') : __('leads.excel.status_fail'), $ok ? $okStyle : $failStyle);
            $cells[] = Cell::fromValue($result['lead_id'] ?? '');
            $cells[] = Cell::fromValue(implode("\n", array_map(
                fn ($e) => '- '.$e, $result['errors']
            )), $wrap);

            $writer->addRow(new Row($cells, $ok ? null : $failRow));
        }

        $writer->close();

        return $relative;
    }
}
