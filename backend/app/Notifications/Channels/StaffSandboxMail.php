<?php

namespace App\Notifications\Channels;

use App\Notifications\StaffPasswordReset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StaffSandboxMail
{
    public function send(object $notifiable, StaffPasswordReset $notification): void
    {
        $path = 'staff-mail/'.Str::uuid().'.eml';
        $saved = Storage::disk('local')->put($path, 'To: '.$notifiable->email."\nSubject: ELVARQO staff password reset (LOCAL SANDBOX)\n\nThis link expires in 30 minutes.\n".$notification->resetUrl($notifiable)."\n");
        if (! $saved) {
            throw new \RuntimeException('The private staff sandbox mailbox could not be written.');
        }
    }
}
