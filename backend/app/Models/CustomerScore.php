<?php

namespace App\Models;

use App\Enums\Segment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerScore extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'segment' => Segment::class,
            'risk_factors' => 'array',
            'monetary' => 'float',
            'revenue_at_risk' => 'float',
            'next_visit_due' => 'immutable_date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
