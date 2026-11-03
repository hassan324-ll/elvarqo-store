<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_bags', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('token_hash', 64)->unique();
            $t->text('items');
            $t->unsignedInteger('revision')->default(1);
            $t->uuid('active_order_id')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        Schema::create('commerce_orders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('bag_id')->constrained('commerce_bags')->restrictOnDelete();
            $t->string('reference')->unique();
            $t->string('order_state');
            $t->string('payment_state');
            $t->string('fulfilment_state');
            $t->unsignedInteger('total_minor');
            $t->string('currency', 3);
            $t->timestamp('reservation_expires_at')->index();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
        Schema::create('commerce_order_snapshots', function (Blueprint $t) {
            $t->foreignUuid('order_id')->primary()->constrained('commerce_orders')->restrictOnDelete();
            $t->text('payload');
            $t->timestamp('created_at');
        });
        Schema::create('commerce_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->foreignId('variant_id')->constrained('catalogue_variants')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('catalogue_products')->restrictOnDelete();
            $t->string('sku');
            $t->string('title');
            $t->string('colour');
            $t->string('size');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('unit_minor');
            $t->unsignedBigInteger('line_minor');
            $t->string('currency', 3);
            $t->timestamp('created_at');
            $t->unique(['order_id', 'variant_id']);
        });
        Schema::create('commerce_order_addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->string('kind');
            $t->string('first_name', 80);
            $t->string('last_name', 80);
            $t->string('line1', 160);
            $t->string('line2', 160);
            $t->string('city', 100);
            $t->string('postcode', 10);
            $t->string('country', 2);
            $t->timestamp('created_at');
            $t->unique(['order_id', 'kind']);
        });
        Schema::create('commerce_checkout_attempts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('bag_id')->constrained('commerce_bags')->restrictOnDelete();
            $t->foreignUuid('order_id')->unique()->constrained('commerce_orders')->restrictOnDelete();
            $t->string('fingerprint', 64);
            $t->timestamp('created_at');
        });
        Schema::create('commerce_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->foreignId('variant_id')->constrained('catalogue_variants')->restrictOnDelete();
            $t->foreignId('balance_id')->constrained('stock_balances')->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->string('state');
            $t->unique(['order_id', 'balance_id']);
            $t->timestamps();
        });
        Schema::create('commerce_payments', function (Blueprint $t) {
            $t->foreignUuid('order_id')->primary()->constrained('commerce_orders')->restrictOnDelete();
            $t->string('session_id')->nullable()->unique();
            $t->text('checkout_url')->nullable();
            $t->text('request_payload')->nullable();
            $t->timestamp('creation_started_at')->nullable();
            $t->string('last_error')->nullable();
            $t->timestamps();
        });
        Schema::create('commerce_webhook_events', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('type');
            $t->string('session_id');
            $t->string('payload_hash', 64);
            $t->timestamp('created_at');
        });
        Schema::create('commerce_stock_events', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->foreignId('balance_id')->constrained('stock_balances')->restrictOnDelete();
            $t->string('kind');
            $t->integer('on_hand_delta');
            $t->integer('reserved_delta');
            $t->unsignedInteger('on_hand_after');
            $t->unsignedInteger('reserved_after');
            $t->unique(['order_id', 'balance_id', 'kind']);
            $t->timestamp('created_at');
        });
        Schema::create('commerce_mail_outbox', function (Blueprint $t) {
            $t->foreignUuid('order_id')->primary()->constrained('commerce_orders')->restrictOnDelete();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('created_at');
        });
        if (DB::getDriverName() === 'sqlite') {
            foreach (['commerce_order_snapshots', 'commerce_order_items', 'commerce_order_addresses', 'commerce_stock_events', 'commerce_webhook_events'] as $table) {
                foreach (['UPDATE', 'DELETE'] as $op) {
                    DB::unprepared("CREATE TRIGGER {$table}_no_{$op} BEFORE {$op} ON {$table} BEGIN SELECT RAISE(ABORT, 'Immutable commerce record'); END");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['commerce_mail_outbox', 'commerce_stock_events', 'commerce_webhook_events', 'commerce_payments', 'commerce_reservations', 'commerce_checkout_attempts', 'commerce_order_addresses', 'commerce_order_items', 'commerce_order_snapshots', 'commerce_orders', 'commerce_bags'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
