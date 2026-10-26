<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'group', 'value', 'is_public'];

    protected function casts(): array
    {
        return ['value' => 'json', 'is_public' => 'boolean'];
    }
}
