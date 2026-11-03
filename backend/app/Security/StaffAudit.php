<?php

namespace App\Security;

use App\Models\StaffAuditEvent;
use Illuminate\Support\Str;

class StaffAudit
{
    public static function record(string $action, ?int $actor = null, ?string $target = null, string $result = 'success'): void
    {
        StaffAuditEvent::create(['id' => (string) Str::uuid(), 'staff_user_id' => $actor, 'action' => $action, 'target' => $target, 'result' => $result,
            'request_id' => app()->runningInConsole() ? null : request()->attributes->get('request_id'),
            'ip_hash' => app()->runningInConsole() ? null : hash_hmac('sha256', request()->ip() ?? '', config('app.key')), 'created_at' => now()]);
    }
}
