<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory, HasStringId;

    // Trạng thái khóa học (cột status): Đang cung cấp hoặc Ngừng cung cấp
    const STATUS_ACTIVE = 'Active';

    // Key trạng thái dùng cho bộ lọc / nhãn hiển thị (lang courses.state.*)
    const STATE_ACTIVE = 'active';     // status = 'Active'
    const STATE_INACTIVE = 'inactive'; // status khác 'Active'

    const STATES = [self::STATE_ACTIVE, self::STATE_INACTIVE];

    protected $table = 'Courses';

    protected $primaryKey = 'course_id';

    protected string $idPrefix = 'CRS';

    protected int $idLength = 3;

    // Bảng Courses chỉ có created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'course_id',
        'course_name',
        'description',
        'unit_price',
        'VAT',
        'duration',
        'status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'VAT'        => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function quotationDetails()
    {
        return $this->hasMany(QuotationDetail::class, 'course_id', 'course_id');
    }

    /**
     * Lọc theo trạng thái (một trong self::STATES).
     */
    public function scopeState(Builder $query, string $state): Builder
    {
        return match ($state) {
            self::STATE_ACTIVE   => $query->where('status', self::STATUS_ACTIVE),
            self::STATE_INACTIVE => $query->where('status', '<>', self::STATUS_ACTIVE),
            default              => $query,
        };
    }

    public function state(): string
    {
        return $this->status === self::STATUS_ACTIVE ? self::STATE_ACTIVE : self::STATE_INACTIVE;
    }

    /**
     * Học phí hiển thị: "6.000.000 ₫".
     */
    public function displayPrice(): string
    {
        return number_format((float) $this->unit_price, 0, ',', '.').' ₫';
    }

    /**
     * VAT hiển thị: "10%", "0%", "8,5%".
     */
    public function displayVat(): string
    {
        return rtrim(rtrim(number_format((float) $this->VAT, 2, ',', '.'), '0'), ',').'%';
    }

    /**
     * Ảnh minh hoạ khóa học: file public/images/courses/{course_id}.{jpg|jpeg|png|webp}.
     * Bảng Courses không có cột ảnh nên ảnh đặt theo mã khóa học; không có file thì trả null.
     */
    public function imageUrl(): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $path = "images/courses/{$this->course_id}.{$ext}";

            if (is_file(public_path($path))) {
                return asset($path).'?v='.filemtime(public_path($path));
            }
        }

        return null;
    }

    /**
     * Các dòng báo giá (QuotationDetails) của khóa học, đã nối với Quotations.
     * Khóa chính (quotation_id, course_id) nên mỗi báo giá có đúng 1 dòng cho khóa học này.
     *
     * @param  array<int, string>|null  $employeeIds  null: mọi báo giá; mảng: chỉ báo giá do các nhân viên này lập
     *                                                ([] = không có báo giá nào)
     */
    public function quotationLines(?array $employeeIds = null): Builder
    {
        return QuotationDetail::query()
            ->join('Quotations', 'Quotations.quotation_id', '=', 'QuotationDetails.quotation_id')
            ->where('QuotationDetails.course_id', $this->course_id)
            ->when($employeeIds !== null, fn ($q) => $q->whereIn('Quotations.employee_id', $employeeIds));
    }

    /**
     * Thống kê báo giá của khóa học (1 truy vấn GROUP BY trạng thái).
     *
     * @param  array<int, string>|null  $employeeIds  phạm vi nhân viên, như quotationLines()
     */
    public function quotationStats(?array $employeeIds = null): array
    {
        $rows = $this->quotationLines($employeeIds)
            ->groupBy('Quotations.status')
            ->selectRaw('Quotations.status AS status, COUNT(*) AS quotations,'
                .' SUM(QuotationDetails.quantity) AS seats, SUM(QuotationDetails.line_total) AS revenue')
            ->toBase()
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return self::summarizeQuotationStats($rows);
    }

    /**
     * Tính số liệu từ kết quả GROUP BY (tách riêng để test không cần CSDL).
     *
     * @param  array<int, array{status: string, quotations: int|string, seats: int|string|null, revenue: float|string|null}>  $rows
     * @return array{total: int, by_status: array<string, int>, seats_confirmed: int, revenue_confirmed: float, close_rate: float|null}
     */
    public static function summarizeQuotationStats(array $rows): array
    {
        $byStatus = array_fill_keys(Quotation::STATUSES, 0);
        $seats = 0;
        $revenue = 0.0;
        $total = 0;

        foreach ($rows as $row) {
            $count = (int) $row['quotations'];
            $total += $count;

            // Collation không phân biệt hoa thường: 'confirmed' cũng là Confirmed
            $status = collect(Quotation::STATUSES)->first(fn ($s) => strcasecmp($s, (string) $row['status']) === 0);
            if ($status === null) {
                continue;
            }

            $byStatus[$status] += $count;

            if ($status === Quotation::STATUS_CONFIRMED) {
                $seats += (int) $row['seats'];
                $revenue += (float) $row['revenue'];
            }
        }

        // Tỷ lệ chốt = Confirmed / (Confirmed + Rejected); chưa có báo giá đã kết thúc thì null
        $closed = $byStatus[Quotation::STATUS_CONFIRMED] + $byStatus[Quotation::STATUS_REJECTED];

        return [
            'total'             => $total,
            'by_status'         => $byStatus,
            'seats_confirmed'   => $seats,
            'revenue_confirmed' => round($revenue, 2),
            'close_rate'        => $closed > 0 ? $byStatus[Quotation::STATUS_CONFIRMED] / $closed * 100 : null,
        ];
    }
}
