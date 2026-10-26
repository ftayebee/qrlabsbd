<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbuseReport extends Model
{
    protected $fillable = ['qr_code_id', 'reporter_email', 'reason', 'details', 'status', 'resolved_by', 'resolution_note', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class)->withTrashed();
    }
}
