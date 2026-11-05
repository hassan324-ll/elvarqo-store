<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_supplier_products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->nullable()->constrained('catalogue_products')->nullOnDelete();
            $t->string('supplier', 30);
            $t->string('external_id', 40);
            $t->string('url', 300);
            $t->unsignedInteger('cost_minor')->nullable();
            $t->char('currency', 3)->nullable();
            $t->unsignedInteger('shipping_cost_minor')->nullable();
            $t->date('checked_at')->nullable();
            $t->timestamps();
            $t->unique(['supplier', 'external_id'], 'supplier_product_identity_unique');
            $t->unique('product_id');
        });
        Schema::create('catalogue_supplier_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_product_id')->constrained('catalogue_supplier_products')->cascadeOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('catalogue_variants')->nullOnDelete();
            $t->string('external_id', 100)->nullable();
            $t->unsignedInteger('cost_minor')->nullable();
            $t->char('currency', 3)->nullable();
            $t->unsignedInteger('shipping_cost_minor')->nullable();
            $t->date('checked_at')->nullable();
            $t->json('pending_catalogue')->nullable();
            $t->unique('variant_id');
            $t->unique(['supplier_product_id', 'external_id'], 'supplier_variant_identity_unique');
        });
        Schema::create('catalogue_import_batches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('staff_user_id')->constrained('staff_users')->restrictOnDelete();
            $t->string('source_sha256', 64);
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        Schema::create('catalogue_import_items', function (Blueprint $t) {
            $t->id();
            $t->uuid('batch_id');
            $t->foreign('batch_id')->references('id')->on('catalogue_import_batches')->cascadeOnDelete();
            $t->unsignedInteger('position');
            $t->string('title', 160);
            $t->string('external_id', 40)->nullable();
            $t->string('status', 20)->default('ready');
            $t->foreignId('product_id')->nullable()->constrained('catalogue_products')->nullOnDelete();
            $t->json('payload');
            $t->json('preview');
            $t->json('errors');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamps();
            $t->unique(['batch_id', 'position']);
        });
    }

    public function down(): void
    {
        foreach (['catalogue_import_items', 'catalogue_import_batches', 'catalogue_supplier_variants', 'catalogue_supplier_products'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
