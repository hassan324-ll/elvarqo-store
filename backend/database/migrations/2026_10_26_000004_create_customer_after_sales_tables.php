<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('email')->unique();
            $t->string('password');
            $t->unsignedInteger('auth_version')->default(1);
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamps();
        });
        Schema::create('customer_sessions', function (Blueprint $t) {
            $t->string('token_hash', 64)->primary();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('auth_version');
            $t->timestamp('seen_at');
            $t->timestamp('expires_at');
            $t->timestamp('created_at');
        });
        Schema::create('customer_challenges', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('purpose');
            $t->string('token_hash', 64)->unique();
            $t->text('payload');
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('customer_notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->text('encrypted_payload');
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('customer_orders', function (Blueprint $t) {
            $t->foreignUuid('order_id')->primary()->constrained('commerce_orders')->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->timestamp('created_at');
        });
        Schema::create('customer_addresses', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->text('payload');
            $t->unsignedInteger('version')->default(1);
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });
        Schema::create('customer_saved_items', function (Blueprint $t) {
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('variant_id')->nullable();
            $t->timestamp('created_at');
            $t->primary(['customer_id', 'product_id']);
        });
        Schema::create('after_sales_returns', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->foreignId('order_item_id')->constrained('commerce_order_items')->restrictOnDelete();
            $t->string('fingerprint', 64);
            $t->unsignedInteger('quantity');
            $t->text('units');
            $t->unsignedInteger('credit_minor');
            $t->string('reason');
            $t->string('resolution');
            $t->unsignedBigInteger('exchange_variant_id')->nullable();
            $t->text('comments');
            $t->string('state');
            $t->timestamps();
        });
        Schema::create('return_unit_claims', function (Blueprint $t) {
            $t->foreignId('order_item_id')->constrained('commerce_order_items')->restrictOnDelete();
            $t->unsignedInteger('unit');
            $t->foreignUuid('return_id')->constrained('after_sales_returns')->restrictOnDelete();
            $t->primary(['order_item_id', 'unit']);
        });
        Schema::create('return_inspections', function (Blueprint $t) {
            $t->foreignUuid('return_id')->primary()->constrained('after_sales_returns')->restrictOnDelete();
            $t->foreignId('staff_user_id')->constrained('staff_users')->restrictOnDelete();
            $t->string('outcome');
            $t->unsignedInteger('received_quantity');
            $t->foreignId('location_id')->constrained('stock_locations')->restrictOnDelete();
            $t->text('reason');
            $t->string('fingerprint', 64);
            $t->timestamp('created_at');
        });
        Schema::create('after_sales_stock_events', function (Blueprint $t) {
            $t->foreignUuid('return_id')->primary()->constrained('after_sales_returns')->restrictOnDelete();
            $t->foreignId('balance_id')->constrained('stock_balances')->restrictOnDelete();
            $t->integer('delta');
            $t->unsignedInteger('on_hand_after');
            $t->timestamp('created_at');
        });
        Schema::create('exchange_links', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('return_id')->constrained('after_sales_returns')->restrictOnDelete();
            $t->foreignUuid('replacement_order_id')->unique()->constrained('commerce_orders')->restrictOnDelete();
            $t->string('fingerprint', 64);
            $t->unsignedInteger('credit_minor');
            $t->integer('difference_minor');
            $t->string('state');
            $t->timestamp('created_at');
        });
        Schema::create('refund_attempts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('return_id')->constrained('after_sales_returns')->restrictOnDelete();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->foreignId('staff_user_id')->constrained('staff_users')->restrictOnDelete();
            $t->unsignedInteger('amount_minor');
            $t->string('currency', 3);
            $t->text('reason');
            $t->string('scenario');
            $t->string('fingerprint', 64);
            $t->string('state');
            $t->string('last_error')->nullable();
            $t->timestamps();
        });
        Schema::create('sandbox_refund_receipts', function (Blueprint $t) {
            $t->foreignUuid('attempt_id')->primary()->constrained('refund_attempts')->restrictOnDelete();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->unsignedInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('state');
            $t->string('provider_reference')->unique();
            $t->timestamps();
        });
        Schema::create('after_sales_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('return_id')->constrained('after_sales_returns')->restrictOnDelete();
            $t->string('kind');
            $t->text('payload');
            $t->timestamp('created_at');
        });
        if (DB::getDriverName() === 'sqlite') {
            foreach (['return_inspections', 'after_sales_stock_events', 'after_sales_events', 'customer_orders'] as $table) {
                foreach (['UPDATE', 'DELETE'] as $op) {
                    DB::unprepared("CREATE TRIGGER {$table}_no_{$op} BEFORE {$op} ON {$table} BEGIN SELECT RAISE(ABORT,'Immutable after sales evidence'); END");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['after_sales_events', 'sandbox_refund_receipts', 'refund_attempts', 'exchange_links', 'after_sales_stock_events', 'return_inspections', 'return_unit_claims', 'after_sales_returns', 'customer_saved_items', 'customer_addresses', 'customer_orders', 'customer_notifications', 'customer_challenges', 'customer_sessions', 'customers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
