<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_notes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->foreignId('staff_user_id')->constrained('staff_users')->restrictOnDelete();
            $t->text('body');
            $t->timestamp('created_at');
        });
        Schema::create('manual_order_attempts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->unique()->constrained('commerce_orders')->restrictOnDelete();
            $t->foreignId('staff_user_id')->constrained('staff_users')->restrictOnDelete();
            $t->string('fingerprint', 64);
            $t->timestamp('created_at');
        });
        Schema::create('shipment_quotes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->foreignId('staff_user_id')->constrained('staff_users')->restrictOnDelete();
            $t->text('payload');
            $t->string('fingerprint', 64);
            $t->unsignedInteger('cost_minor');
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        Schema::create('order_fulfilments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->foreignUuid('quote_id')->unique()->constrained('shipment_quotes')->restrictOnDelete();
            $t->foreignId('staff_user_id')->constrained('staff_users')->restrictOnDelete();
            $t->foreignId('location_id')->constrained('stock_locations')->restrictOnDelete();
            $t->string('state');
            $t->string('service');
            $t->unsignedInteger('cost_minor');
            $t->string('provider_reference')->nullable()->unique();
            $t->string('tracking_number')->nullable()->unique();
            $t->string('last_error')->nullable();
            $t->timestamp('dispatched_at')->nullable();
            $t->timestamp('last_event_at')->nullable();
            $t->unsignedInteger('progress_rank')->default(0);
            $t->timestamps();
        });
        Schema::create('fulfilment_items', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('fulfilment_id')->constrained('order_fulfilments')->restrictOnDelete();
            $t->foreignId('order_item_id')->constrained('commerce_order_items')->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->unique(['fulfilment_id', 'order_item_id']);
        });
        Schema::create('fulfilment_parcels', function (Blueprint $t) {
            $t->foreignUuid('fulfilment_id')->primary()->constrained('order_fulfilments')->restrictOnDelete();
            $t->unsignedInteger('weight_grams');
            $t->unsignedInteger('length_cm');
            $t->unsignedInteger('width_cm');
            $t->unsignedInteger('height_cm');
            $t->string('instructions', 240)->default('');
        });
        Schema::create('courier_booking_attempts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('fulfilment_id')->constrained('order_fulfilments')->restrictOnDelete();
            $t->foreignId('staff_user_id')->nullable()->constrained('staff_users')->restrictOnDelete();
            $t->string('action');
            $t->string('outcome');
            $t->string('provider_reference')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('sandbox_courier_receipts', function (Blueprint $t) {
            $t->foreignUuid('fulfilment_id')->primary()->constrained('order_fulfilments')->restrictOnDelete();
            $t->string('fingerprint', 64);
            $t->string('reference')->unique();
            $t->string('tracking_number')->unique();
            $t->string('state');
            $t->timestamp('created_at');
            $t->timestamp('updated_at');
        });
        Schema::create('fulfilment_labels', function (Blueprint $t) {
            $t->foreignUuid('fulfilment_id')->primary()->constrained('order_fulfilments')->restrictOnDelete();
            $t->string('path');
            $t->string('sha256', 64);
            $t->timestamp('created_at');
        });
        Schema::create('courier_tracking_events', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignUuid('fulfilment_id')->constrained('order_fulfilments')->restrictOnDelete();
            $t->string('state');
            $t->string('payload_hash', 64);
            $t->boolean('applied');
            $t->timestamp('occurred_at');
            $t->timestamp('created_at');
        });
        Schema::create('order_access_links', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->string('purpose');
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('order_tracking_sessions', function (Blueprint $t) {
            $t->string('token_hash', 64)->primary();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->timestamp('expires_at');
            $t->timestamp('created_at');
        });
        Schema::create('order_notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->foreignUuid('fulfilment_id')->nullable()->constrained('order_fulfilments')->restrictOnDelete();
            $t->string('kind');
            $t->string('dedupe_key')->unique();
            $t->text('encrypted_payload');
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('order_support_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $t->string('subject', 120);
            $t->text('message');
            $t->string('state')->default('open');
            $t->timestamp('created_at');
        });
        if (DB::getDriverName() === 'sqlite') {
            foreach (['order_notes', 'fulfilment_items', 'fulfilment_parcels', 'courier_booking_attempts', 'fulfilment_labels', 'courier_tracking_events'] as $table) {
                foreach (['UPDATE', 'DELETE'] as $op) {
                    DB::unprepared("CREATE TRIGGER {$table}_no_{$op} BEFORE {$op} ON {$table} BEGIN SELECT RAISE(ABORT,'Immutable fulfilment record'); END");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['order_support_requests', 'order_notifications', 'order_tracking_sessions', 'order_access_links', 'courier_tracking_events', 'fulfilment_labels', 'sandbox_courier_receipts', 'courier_booking_attempts', 'fulfilment_parcels', 'fulfilment_items', 'order_fulfilments', 'shipment_quotes', 'manual_order_attempts', 'order_notes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
