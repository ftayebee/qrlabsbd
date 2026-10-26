<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Only keys that already exist (created by the seeder) can be edited, so the
 * admin UI cannot be used to inject arbitrary configuration. Secrets live in
 * environment variables, never here.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings, private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => SystemSetting::query()->orderBy('group')->orderBy('key')->get(['key', 'group', 'value', 'is_public'])]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array', 'max:100'],
            'settings.*.key' => ['required', 'string', 'exists:system_settings,key'],
            'settings.*.value' => ['present'],
        ]);

        $changes = [];
        foreach ($data['settings'] as $item) {
            $value = $item['value'];
            if (is_string($value) && mb_strlen($value) > 5000) {
                abort(422, "Setting {$item['key']} is too long.");
            }
            $changes[$item['key']] = $value;
            $this->settings->set($item['key'], $value);
        }
        Cache::forget('public:plans');
        $this->audit->log($request->user(), 'settings.updated', null, $request->input('reason'), $changes);

        return $this->index();
    }
}
