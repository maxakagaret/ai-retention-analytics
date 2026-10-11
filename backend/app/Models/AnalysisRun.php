<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'as_of' => 'immutable_date',
            'params' => 'array',
            'summary' => 'array',
        ];
    }

    public function scores(): HasMany
    {
        return $this->hasMany(CustomerScore::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('kind', 'live');
    }

    public static function latestLive(): ?self
    {
        return self::live()->latest('id')->first();
    }
}
