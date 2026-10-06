<?php

namespace App\Models;

use App\Models\Concerns\HasStringId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory, HasStringId;

    protected $table = 'Leads';

    protected $primaryKey = 'lead_id';

    protected string $idPrefix = 'LEAD';

    protected int $idLength = 3;

    // Bảng Leads chỉ có created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'lead_id',
        'full_name',
        'phone',
        'email',
        'source_name',
        'source_url',
        'contact_method',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'lead_id', 'lead_id');
    }

    /**
     * Cơ hội gần nhất của Lead.
     */
    public function latestOpportunity()
    {
        return $this->hasOne(Opportunity::class, 'lead_id', 'lead_id')->latestOfMany('conversion_date');
    }
}
