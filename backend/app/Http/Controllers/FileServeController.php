<?php

namespace App\Http\Controllers;

use App\Models\QrFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves hosted files by an opaque token. Files linked to a disabled,
 * paused or archived code, or owned by a suspended account, are not served.
 */
class FileServeController extends Controller
{
    public function __invoke(string $token): Response
    {
        $file = QrFile::query()->where('public_token', $token)->with(['qrCode:id,status,moderation_status', 'user:id,status'])->first();
        abort_unless($file, 404);
        abort_if($file->user?->status === 'suspended', 404);
        if ($file->qrCode && ($file->qrCode->moderation_status === 'blocked' || $file->qrCode->status->value !== 'active')) {
            abort(410, 'This file is no longer available.');
        }

        $disk = Storage::disk($file->disk);
        $headers = [
            'Content-Type' => $file->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:",
            'Cache-Control' => 'public, max-age=300',
            'X-Robots-Tag' => 'noindex',
        ];

        // Object storage: hand off to a short-lived signed URL so the API does not proxy large files.
        if (method_exists($disk, 'temporaryUrl') && config("filesystems.disks.{$file->disk}.driver") === 's3') {
            return redirect()->away($disk->temporaryUrl($file->path, now()->addMinutes(10), [
                'ResponseContentType' => $file->mime,
                'ResponseContentDisposition' => 'inline; filename="'.addslashes($file->original_name).'"',
            ]));
        }

        abort_unless($disk->exists($file->path), 404);

        return $disk->response($file->path, $file->original_name, $headers, 'inline');
    }
}
