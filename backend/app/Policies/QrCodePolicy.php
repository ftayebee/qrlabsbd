<?php

namespace App\Policies;

use App\Models\QrCode;
use App\Models\TeamMember;
use App\Models\User;

/**
 * Owners have full control. Accepted team members (when the owner's plan
 * allows teams) may view; editors may also change codes. Nobody else may see
 * that a code exists.
 */
class QrCodePolicy
{
    public function view(User $user, QrCode $qr): bool
    {
        return $qr->user_id === $user->id || $this->memberRole($user, $qr) !== null;
    }

    /** Codes disabled by moderation cannot be edited by anyone but admins (via the admin API). */
    public function update(User $user, QrCode $qr): bool
    {
        if ($qr->moderation_status === 'blocked') {
            return false;
        }

        return $qr->user_id === $user->id || $this->memberRole($user, $qr) === 'editor';
    }

    public function delete(User $user, QrCode $qr): bool
    {
        return $qr->user_id === $user->id;
    }

    private function memberRole(User $user, QrCode $qr): ?string
    {
        return TeamMember::query()
            ->where('owner_id', $qr->user_id)
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->value('role');
    }
}
