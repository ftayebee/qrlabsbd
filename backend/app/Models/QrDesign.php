<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrDesign extends Model
{
    protected $fillable = ['config', 'logo_file_id'];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(QrFile::class, 'logo_file_id');
    }
}
