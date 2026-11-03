<?php

namespace App\Security;

use App\Models\StaffUser;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

class StaffFactors
{
    public function verify(StaffUser $staff, #[\SensitiveParameter] ?string $code, #[\SensitiveParameter] ?string $recovery): bool
    {
        if ($code && preg_match('/^[0-9]{6}$/D', $code) && $staff->two_factor_secret) {
            $step = (new Google2FA)->verifyKeyNewer($staff->two_factor_secret, $code, $staff->last_totp_step ?? 0, 1, (int) floor(now()->timestamp / 30));
            if ($step !== false) {
                $staff->last_totp_step = $step;
                $staff->save();

                return true;
            }
        }
        if ($recovery && $staff->two_factor_confirmed_at) {
            $hash = hash('sha256', strtolower(str_replace('-', '', trim($recovery))));
            $used = DB::table('staff_recovery_codes')->where('staff_user_id', $staff->id)->where('code_hash', $hash)->whereNull('used_at')->where('expires_at', '>', now())->update(['used_at' => now()]);
            if ($used) {
                StaffAudit::record('mfa.recovery_used', $staff->id, 'staff:'.$staff->id);

                return true;
            }
        }

        return false;
    }

    public function rotate(StaffUser $staff): array
    {
        DB::table('staff_recovery_codes')->where('staff_user_id', $staff->id)->delete();
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $raw = bin2hex(random_bytes(16));
            $codes[] = implode('-', str_split($raw, 8));
            DB::table('staff_recovery_codes')->insert(['staff_user_id' => $staff->id, 'code_hash' => hash('sha256', $raw), 'expires_at' => now()->addDays(config('staff.recovery_days'))]);
        }

        return $codes;
    }
}
