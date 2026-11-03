<?php

namespace Tests\Feature;

use App\Models\StaffAuditEvent;
use App\Models\StaffRole;
use App\Models\StaffUser;
use App\Models\User;
use App\Notifications\StaffPasswordReset;
use App\Security\StaffAudit;
use App\Security\StaffFactors;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class StaffSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'TestOnly-Staff-Password-42!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfMinute()->addSeconds(5));
    }

    private function staff(string $role = 'owner', bool $enrolled = true): StaffUser
    {
        return StaffUser::create(['name' => 'Test Staff', 'email' => uniqid().'@example.test', 'password' => self::PASSWORD, 'staff_role_id' => StaffRole::where('name', $role)->value('id'),
            'two_factor_secret' => $enrolled ? (new Google2FA)->generateSecretKey(32) : null, 'two_factor_confirmed_at' => $enrolled ? now() : null]);
    }

    private function otp(StaffUser $staff): string
    {
        return (new Google2FA)->oathTotp($staff->fresh()->two_factor_secret, (int) floor(now()->timestamp / 30));
    }

    private function send(string $method, string $path, array $data = [], bool $csrf = true)
    {
        $headers = ['Origin' => config('staff.admin_origin'), 'Accept' => 'application/json'];
        if ($method === 'POST' && $csrf) {
            $headers['X-CSRF-TOKEN'] = $this->send('GET', 'csrf')->json('data.csrf_token');
        }
        Auth::forgetGuards();
        $response = $this->json($method, '/api/v1/staff/'.$path, $data, $headers);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }

        return $response;
    }

    private function login(StaffUser $staff)
    {
        return $this->send('POST', 'login', ['email' => $staff->email, 'password' => self::PASSWORD]);
    }

    private function authenticate(StaffUser $staff)
    {
        $this->login($staff)->assertOk();

        return $this->send('POST', 'two-factor', ['code' => $this->otp($staff)])->assertOk();
    }

    public function test_owner_bootstrap_uses_hidden_prompts_without_a_public_registration_route(): void
    {
        $this->artisan('staff:create', ['email' => 'bootstrap@example.test', '--name' => 'Bootstrap Owner'])
            ->expectsQuestion('Choose a password (14+ characters, upper/lowercase, number and symbol)', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)->assertSuccessful();
        $owner = StaffUser::where('email', 'bootstrap@example.test')->firstOrFail();
        $this->assertSame('owner', $owner->role->name);
        $this->assertTrue(Hash::check(self::PASSWORD, $owner->password));
        $this->assertNull($owner->two_factor_confirmed_at);
        $this->send('POST', 'register', ['email' => 'public@example.test', 'password' => self::PASSWORD])->assertNotFound();
    }

    public function test_visitors_and_password_only_sessions_cannot_enter_admin(): void
    {
        $this->send('GET', 'me')->assertUnauthorized();
        $this->send('GET', 'capabilities/finance.refund')->assertUnauthorized();
        $staff = $this->staff();
        $this->login($staff)->assertJsonPath('data.state', 'two_factor_required');
        $this->send('GET', 'me')->assertUnauthorized();
        $this->send('GET', 'audit-events')->assertUnauthorized();
    }

    public function test_csrf_is_required_even_when_laravel_test_bypass_is_active(): void
    {
        $this->send('POST', 'login', ['email' => 'any@example.test', 'password' => 'bad'], false)->assertStatus(419);
    }

    public function test_staff_origin_and_credentialed_cors_are_scoped(): void
    {
        $this->getJson('/api/v1/staff/csrf', ['Origin' => 'http://localhost:3000'])->assertForbidden()->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->getJson('/api/v1/staff/csrf', ['Origin' => config('staff.admin_origin')])->assertOk()->assertHeader('Access-Control-Allow-Credentials', 'true')->assertHeader('Access-Control-Allow-Origin', config('staff.admin_origin'));
        $this->getJson('/api/v1/health', ['Origin' => 'http://localhost:3000'])->assertOk()->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_bad_credentials_are_neutral_and_throttled(): void
    {
        $staff = $this->staff();
        $known = $this->send('POST', 'login', ['email' => $staff->email, 'password' => 'wrong'])->assertUnprocessable()->json('error.message');
        $unknown = $this->send('POST', 'login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertUnprocessable()->json('error.message');
        $this->assertSame($known, $unknown);
        for ($i = 0; $i < 4; $i++) {
            $this->send('POST', 'login', ['email' => $staff->email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->send('POST', 'login', ['email' => $staff->email, 'password' => 'wrong'])->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_mandatory_enrollment_encrypts_seed_and_shows_hashed_recovery_codes_once(): void
    {
        $staff = $this->staff(enrolled: false);
        $this->login($staff)->assertJsonPath('data.state', 'enrollment_required');
        $setup = $this->send('GET', 'enrollment')->assertOk();
        $secret = $setup->json('data.secret');
        $this->assertNotSame($secret, $staff->fresh()->getRawOriginal('two_factor_secret'));
        $this->send('GET', 'me')->assertUnauthorized();
        $response = $this->send('POST', 'two-factor', ['code' => $this->otp($staff)])->assertOk();
        $codes = $response->json('data.recovery_codes');
        $this->assertCount(10, $codes);
        $this->assertDatabaseCount('staff_recovery_codes', 10);
        $this->assertDatabaseHas('staff_recovery_codes', ['code_hash' => hash('sha256', str_replace('-', '', $codes[0]))]);
        $this->send('GET', 'me')->assertOk()->assertJsonMissingPath('data.staff.two_factor_secret')->assertJsonMissingPath('data.recovery_codes');
        $this->assertNotNull($staff->fresh()->two_factor_confirmed_at);
        $audit = StaffAuditEvent::all()->toJson();
        $this->assertStringNotContainsString($secret, $audit);
        $this->assertStringNotContainsString($codes[0], $audit);
        $this->assertStringNotContainsString(self::PASSWORD, $audit);
    }

    public function test_totp_replay_and_old_challenges_are_rejected(): void
    {
        $staff = $this->staff();
        $code = $this->otp($staff);
        $this->authenticate($staff);
        $this->send('POST', 'logout')->assertOk();
        $this->login($staff)->assertOk();
        $this->send('POST', 'two-factor', ['code' => $code])->assertUnprocessable();
        $this->travel(31)->seconds();
        $this->send('POST', 'two-factor', ['code' => $this->otp($staff)])->assertOk();
        $this->send('POST', 'logout');
        $this->login($staff);
        $this->travel(301)->seconds();
        $this->send('POST', 'two-factor', ['code' => $this->otp($staff)])->assertUnauthorized()->assertJsonPath('error.code', 'CHALLENGE_EXPIRED');
    }

    public function test_recovery_codes_are_single_use_and_expire(): void
    {
        $staff = $this->staff();
        $codes = app(StaffFactors::class)->rotate($staff);
        $this->login($staff);
        $this->send('POST', 'two-factor', ['recovery_code' => $codes[0]])->assertOk();
        $this->send('POST', 'logout');
        $this->login($staff);
        $this->send('POST', 'two-factor', ['recovery_code' => $codes[0]])->assertUnprocessable();
        DB::table('staff_recovery_codes')->where('code_hash', hash('sha256', str_replace('-', '', $codes[1])))->update(['expires_at' => now()->subSecond()]);
        $this->send('POST', 'two-factor', ['recovery_code' => $codes[1]])->assertUnprocessable();
    }

    public function test_logout_revokes_the_server_session(): void
    {
        $staff = $this->staff();
        $this->authenticate($staff);
        $this->send('GET', 'me')->assertOk();
        $this->send('POST', 'logout')->assertOk();
        $this->send('GET', 'me')->assertUnauthorized();
    }

    public function test_idle_and_absolute_expiry_require_new_login(): void
    {
        $staff = $this->staff();
        $this->authenticate($staff);
        $this->travel(1801)->seconds();
        $this->send('GET', 'me')->assertUnauthorized();
        config(['staff.idle_seconds' => 40000]);
        $this->authenticate($staff);
        $this->travel(28801)->seconds();
        $this->send('GET', 'me')->assertUnauthorized()->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }

    public function test_support_cannot_bypass_finance_or_staff_permissions_with_direct_requests(): void
    {
        $staff = $this->staff('support');
        $this->authenticate($staff);
        $this->send('GET', 'capabilities/catalogue.view')->assertOk();
        $this->send('GET', 'capabilities/finance.refund')->assertForbidden();
        $this->send('GET', 'capabilities/staff.manage')->assertForbidden();
        $this->send('GET', 'audit-events')->assertForbidden();
        $this->assertDatabaseHas('staff_audit_events', ['staff_user_id' => $staff->id, 'action' => 'permission.denied', 'target' => 'finance.refund', 'result' => 'denied']);
    }

    public function test_customer_guard_cannot_be_substituted_for_staff(): void
    {
        $user = User::create(['name' => 'Customer', 'email' => 'customer@example.test', 'password' => self::PASSWORD]);
        $this->actingAs($user, 'web');
        $this->getJson('/api/v1/staff/me')->assertUnauthorized();
        $staff = $this->staff();
        $this->authenticate($staff);
        $this->assertTrue(Auth::guard('web')->guest());
    }

    public function test_account_changes_invalidate_authenticated_and_pending_sessions(): void
    {
        $staff = $this->staff();
        $this->authenticate($staff);
        $staff->increment('auth_version');
        $this->send('GET', 'me')->assertUnauthorized();
        $this->login($staff);
        $staff->increment('auth_version');
        $this->send('POST', 'two-factor', ['code' => $this->otp($staff)])->assertUnauthorized();
        $this->login($staff);
        $staff->update(['active' => false]);
        $this->send('POST', 'two-factor', ['code' => $this->otp($staff)])->assertUnauthorized();
    }

    public function test_reset_requests_do_not_disclose_membership_and_use_separate_broker(): void
    {
        Notification::fake();
        $staff = $this->staff();
        $known = $this->send('POST', 'forgot-password', ['email' => $staff->email])->assertOk()->json('data.message');
        $unknown = $this->send('POST', 'forgot-password', ['email' => 'nobody@example.test'])->assertOk()->json('data.message');
        $this->assertSame($known, $unknown);
        Notification::assertSentTo($staff, StaffPasswordReset::class);
        $this->assertDatabaseCount('staff_password_reset_tokens', 1);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_local_staff_mail_goes_to_private_storage_without_logging_token(): void
    {
        Storage::fake('local');
        config(['mail.default' => 'log']);
        $staff = $this->staff();
        $this->send('POST', 'forgot-password', ['email' => $staff->email])->assertOk();
        $files = Storage::disk('local')->files('staff-mail');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('?reset_token=', Storage::disk('local')->get($files[0]));
    }

    public function test_password_reset_expiry_reuse_and_revocation_preserve_mfa(): void
    {
        $staff = $this->staff();
        $this->authenticate($staff);
        $version = $staff->fresh()->auth_version;
        $token = Password::broker('staff')->createToken($staff);
        $this->travel(1801)->seconds();
        $input = ['email' => $staff->email, 'token' => $token, 'password' => 'Different-Staff-Password-43!', 'password_confirmation' => 'Different-Staff-Password-43!'];
        $this->send('POST', 'reset-password', $input)->assertUnprocessable();
        $token = Password::broker('staff')->createToken($staff);
        $input['token'] = $token;
        $this->send('POST', 'reset-password', $input)->assertOk();
        $this->assertSame($version + 1, $staff->fresh()->auth_version);
        $this->assertNotNull($staff->fresh()->two_factor_confirmed_at);
        $this->send('GET', 'me')->assertUnauthorized();
        $this->send('POST', 'reset-password', $input)->assertUnprocessable();
        $this->assertTrue(Hash::check($input['password'], $staff->fresh()->password));
        $this->send('POST', 'login', ['email' => $staff->email, 'password' => $input['password']])->assertOk()->assertJsonPath('data.state', 'two_factor_required');
    }

    public function test_regeneration_requires_password_and_fresh_second_factor(): void
    {
        $staff = $this->staff();
        $old = app(StaffFactors::class)->rotate($staff);
        $this->authenticate($staff);
        $this->send('POST', 'recovery-codes', ['password' => self::PASSWORD, 'code' => $this->otp($staff)])->assertUnprocessable();
        $this->travel(31)->seconds();
        $response = $this->send('POST', 'recovery-codes', ['password' => self::PASSWORD, 'code' => $this->otp($staff)])->assertOk();
        $this->assertCount(10, $response->json('data.recovery_codes'));
        $this->assertDatabaseMissing('staff_recovery_codes', ['code_hash' => hash('sha256', str_replace('-', '', $old[0]))]);
    }

    public function test_audit_history_is_append_only_in_model_and_database(): void
    {
        StaffAudit::record('test.event');
        $event = StaffAuditEvent::first();
        try {
            $event->update(['result' => 'changed']);
            $this->fail('Audit model update accepted');
        } catch (\LogicException) {
        }
        try {
            DB::table('staff_audit_events')->where('id', $event->id)->delete();
            $this->fail('Audit database delete accepted');
        } catch (QueryException) {
        }
        $this->assertDatabaseCount('staff_audit_events', 1);
    }

    public function test_owner_can_read_real_security_events_and_authorized_capabilities(): void
    {
        $staff = $this->staff();
        $this->authenticate($staff);
        $this->send('GET', 'capabilities/staff.manage')->assertOk();
        $this->send('GET', 'capabilities/finance.refund')->assertOk();
        $this->send('GET', 'audit-events')->assertOk()->assertJsonStructure(['data' => ['events' => [['action', 'result', 'created_at']]]]);
    }

    public function test_cli_recovery_revokes_factors_and_last_owner_cannot_be_demoted(): void
    {
        $staff = $this->staff();
        app(StaffFactors::class)->rotate($staff);
        $this->artisan('staff:set-role', ['email' => $staff->email, 'role' => 'support'])->assertFailed();
        $this->artisan('staff:recover-mfa', ['email' => $staff->email])->expectsConfirmation('This revokes every session and all recovery codes, and requires authenticator enrollment again. Continue?', 'yes')->assertSuccessful();
        $this->assertNull($staff->fresh()->two_factor_secret);
        $this->assertDatabaseCount('staff_recovery_codes', 0);
        $this->assertSame(2, $staff->fresh()->auth_version);
    }
}
