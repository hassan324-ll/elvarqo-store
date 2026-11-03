<?php

namespace App\Notifications;

use App\Notifications\Channels\StaffSandboxMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffPasswordReset extends Notification
{
    public function __construct(#[\SensitiveParameter] public string $token) {}

    public function via(object $notifiable): array
    {
        if (config('mail.default') === 'log') {
            if (! app()->environment(['local', 'testing'])) {
                throw new \RuntimeException('Configure a real staff mail transport before production recovery.');
            }

            return [StaffSandboxMail::class];
        }

        return ['mail'];
    }

    public function resetUrl(object $notifiable): string
    {
        return rtrim(config('staff.admin_origin'), '/').'/?reset_token='.rawurlencode($this->token).'&email='.rawurlencode($notifiable->email);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Reset your ELVARQO staff password')->line('A staff password reset was requested. This link expires in 30 minutes.')->action('Reset staff password', $this->resetUrl($notifiable))->line('If you did not request this, ignore the message. Your authenticator remains required.');
    }
}
