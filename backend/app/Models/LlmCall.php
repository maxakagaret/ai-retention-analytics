<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmCall extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['success' => 'boolean', 'cost_usd' => 'float', 'created_at' => 'immutable_datetime'];
    }
}
