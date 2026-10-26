<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable, TemplatedMail;

    public function __construct(#[\SensitiveParameter] public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return $this->templated(
            'reset_password',
            ['name' => (string) $notifiable->name],
            'Reset your {{ app_name }} password',
            "Hi {{ name }},\n\nWe received a request to reset your password. This link expires in 60 minutes.\n\nIf you did not request a reset, no action is needed.",
            'Reset password',
            $url,
        );
    }
}
