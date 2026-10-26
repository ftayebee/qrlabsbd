<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrScan extends Model
{
    public $timestamps = false;

    protected $fillable = ['qr_code_id', 'scanned_at', 'country_code', 'region', 'device_type', 'os', 'browser', 'referrer_host', 'visitor_hash', 'is_unique', 'is_bot'];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime', 'is_unique' => 'boolean', 'is_bot' => 'boolean'];
    }
}
