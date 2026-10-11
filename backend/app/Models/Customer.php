<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['joined_on' => 'immutable_date'];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->orderBy('visited_on');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(CustomerScore::class);
    }
}
