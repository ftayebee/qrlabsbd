<?php

namespace App\Models;

use App\Enums\QrMode;
use App\Enums\QrStatus;
use App\Enums\QrType;
use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'type', 'mode', 'status', 'content', 'static_payload', 'folder_id', 'campaign_id'];

    protected function casts(): array
    {
        return [
            'type' => QrType::class,
            'mode' => QrMode::class,
            'status' => QrStatus::class,
            // Encrypted: content may contain Wi-Fi passwords or personal contact details.
            'content' => 'encrypted:array',
            'static_payload' => 'encrypted',
            'last_scanned_at' => 'datetime',
            'total_scans' => 'integer',
            'unique_scans' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(QrDestination::class)->orderBy('position');
    }

    public function design(): HasOne
    {
        return $this->hasOne(QrDesign::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(QrFile::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(QrScan::class);
    }

    public function abuseReports(): HasMany
    {
        return $this->hasMany(AbuseReport::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(QrDestinationChange::class)->latest('created_at');
    }

    public function isDynamic(): bool
    {
        return $this->mode === QrMode::Dynamic;
    }

    public function shortUrl(): ?string
    {
        return $this->short_code ? rtrim((string) config('qrio.short_domain'), '/').'/r/'.$this->short_code : null;
    }

    /** The exact value encoded into the QR symbol. */
    public function encodedValue(): string
    {
        return $this->isDynamic() ? (string) $this->shortUrl() : (string) $this->static_payload;
    }

    public function cacheKey(): string
    {
        return self::cacheKeyFor((string) $this->short_code);
    }

    public static function cacheKeyFor(string $code): string
    {
        return 'qr:redirect:'.$code;
    }
}
