<?php

use App\AfterSales\Exchanges;
use App\AfterSales\Refunds;
use App\Commerce\Checkout;
use App\Commerce\WiseBankTransfer;
use App\Fulfilment\Tracking;
use App\Jobs\DeliverCustomerNotification;
use App\Jobs\DeliverOrderNotification;
use App\Jobs\DeliverSandboxOrderMail;
use App\Jobs\FoundationProbe;
use App\Models\StaffRole;
use App\Models\StaffUser;
use App\Security\StaffAudit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

Artisan::command('foundation:probe', function () {
    if (! app()->environment('local') || config('mail.default') !== 'log' || config('queue.default') !== 'database') {
        $this->error('This probe requires APP_ENV=local, MAIL_MAILER=log and QUEUE_CONNECTION=database.');

        return 1;
    }
    $id = (string) Str::uuid();
    FoundationProbe::dispatch($id);
    $this->info('Queued probe '.$id.'. Run the worker, then inspect storage/app/private/foundation and storage/logs/laravel.log.');
})->purpose('Exercise the local database queue, sandbox log mail and local storage');

Artisan::command('staff:create {email} {--name=} {--role=owner}', function () {
    $email = strtolower(trim($this->argument('email')));
    $role = StaffRole::where('name', $this->option('role'))->first();
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $role || StaffUser::where('email', $email)->exists()) {
        $this->error('Use a unique valid email and an existing role: owner, operations, support or finance.');

        return 1;
    }
    $name = trim($this->option('name') ?: $this->ask('Staff name'));
    $password = $this->secret('Choose a password (14+ characters, upper/lowercase, number and symbol)');
    $confirmation = $this->secret('Confirm password');
    $validation = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], ['name' => 'required|string|max:100', 'email' => 'required|email|max:254', 'password' => ['required', 'string', 'confirmed', 'max:128', Password::min(14)->letters()->mixedCase()->numbers()->symbols()]]);
    if ($validation->fails()) {
        $this->error('Name, email or password did not meet the requirements. Nothing was created.');

        return 1;
    }
    DB::transaction(function () use ($name, $email, $password, $role) {
        $staff = StaffUser::create(['name' => $name, 'email' => $email, 'password' => $password, 'staff_role_id' => $role->id]);
        StaffAudit::record('staff.created', null, 'staff:'.$staff->id);
    });
    $this->info('Staff account created. Sign in to the admin and complete mandatory authenticator setup.');
})->purpose('Secure interactive staff bootstrap; no public registration or default password');

Artisan::command('staff:recover-mfa {email}', function () {
    $staff = StaffUser::where('email', strtolower(trim($this->argument('email'))))->first();
    if (! $staff || ! $staff->active) {
        $this->error('Active staff account not found.');

        return 1;
    }
    if (! $this->confirm('This revokes every session and all recovery codes, and requires authenticator enrollment again. Continue?')) {
        return 1;
    }
    DB::transaction(function () use ($staff) {
        $staff = StaffUser::lockForUpdate()->findOrFail($staff->id);
        $staff->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'last_totp_step' => null, 'auth_version' => $staff->auth_version + 1])->save();
        DB::table('staff_recovery_codes')->where('staff_user_id', $staff->id)->delete();
        DB::table('staff_sessions')->where('user_id', $staff->id)->delete();
        StaffAudit::record('mfa.cli_recovery', null, 'staff:'.$staff->id);
    });
    $this->info('Sessions and second factors revoked. Password is still required; re-enroll at the next sign-in.');
})->purpose('Trusted server-console recovery after independently verifying staff ownership');

Artisan::command('staff:set-role {email} {role}', function () {
    return DB::transaction(function () {
        $owners = StaffUser::whereHas('role', fn ($q) => $q->where('name', 'owner'))->where('active', true)->lockForUpdate()->get();
        $staff = StaffUser::where('email', strtolower(trim($this->argument('email'))))->lockForUpdate()->first();
        $role = StaffRole::where('name', $this->argument('role'))->first();
        if (! $staff || ! $role || ($staff->role->name === 'owner' && $staff->active && $role->name !== 'owner' && $owners->count() <= 1)) {
            $this->error('Invalid account/role, or this would remove the last active owner.');

            return 1;
        }
        $staff->staff_role_id = $role->id;
        $staff->auth_version++;
        $staff->save();
        DB::table('staff_sessions')->where('user_id', $staff->id)->delete();
        StaffAudit::record('staff.role_changed', null, 'staff:'.$staff->id);
        $this->info('Role updated and sessions revoked.');

        return 0;
    });
})->purpose('Trusted CLI role assignment with last-owner protection and session revocation');

Artisan::command('commerce:reconcile', function () {
    $errors = 0;
    foreach (DB::table('commerce_orders')->whereIn('payment_state', ['creating', 'open', 'pending'])->whereNotExists(function ($q) {
        $q->selectRaw('1')->from('manual_order_attempts as m')->join('commerce_payments as p', 'p.order_id', '=', 'm.order_id')->whereColumn('m.order_id', 'commerce_orders.id')->whereNull('p.creation_started_at');
    })->orderBy('created_at')->limit(500)->pluck('id') as $id) {
        try {
            app(Checkout::class)->reconcile($id, true);
        } catch (Throwable $e) {
            $errors++;
        }
    }
    $this->info('Reconciliation finished. Unresolved provider requests: '.$errors.'. Stock retained safely; inspect read-only orders.');

    return $errors ? 1 : 0;
})->purpose('Reconcile sandbox evidence and expire abandoned hosted sessions without releasing uncertain stock');
Artisan::command('commerce:mail', function () {
    foreach (config('hosting.enqueue_notifications', true) ? DB::table('commerce_mail_outbox')->whereNull('delivered_at')->limit(500)->pluck('order_id') : [] as $id) {
        DeliverSandboxOrderMail::dispatch($id);
    }$this->info('Pending private sandbox confirmations queued.');
});
Schedule::command('commerce:reconcile')->everyMinute()->withoutOverlapping();
Schedule::command('commerce:mail')->everyMinute()->withoutOverlapping();

Artisan::command('orders:maintenance', function () {
    foreach (DB::table('commerce_orders')->whereIn('payment_state', ['awaiting_payment_link', 'creating'])->where('reservation_expires_at', '<=', now())->pluck('id') as $id) {
        app(Checkout::class)->expireUnattemptedManual($id);
    }
    foreach (config('hosting.enqueue_notifications', true) ? DB::table('order_notifications')->whereNull('delivered_at')->limit(500)->pluck('id') : [] as $id) {
        DeliverOrderNotification::dispatch($id);
    }
    $this->info('Eligible unattempted manual invitations expired; pending private order notifications queued.');
})->purpose('Expire unattempted manual orders and queue local order notifications');
Schedule::command('orders:maintenance')->everyMinute()->withoutOverlapping();

Artisan::command('courier:sandbox-event {shipment} {state}', function () {
    if (! app()->environment('local') || strlen(config('fulfilment.webhook_secret')) < 32) {
        $this->error('Requires local environment and a configured 32+ character sandbox webhook secret.');

        return 1;
    }
    $f = DB::table('order_fulfilments')->where('id', $this->argument('shipment'))->first();
    $state = $this->argument('state');
    if (! $f || ! $f->provider_reference || ! in_array($state, ['in_transit', 'out_for_delivery', 'delivered', 'exception'])) {
        $this->error('Choose a booked sandbox shipment and an allowed tracking state.');

        return 1;
    }
    $body = json_encode(['id' => 'cli_'.Str::uuid(), 'provider_reference' => $f->provider_reference, 'tracking_number' => $f->tracking_number, 'state' => $state, 'occurred_at' => now()->toISOString()]);
    $t = time();
    $signature = hash_hmac('sha256', $t.'.'.$body, config('fulfilment.webhook_secret'));
    app(Tracking::class)->event($body, 't='.$t.',v1='.$signature);
    $this->info('Signed local sandbox tracking event processed. No real carrier event or shipment exists.');

    return 0;
})->purpose('Local-only signed courier event simulator; not a real courier callback');

Artisan::command('after-sales:maintenance', function () {
    app(Exchanges::class)->expire();
    foreach (config('hosting.enqueue_notifications', true) ? DB::table('customer_notifications')->whereNull('delivered_at')->limit(500)->pluck('id') : [] as $id) {
        DeliverCustomerNotification::dispatch($id);
    }
    if (app()->environment(['local', 'testing'])) {
        foreach (DB::table('refund_attempts')->whereIn('state', ['sandbox_unknown', 'sandbox_pending'])->limit(500)->pluck('id') as $id) {
            app(Refunds::class)->process($id);
        }
    }
    $this->info('Expired exchange holds, queued private account mail and reconciled local sandbox refund evidence. No real refunds.');
});
Schedule::command('after-sales:maintenance')->everyMinute()->withoutOverlapping();
Artisan::command('refund:sandbox-event {attempt} {state}', function () {
    if (! app()->environment('local') || ! in_array($this->argument('state'), ['succeeded', 'failed'])) {
        return 1;
    }
    $id = $this->argument('attempt');
    $receipt = DB::table('sandbox_refund_receipts')->where('attempt_id', $id)->first();
    if (! $receipt || $receipt->state !== 'pending') {
        $this->error('Only a pending local receipt can receive a terminal sandbox outcome.');

        return 1;
    }
    DB::table('sandbox_refund_receipts')->where('attempt_id', $id)->where('state', 'pending')->update(['state' => $this->argument('state'), 'updated_at' => now()]);
    app(Refunds::class)->process($id);
    $this->info('Local sandbox evidence reconciled. No real money moved.');

    return 0;
});

Artisan::command('commerce:wise-maintenance', function () {
    foreach (DB::table('commerce_orders')->where('payment_method', 'wise_bank_transfer')->where('payment_state', 'awaiting_bank_transfer')->where('reservation_expires_at', '<=', now())->orderBy('created_at')->limit(500)->pluck('id') as $id) {
        app(WiseBankTransfer::class)->expire($id);
    }
    $this->info('Expired unpaid Wise reservations released. No bank calls or fund movements performed.');
});
Schedule::command('commerce:wise-maintenance')->everyMinute()->withoutOverlapping();
