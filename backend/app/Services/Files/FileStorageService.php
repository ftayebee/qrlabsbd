<?php

namespace App\Services\Files;

use App\Exceptions\PlanLimitException;
use App\Models\QrFile;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileStorageService
{
    private const SIGNATURES = [
        'application/pdf' => ['%PDF-'],
        'image/png' => ["\x89PNG\r\n\x1a\n"],
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/webp' => ['RIFF'],
    ];

    public function __construct(private readonly EntitlementService $entitlements) {}

    public function store(User $user, UploadedFile $file, string $purpose): QrFile
    {
        $e = $this->entitlements->for($user);
        $size = (int) $file->getSize();

        if ($purpose === QrFile::PURPOSE_LOGO && ! $e->allows('custom_logo')) {
            throw new PlanLimitException('custom_logo', 'Logos are available on paid plans.');
        }
        if ($purpose === QrFile::PURPOSE_PDF && ! $e->isUnlimited('max_upload_mb') && $size > $e->limit('max_upload_mb') * 1048576) {
            throw new PlanLimitException('max_upload_mb', "Your plan allows files up to {$e->limit('max_upload_mb')} MB.");
        }
        if (! $e->isUnlimited('storage_mb') && $this->entitlements->storageBytes($user) + $size > $e->limit('storage_mb') * 1048576) {
            throw new PlanLimitException('storage_mb', 'This upload would exceed your plan’s storage limit.');
        }

        // Verify the real content, not only the client-declared MIME type.
        $mime = (string) $file->getMimeType();
        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 16);
        $valid = collect(self::SIGNATURES[$mime] ?? [])->contains(fn ($sig) => str_starts_with($head, $sig));
        if (! $valid || ($mime === 'image/webp' && substr($head, 8, 4) !== 'WEBP')) {
            throw ValidationException::withMessages(['file' => 'The file content does not match an allowed file type.']);
        }

        $disk = (string) config('qrio.files_disk');
        $extension = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime];
        // Randomised path: nothing about the user or original name is exposed in storage.
        $path = $purpose.'/'.now()->format('Y/m').'/'.Str::random(40).'.'.$extension;
        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path), ['visibility' => 'private']);

        return QrFile::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'disk' => $disk,
            'path' => $path,
            'public_token' => Str::random(32),
            'original_name' => Str::limit(preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()) ?: 'file', 200, ''),
            'mime' => $mime,
            'size' => $size,
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);
    }

    public function delete(QrFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->forceDelete();
    }
}
