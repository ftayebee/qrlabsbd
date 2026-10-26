<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrDestination extends Model
{
    protected $fillable = ['kind', 'platform', 'label', 'url', 'position'];

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }
}
