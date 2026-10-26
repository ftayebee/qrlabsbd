<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotaOverride extends Model
{
    protected $fillable = ['user_id', 'key', 'value', 'reason', 'created_by', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
