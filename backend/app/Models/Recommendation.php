<?php

namespace App\Models;

use App\Enums\Segment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'segment' => Segment::class,
            'items' => 'array',
            'context' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class, 'analysis_run_id');
    }
}
