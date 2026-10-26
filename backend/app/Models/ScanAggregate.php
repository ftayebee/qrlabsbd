<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanAggregate extends Model
{
    public $timestamps = false;

    protected $fillable = ['qr_code_id', 'user_id', 'date', 'dimension', 'value', 'scans', 'unique_scans'];

    protected function casts(): array
    {
        return ['date' => 'date', 'scans' => 'integer', 'unique_scans' => 'integer'];
    }
}
