<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    public const KEYS = ['billing', 'product_updates', 'weekly_report', 'usage_alerts'];

    protected $fillable = ['user_id', 'channel', 'key', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
