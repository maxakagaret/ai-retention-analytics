<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Visit extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['visited_on' => 'immutable_date', 'total' => 'float'];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'visit_services')->withPivot('price');
    }
}
