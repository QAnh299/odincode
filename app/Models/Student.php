<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;
    use HasStringId;

    public const STATUS_STUDYING = 'Studying';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_DROPPED = 'Dropped';
    public const STATUSES = [self::STATUS_STUDYING, self::STATUS_COMPLETED, self::STATUS_DROPPED];

    protected $table = 'Students';

    protected $primaryKey = 'student_id';

    protected string $idPrefix = 'STD';

    protected int $idLength = 3;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'full_name',
        'phone',
        'email',
        'date_of_birth',
        'conversion_date',
        'status',
        'lead_id',
        'opportunity_id',
    ];

    protected $casts = [
        'date_of_birth'   => 'date',
        'conversion_date' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'lead_id');
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id', 'opportunity_id');
    }

    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        return $query->whereHas('lead', fn ($q) =>
            $q->whereHas('opportunities', fn ($q2) => $q2->visibleTo($employee))
        );
    }

    public function paidOrder(): ?SalesOrder
    {
        return SalesOrder::query()
            ->where('paid_amount', '>', 0)
            ->whereHas('quotation.opportunity', fn ($q) => $q->where('lead_id', $this->lead_id))
            ->latest('created_at')
            ->first();
    }

    public static function enrollFromOrder(SalesOrder $order): ?self
    {
        $opportunity = $order->quotation?->opportunity;

        if (! $opportunity?->lead_id) {
            return null;
        }

        $lead = Lead::query()->with('student')->find($opportunity->lead_id);

        if (! $lead) {
            return null;
        }

        if ($existing = $lead->student) {
            if (in_array($existing->status, [self::STATUS_COMPLETED, self::STATUS_DROPPED])) {
                $existing->update(['status' => self::STATUS_STUDYING]);
            }

            return $existing;
        }

        return static::create([
            'full_name'       => $lead->full_name,
            'phone'           => $lead->phone,
            'email'           => $lead->email,
            'conversion_date' => now(),
            'status'          => self::STATUS_STUDYING,
            'lead_id'         => $lead->lead_id,
            'opportunity_id'  => $opportunity->opportunity_id,
        ]);
    }
}
