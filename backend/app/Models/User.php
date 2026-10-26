<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'timezone', 'status'];

    protected $hidden = ['password', 'remember_token'];

    /** @var array{roles: list<string>, permissions: list<string>, admin: bool}|null */
    private ?array $accessProfile = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('created_at');
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** The subscription currently granting entitlements, if any. */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', SubscriptionStatus::accessValues())
            ->latestOfMany('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function quotaOverrides(): HasMany
    {
        return $this->hasMany(QuotaOverride::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /** @return array{roles: list<string>, permissions: list<string>, admin: bool} */
    public function accessProfile(): array
    {
        if ($this->accessProfile === null) {
            $roles = $this->roles()->with('permissions:id,slug')->get();
            $this->accessProfile = [
                'roles' => $roles->pluck('slug')->values()->all(),
                'permissions' => $roles->flatMap(fn (Role $r) => $r->permissions->pluck('slug'))->unique()->values()->all(),
                'admin' => $roles->contains(fn (Role $r) => $r->is_admin),
            ];
        }

        return $this->accessProfile;
    }

    public function isAdmin(): bool
    {
        return $this->accessProfile()['admin'];
    }

    public function hasPermission(string $permission): bool
    {
        $profile = $this->accessProfile();

        return in_array('super-admin', $profile['roles'], true) || in_array($permission, $profile['permissions'], true);
    }

    public function flushAccessCache(): void
    {
        $this->accessProfile = null;
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
