<?php

use App\Security\StaffPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('permissions');
        });
        foreach (StaffPermissions::ROLES as $name => $permissions) {
            DB::table('staff_roles')->insert(['name' => $name, 'permissions' => json_encode($permissions)]);
        }
        Schema::create('staff_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('staff_role_id')->constrained('staff_roles');
            $table->boolean('active')->default(true);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('last_totp_step')->nullable();
            $table->unsignedInteger('auth_version')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('staff_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('staff_users')->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at');
        });
        Schema::create('staff_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('staff_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        Schema::create('staff_audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('staff_user_id')->nullable()->index();
            $table->string('action', 80);
            $table->string('target', 100)->nullable();
            $table->string('result', 20);
            $table->string('request_id', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->index();
        });
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER staff_audit_no_update BEFORE UPDATE ON staff_audit_events BEGIN SELECT RAISE(ABORT, 'Audit events are append-only'); END");
            DB::unprepared("CREATE TRIGGER staff_audit_no_delete BEFORE DELETE ON staff_audit_events BEGIN SELECT RAISE(ABORT, 'Audit events are append-only'); END");
        } elseif ($driver === 'mysql') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared('CREATE TRIGGER staff_audit_no_'.strtolower($operation)." BEFORE $operation ON staff_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit events are append-only'");
            }
        } elseif ($driver === 'pgsql') {
            DB::unprepared("CREATE FUNCTION staff_audit_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Audit events are append-only'; END $$");
            DB::unprepared('CREATE TRIGGER staff_audit_immutable BEFORE UPDATE OR DELETE ON staff_audit_events FOR EACH ROW EXECUTE FUNCTION staff_audit_immutable()');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_audit_events');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS staff_audit_immutable()');
        }
        foreach (['staff_sessions', 'staff_password_reset_tokens', 'staff_recovery_codes', 'staff_users', 'staff_roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
