<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QrFile;
use App\Services\Files\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FileController extends Controller
{
    public function __construct(private readonly FileStorageService $files) {}

    public function store(Request $request): JsonResponse
    {
        $purpose = $request->input('purpose');
        $mimes = $purpose === QrFile::PURPOSE_LOGO ? config('qrio.uploads.logo_mimes') : config('qrio.uploads.pdf_mimes');
        $max = $purpose === QrFile::PURPOSE_LOGO ? config('qrio.uploads.logo_max_kb') : config('qrio.uploads.hard_max_kb');

        $request->validate([
            'purpose' => ['required', Rule::in([QrFile::PURPOSE_PDF, QrFile::PURPOSE_LOGO])],
            'file' => ['required', 'file', 'mimetypes:'.implode(',', $mimes), 'max:'.$max],
        ]);

        $file = $this->files->store($request->user(), $request->file('file'), $purpose);

        return response()->json(['data' => [
            'id' => $file->id,
            'name' => $file->original_name,
            'size' => $file->size,
            'mime' => $file->mime,
            'url' => $file->publicUrl(),
        ]], 201);
    }

    public function destroy(Request $request, QrFile $file): JsonResponse
    {
        $this->authorize('delete', $file);
        abort_if($file->qr_code_id !== null, 422, 'This file is used by a QR code. Replace it on the code first.');
        $this->files->delete($file);

        return response()->json(['message' => 'File deleted.']);
    }
}
