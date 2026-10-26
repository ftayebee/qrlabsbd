<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/** Admin-editable key/value settings, cached as one map. */
class SettingsService
{
    private const CACHE_KEY = 'system_settings:all';

    /** @var array<string, array{value: mixed, group: string, public: bool}>|null */
    private ?array $loaded = null;

    /** @return array<string, array{value: mixed, group: string, public: bool}> */
    private function all(): array
    {
        return $this->loaded ??= Cache::remember(self::CACHE_KEY, 600, fn () => SystemSetting::query()->get()
            ->mapWithKeys(fn (SystemSetting $s) => [$s->key => ['value' => $s->value, 'group' => $s->group, 'public' => $s->is_public]])
            ->all());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key]['value'] ?? $default;
    }

    /** @return array<string, mixed> */
    public function public(): array
    {
        return collect($this->all())->filter(fn ($s) => $s['public'])->map(fn ($s) => $s['value'])->all();
    }

    /** @return array<string, array<string, mixed>> */
    public function grouped(): array
    {
        return collect($this->all())->map(fn ($s, $key) => ['key' => $key] + $s)->groupBy('group')->map->values()->toArray();
    }

    public function set(string $key, mixed $value, ?string $group = null, ?bool $public = null): void
    {
        $setting = SystemSetting::query()->firstOrNew(['key' => $key]);
        $setting->value = $value;
        $setting->group = $group ?? $setting->group ?? 'general';
        if ($public !== null) {
            $setting->is_public = $public;
        }
        $setting->save();
        $this->flush();
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }
}
