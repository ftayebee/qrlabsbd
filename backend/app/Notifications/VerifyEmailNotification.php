<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable, TemplatedMail;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // The link opens the SPA, which forwards id/hash/expires/signature to the signed API route.
        $signed = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ], absolute: false);
        parse_str((string) parse_url($signed, PHP_URL_QUERY), $signature);
        $url = rtrim((string) config('app.frontend_url'), '/').'/verify-email?'.http_build_query([
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ] + $signature);

        return $this->templated(
            'verify_email',
            ['name' => (string) $notifiable->name],
            'Verify your email for {{ app_name }}',
            "Hi {{ name }},\n\nPlease confirm your email address to finish setting up your {{ app_name }} account. This link expires in 60 minutes.\n\nIf you did not create an account, you can ignore this email.",
            'Verify email',
            $url,
        );
    }
}
