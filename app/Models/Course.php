<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;
    use HasStringId;

    public const STATUS_ACTIVE = 'Active';

    public const STATE_ACTIVE = 'active';
    public const STATE_INACTIVE = 'inactive';

    public const STATES = [self::STATE_ACTIVE, self::STATE_INACTIVE];

    protected $table = 'Courses';

    protected $primaryKey = 'course_id';

    protected string $idPrefix = 'CRS';

    protected int $idLength = 3;

    public const UPDATED_AT = null;

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

    public function displayPrice(): string
    {
        return number_format((float) $this->unit_price, 0, ',', '.').' ₫';
    }

    public function displayVat(): string
    {
        return rtrim(rtrim(number_format((float) $this->VAT, 2, ',', '.'), '0'), ',').'%';
    }

    public function quotationLines(?array $employeeIds = null): Builder
    {
        return QuotationDetail::query()
            ->join('Quotations', 'Quotations.quotation_id', '=', 'QuotationDetails.quotation_id')
            ->where('QuotationDetails.course_id', $this->course_id)
            ->when($employeeIds !== null, fn ($q) => $q->whereIn('Quotations.employee_id', $employeeIds));
    }

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

    public static function summarizeQuotationStats(array $rows): array
    {
        $byStatus = array_fill_keys(Quotation::STATUSES, 0);
        $seats = 0;
        $revenue = 0.0;
        $total = 0;

        foreach ($rows as $row) {
            $count = (int) $row['quotations'];
            $total += $count;

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
