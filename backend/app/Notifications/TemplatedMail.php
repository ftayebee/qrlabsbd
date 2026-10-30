<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Builds mail from an admin-editable template, falling back to the supplied
 * defaults when the template is missing or disabled.
 */
trait TemplatedMail
{
    /** @param array<string, string> $vars */
    protected function templated(string $key, array $vars, string $defaultSubject, string $defaultBody, ?string $actionText = null, ?string $actionUrl = null): MailMessage
    {
        $vars += ['app_name' => (string) config('qrio.brand.name')];
        $template = EmailTemplate::query()->where('key', $key)->where('is_active', true)->first()
            ?? new EmailTemplate(['subject' => $defaultSubject, 'body' => $defaultBody]);
        $rendered = $template->render($vars);

        $mail = (new MailMessage)->subject($rendered['subject']);
        foreach (preg_split('/\R{2,}/', trim($rendered['body'])) ?: [] as $paragraph) {
            // Added as plain text lines; the mail layout escapes them.
            $mail->line(Str::of($paragraph)->replace("\n", ' ')->toString());
        }
        if ($actionText && $actionUrl) {
            $mail->action($actionText, $actionUrl);
        }

        return $mail;
    }
}
