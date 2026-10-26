<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QrFile extends Model
{
    use SoftDeletes;

    public const PURPOSE_PDF = 'pdf';

    public const PURPOSE_LOGO = 'logo';

    protected $fillable = ['user_id', 'qr_code_id', 'purpose', 'disk', 'path', 'public_token', 'original_name', 'mime', 'size', 'checksum'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    /** Public, controlled URL. Files are never exposed by their storage path. */
    public function publicUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/f/'.$this->public_token;
    }
}
