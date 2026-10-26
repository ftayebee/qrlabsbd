<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FolderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $folders = $request->user()->folders()->withCount('qrCodes')->orderBy('name')->get();

        return response()->json(['data' => $folders->map(fn (Folder $f) => ['id' => $f->id, 'name' => $f->name, 'qrCount' => $f->qr_codes_count])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('folders')->where('user_id', $request->user()->id)]]);
        abort_if($request->user()->folders()->count() >= 100, 422, 'You can create up to 100 folders.');
        $folder = $request->user()->folders()->create($data);

        return response()->json(['data' => ['id' => $folder->id, 'name' => $folder->name, 'qrCount' => 0]], 201);
    }

    public function update(Request $request, Folder $folder): JsonResponse
    {
        $this->authorize('update', $folder);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('folders')->where('user_id', $request->user()->id)->ignore($folder->id)]]);
        $folder->update($data);

        return response()->json(['data' => ['id' => $folder->id, 'name' => $folder->name]]);
    }

    public function destroy(Request $request, Folder $folder): JsonResponse
    {
        $this->authorize('delete', $folder);
        $folder->delete();

        return response()->json(['message' => 'Folder deleted. Its QR codes were kept.']);
    }
}
