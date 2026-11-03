<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_orders', function (Blueprint $t) {
            $t->string('payment_method')->default('stripe_sandbox');
        });
        Schema::create('wise_bank_transfers', function (Blueprint $t) {
            $t->foreignUuid('order_id')->primary()->constrained('commerce_orders')->restrictOnDelete();
            $t->text('instructions');
            $t->uuid('operation_id')->nullable()->unique();
            $t->string('fingerprint', 64)->nullable();
            $t->string('receipt_hash', 64)->nullable()->unique();
            $t->text('receipt_reference')->nullable();
            $t->foreignId('confirmed_by')->nullable()->constrained('staff_users')->restrictOnDelete();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('created_at');
        });
        $role = DB::table('staff_roles')->where('name', 'owner')->first();
        if ($role) {
            $permissions = array_values(array_unique([...json_decode($role->permissions, true), 'finance.confirm']));
            DB::table('staff_roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wise_bank_transfers');
        Schema::table('commerce_orders', fn (Blueprint $t) => $t->dropColumn('payment_method'));
        $role = DB::table('staff_roles')->where('name', 'owner')->first();
        if ($role) {
            DB::table('staff_roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_diff(json_decode($role->permissions, true), ['finance.confirm'])))]);
        }
    }
};
