<?php

namespace App\Http\Controllers;

use App\Models\StaffAuditEvent;
use App\Models\StaffUser;
use App\Security\StaffAudit;
use App\Security\StaffFactors;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class StaffAuthController extends Controller
{
    private function ok(array $data)
    {
        return response()->json(['data' => $data, 'meta' => ApiResponse::meta()]);
    }

    public function csrf(Request $request)
    {
        return $this->ok(['csrf_token' => $request->session()->token()]);
    }

    public function login(Request $request)
    {
        $input = $request->validate(['email' => 'required|string|email|max:254', 'password' => 'required|string|max:128']);
        $request->session()->forget(['staff_pending_id', 'staff_pending_at', 'staff_pending_version']);
        $staff = StaffUser::where('email', strtolower(trim($input['email'])))->first();
        if (! Hash::check($input['password'], $staff?->password ?? '$2y$12$6DrRcsvEsITrHwvUFlbak.GTT6luYjju.1YqomH4ylVa42OU57nPW') || ! $staff?->active) {
            StaffAudit::record('login.failed', $staff?->id, 'staff', 'denied');

            return ApiResponse::error('INVALID_CREDENTIALS', 'The email or password is incorrect.', 422);
        }
        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(['staff_pending_id' => $staff->id, 'staff_pending_at' => now()->timestamp, 'staff_pending_version' => $staff->auth_version]);
        StaffAudit::record('login.password_verified', $staff->id, 'staff:'.$staff->id);

        return $this->ok(['state' => $staff->two_factor_confirmed_at ? 'two_factor_required' : 'enrollment_required']);
    }

    private function pending(Request $request): ?StaffUser
    {
        $staff = StaffUser::find($request->session()->get('staff_pending_id'));
        if (! $staff || ! $staff->active || $staff->auth_version !== $request->session()->get('staff_pending_version') || now()->timestamp - $request->session()->get('staff_pending_at', 0) > config('staff.challenge_seconds')) {
            $request->session()->forget(['staff_pending_id', 'staff_pending_at', 'staff_pending_version']);

            return null;
        }

        return $staff;
    }

    public function enrollment(Request $request)
    {
        $staff = $this->pending($request);
        if (! $staff || $staff->two_factor_confirmed_at) {
            return ApiResponse::error('CHALLENGE_EXPIRED', 'Sign in again to begin authenticator setup.', 401);
        }

        return DB::transaction(function () use ($staff) {
            $staff = StaffUser::lockForUpdate()->findOrFail($staff->id);
            if (! $staff->two_factor_secret) {
                $staff->two_factor_secret = (new Google2FA)->generateSecretKey(32);
                $staff->save();
                StaffAudit::record('mfa.enrollment_started', $staff->id, 'staff:'.$staff->id);
            }

            return $this->ok(['secret' => $staff->two_factor_secret, 'uri' => 'otpauth://totp/'.rawurlencode('ELVARQO staff:'.$staff->email).'?secret='.$staff->two_factor_secret.'&issuer=ELVARQO%20staff&algorithm=SHA1&digits=6&period=30']);
        });
    }

    private function factorInput(Request $request): array
    {
        return $request->validate(['code' => 'nullable|required_without:recovery_code|prohibits:recovery_code|string|regex:/^[0-9]{6}$/D', 'recovery_code' => 'nullable|required_without:code|prohibits:code|string|max:80']);
    }

    public function challenge(Request $request, StaffFactors $factors)
    {
        $input = $this->factorInput($request);
        $pending = $this->pending($request);
        if (! $pending) {
            return ApiResponse::error('CHALLENGE_EXPIRED', 'Your verification step expired. Sign in again.', 401);
        }
        $result = DB::transaction(function () use ($pending, $input, $factors) {
            $staff = StaffUser::lockForUpdate()->findOrFail($pending->id);
            if ($staff->auth_version !== $pending->auth_version || ! $staff->active || ! $factors->verify($staff, $input['code'] ?? null, $input['recovery_code'] ?? null)) {
                StaffAudit::record('mfa.failed', $staff->id, 'staff:'.$staff->id, 'denied');

                return null;
            }
            $codes = [];
            if (! $staff->two_factor_confirmed_at) {
                $staff->two_factor_confirmed_at = now();
                $staff->save();
                $codes = $factors->rotate($staff);
                StaffAudit::record('mfa.enrollment_completed', $staff->id, 'staff:'.$staff->id);
            }

            return [$staff, $codes];
        });
        if (! $result) {
            return ApiResponse::error('INVALID_SECOND_FACTOR', 'The code is invalid, expired or already used. Try a current authenticator code or unused recovery code.', 422);
        }
        [$staff,$codes] = $result;
        $request->session()->forget(['staff_pending_id', 'staff_pending_at', 'staff_pending_version']);
        Auth::guard('staff')->login($staff);
        $request->session()->regenerate();
        $request->session()->put(['staff_version' => $staff->auth_version, 'staff_authenticated_at' => now()->timestamp, 'staff_seen_at' => now()->timestamp]);
        StaffAudit::record('login.completed', $staff->id, 'staff:'.$staff->id);

        return $this->ok(['staff' => $staff->publicIdentity(), 'recovery_codes' => $codes]);
    }

    public function me()
    {
        return $this->ok(['staff' => Auth::guard('staff')->user()->publicIdentity()]);
    }

    public function logout(Request $request)
    {
        $staff = Auth::guard('staff')->user();
        StaffAudit::record('logout', $staff->id, 'staff:'.$staff->id);
        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->ok(['message' => 'Signed out.']);
    }

    public function forgot(Request $request)
    {
        $input = $request->validate(['email' => 'required|string|email|max:254']);
        $email = strtolower(trim($input['email']));
        try {
            Password::broker('staff')->sendResetLink(['email' => $email]);
        } catch (\Throwable $exception) {
            Log::error('Staff password reset delivery failed.', ['exception_class' => get_class($exception)]);
            StaffAudit::record('password.delivery_failed', null, 'staff', 'failed');
        }
        StaffAudit::record('password.reset_requested', null, 'staff');

        return $this->ok(['message' => 'If this address belongs to an active staff account, a reset link will be sent.']);
    }

    public function reset(Request $request)
    {
        $input = $request->validate(['email' => 'required|string|email|max:254', 'token' => 'required|string|max:200', 'password' => ['required', 'string', 'confirmed', 'max:128', PasswordRule::min(14)->letters()->mixedCase()->numbers()->symbols()]]);
        $input['email'] = strtolower(trim($input['email']));
        $status = DB::transaction(function () use ($input) {
            DB::table('staff_password_reset_tokens')->where('email', $input['email'])->lockForUpdate()->first();

            return Password::broker('staff')->reset($input, function (StaffUser $staff, string $password) {
                $staff = StaffUser::lockForUpdate()->findOrFail($staff->id);
                if (! $staff->active) {
                    throw ValidationException::withMessages(['token' => 'This recovery link is invalid or expired.']);
                }
                $staff->password = $password;
                $staff->remember_token = Str::random(60);
                $staff->auth_version++;
                $staff->save();
                DB::table('staff_sessions')->where('user_id', $staff->id)->delete();
                StaffAudit::record('password.reset_completed', $staff->id, 'staff:'.$staff->id);
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            StaffAudit::record('password.reset_failed', null, 'staff', 'denied');

            return ApiResponse::error('RESET_LINK_INVALID', 'This recovery link is invalid, expired or already used. Request a new link.', 422);
        }

        return $this->ok(['message' => 'Password updated. Sign in again with your authenticator or recovery code.']);
    }

    public function recoveryCodes(Request $request, StaffFactors $factors)
    {
        $request->validate(['password' => 'required|string|max:128']);
        $input = $this->factorInput($request);

        return DB::transaction(function () use ($request, $input, $factors) {
            $staff = StaffUser::lockForUpdate()->findOrFail(Auth::guard('staff')->id());
            if (! Hash::check($request->string('password')->toString(), $staff->password) || ! $factors->verify($staff, $input['code'] ?? null, $input['recovery_code'] ?? null)) {
                StaffAudit::record('recovery.rotation_failed', $staff->id, 'staff:'.$staff->id, 'denied');

                return ApiResponse::error('REAUTH_FAILED', 'Check your password and use a fresh authenticator or unused recovery code.', 422);
            }
            $codes = $factors->rotate($staff);
            StaffAudit::record('recovery.rotated', $staff->id, 'staff:'.$staff->id);

            return $this->ok(['recovery_codes' => $codes]);
        });
    }

    public function capability(Request $request)
    {
        return $this->ok(['permission' => $request->route('permission'), 'authorized' => true]);
    }

    public function audit()
    {
        return $this->ok(['events' => StaffAuditEvent::orderByDesc('created_at')->orderByDesc('id')->limit(50)->get(['id', 'staff_user_id', 'action', 'target', 'result', 'request_id', 'created_at'])->toArray()]);
    }
}
