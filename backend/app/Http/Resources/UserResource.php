<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $access = $this->accessProfile();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'emailVerified' => $this->hasVerifiedEmail(),
            'timezone' => $this->timezone,
            'isAdmin' => $access['admin'],
            'roles' => $access['roles'],
            'permissions' => $access['permissions'],
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
