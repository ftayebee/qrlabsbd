<?php

namespace App\Http\Requests\Qr;

/**
 * `content` and `design` are validated as arrays, so validated() would return
 * any extra keys a client sends. This keeps only keys that have rules.
 */
trait FiltersNestedInput
{
    /** @return array<string, mixed> */
    public function qrData(): array
    {
        $data = $this->validated();
        $rules = array_keys($this->rules());

        foreach (['content', 'design'] as $root) {
            if (! isset($data[$root]) || ! is_array($data[$root])) {
                continue;
            }
            $allowed = collect($rules)
                ->filter(fn (string $r) => str_starts_with($r, $root.'.'))
                ->map(fn (string $r) => explode('.', $r)[1])
                ->unique()->all();
            $data[$root] = array_intersect_key($data[$root], array_flip($allowed));
        }

        return $data;
    }
}
