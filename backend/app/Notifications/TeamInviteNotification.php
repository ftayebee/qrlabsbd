<?php

namespace App\Notifications;

use App\Models\TeamMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInviteNotification extends Notification implements ShouldQueue
{
    use Queueable, TemplatedMail;

    public function __construct(public readonly TeamMember $member, #[\SensitiveParameter] public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templated(
            'team_invite',
            ['inviter' => (string) $this->member->owner->name],
            '{{ inviter }} invited you to {{ app_name }}',
            "{{ inviter }} has invited you to help manage their QR codes on {{ app_name }}.\n\nSign in or create an account with this email address, then accept the invitation.",
            'Accept invitation',
            rtrim((string) config('app.frontend_url'), '/').'/dashboard/team?invite='.$this->token,
        );
    }
}
