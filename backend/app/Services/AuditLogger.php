<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Append-only record of administrative and security-relevant actions. */
class AuditLogger
{
    private const REDACT = ['password', 'password_confirmation', 'token', 'secret', 'remember_token'];

    /** @param array<string, mixed> $changes */
    public function log(?User $actor, string $action, ?Model $subject = null, ?string $reason = null, array $changes = []): AuditLog
    {
        $ip = request()?->ip();

        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'changes' => $this->redact($changes) ?: null,
            // Hashed so admins can correlate actions without storing addresses.
            'ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACT, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
