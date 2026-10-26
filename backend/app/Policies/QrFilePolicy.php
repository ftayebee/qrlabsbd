<?php

namespace App\Policies;

use App\Models\QrFile;
use App\Models\User;

class QrFilePolicy
{
    public function view(User $user, QrFile $file): bool
    {
        return $file->user_id === $user->id;
    }

    public function delete(User $user, QrFile $file): bool
    {
        return $file->user_id === $user->id;
    }
}
