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

/**
 * Thêm Lead thủ công + Import Lead từ file Excel (HĐ1 – HĐ6, PhanLead.docx).
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

    // Cột bắt buộc (đánh dấu * trên tiêu đề)
    const REQUIRED = ['full_name', 'phone', 'source_name', 'contact_method', 'branch_id'];

    /**
     * Chuẩn hoá dữ liệu nhập: bỏ khoảng trắng, chuẩn SĐT, kênh liên hệ, mã chi nhánh.
     */
    public function normalize(array $data): array
    {
        $value = fn ($key) => trim((string) ($data[$key] ?? ''));

        return [
            'full_name'      => preg_replace('/\s+/u', ' ', $value('full_name')),
            'phone'          => $this->normalizePhone($value('phone')),
            'email'          => $value('email') !== '' ? Str::lower($value('email')) : null,
            'source_name'    => $value('source_name'),
            'source_url'     => $value('source_url') !== '' ? $value('source_url') : null,
            'contact_method' => $this->normalizeMethod($value('contact_method')),
            'branch_id'      => Str::upper($value('branch_id')),
        ];
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
     * Luật kiểm tra cho dữ liệu đã chuẩn hoá (theo ràng buộc bảng Leads trong odin.sql).
     */
    public function rules(): array
    {
        return [
            'full_name'      => ['required', 'string', 'max:150'],
            // Không kiểm tra trùng: học viên đăng ký thêm khoá học → tạo Lead mới cùng thông tin
            'phone'          => ['required', 'regex:/^0\d{9}$/'],
            'email'          => ['nullable', 'email', 'max:254'],
            'source_name'    => ['required', 'string', 'max:150'],
            'source_url'     => ['nullable', 'url:http,https', 'max:2000'],
            'contact_method' => ['required', Rule::in(Lead::CONTACT_METHODS)],
            'branch_id'      => ['required', Rule::exists(Branch::class, 'branch_id')],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex'       => __('leads.form.phone_invalid'),
            'contact_method.in' => __('leads.form.method_invalid', ['values' => implode(', ', Lead::CONTACT_METHODS)]),
            'branch_id.exists'  => __('leads.form.branch_invalid'),
        ];
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

        $writer->getCurrentSheet()->setName(__('leads.excel.sheet_data'));
        $writer->addRow($this->headerRow());

        $branch = Branch::query()->orderBy('branch_id')->value('branch_id') ?? 'BR001';
        $writer->addRow(Row::fromValues([
            'Nguyễn Văn A', '0912345678', 'nguyenvana@example.com', 'Facebook',
            'https://facebook.com/odin', 'Zalo', $branch,
        ], (new Style())->setFontItalic()->setFontColor(Color::rgb(107, 114, 128))));

        // Sheet hướng dẫn: mô tả cột + giá trị hợp lệ
        $writer->addNewSheetAndMakeItCurrent()->setName(__('leads.excel.sheet_guide'));
        $bold = (new Style())->setFontBold();

        $writer->addRow(Row::fromValues([__('leads.excel.guide_column'), __('leads.excel.guide_note')], $bold));
        foreach (self::COLUMNS as $column) {
            $writer->addRow(Row::fromValues([$this->heading($column), __('leads.excel.guide.'.$column)]));
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues([__('leads.excel.guide_methods')], $bold));
        foreach (Lead::CONTACT_METHODS as $method) {
            $writer->addRow(Row::fromValues([$method, __('leads.method.'.$method)]));
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues([__('leads.excel.guide_branches')], $bold));
        Branch::query()
            ->orderBy('branch_id')
            ->get(['branch_id', 'branch_name'])
            ->each(fn ($branch) => $writer->addRow(Row::fromValues([$branch->branch_id, $branch->branch_name])));

        $writer->close();

        return $path;
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
