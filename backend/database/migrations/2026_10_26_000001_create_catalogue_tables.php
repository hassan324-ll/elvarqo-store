<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_collections', function (Blueprint $t) {
            $t->id();
            $t->string('title', 160);
            $t->string('slug', 180)->unique();
            $t->string('kind', 20)->default('collection');
            $t->text('description')->nullable();
            $t->boolean('active')->default(false);
            $t->integer('position')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('catalogue_products', function (Blueprint $t) {
            $t->id();
            $t->string('title', 160);
            $t->string('slug', 180)->unique();
            $t->string('product_type', 120)->nullable();
            $t->string('status', 20)->default('draft')->index();
            foreach (['description', 'material', 'fit', 'care'] as $f) {
                $t->text($f)->nullable();
            }$t->json('measurements')->nullable();
            $t->string('seo_title', 70)->nullable();
            $t->string('seo_description', 160)->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('published_at')->nullable();
            $t->timestamp('first_published_at')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('catalogue_slugs', function (Blueprint $t) {
            $t->string('slug', 180)->primary();
            $t->foreignId('product_id')->constrained('catalogue_products')->restrictOnDelete();
        });
        Schema::create('catalogue_collection_product', function (Blueprint $t) {
            $t->foreignId('collection_id')->constrained('catalogue_collections')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('catalogue_products')->restrictOnDelete();
            $t->unsignedInteger('position')->default(0);
            $t->primary(['collection_id', 'product_id']);
        });
        Schema::create('catalogue_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('catalogue_products')->restrictOnDelete();
            $t->string('name', 20);
            $t->unique(['product_id', 'name']);
        });
        Schema::create('catalogue_option_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('option_id')->constrained('catalogue_options')->restrictOnDelete();
            $t->string('value', 80);
            $t->unique(['option_id', 'value']);
        });
        Schema::create('catalogue_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('catalogue_products')->restrictOnDelete();
            $t->foreignId('colour_value_id')->constrained('catalogue_option_values')->restrictOnDelete();
            $t->foreignId('size_value_id')->constrained('catalogue_option_values')->restrictOnDelete();
            $t->string('sku', 80)->unique();
            $t->string('barcode', 80)->nullable()->unique();
            $t->unsignedInteger('price_minor');
            $t->unsignedInteger('compare_at_minor')->nullable();
            $t->char('currency', 3)->default('GBP');
            $t->unsignedInteger('weight_grams')->nullable();
            $t->unsignedInteger('low_stock_threshold')->default(5);
            $t->boolean('active')->default(true);
            $t->json('measurements')->nullable();
            $t->text('fit')->nullable();
            $t->text('care')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->unique(['product_id', 'colour_value_id', 'size_value_id'], 'catalogue_variant_combination_unique');
        });
        Schema::create('catalogue_media', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('product_id')->constrained('catalogue_products')->restrictOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('catalogue_variants')->restrictOnDelete();
            $t->string('alt', 240);
            $t->unsignedInteger('position')->default(0);
            $t->unsignedInteger('width');
            $t->unsignedInteger('height');
            $t->json('sizes');
            $t->string('original_format', 10);
            $t->string('sha256', 64);
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        Schema::create('stock_locations', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('code', 40)->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('stock_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('variant_id')->constrained('catalogue_variants')->restrictOnDelete();
            $t->foreignId('location_id')->constrained('stock_locations')->restrictOnDelete();
            $t->unsignedInteger('on_hand')->default(0);
            $t->unsignedInteger('reserved')->default(0);
            $t->unique(['variant_id', 'location_id']);
        });
        Schema::create('stock_operations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('fingerprint', 64);
            $t->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->uuid('operation_id');
            $t->foreign('operation_id')->references('id')->on('stock_operations')->restrictOnDelete();
            $t->foreignId('variant_id')->constrained('catalogue_variants')->restrictOnDelete();
            $t->foreignId('location_id')->constrained('stock_locations')->restrictOnDelete();
            $t->integer('delta');
            $t->unsignedInteger('on_hand_after');
            $t->unsignedInteger('reserved_after');
            $t->foreignId('staff_user_id')->constrained('staff_users')->restrictOnDelete();
            $t->string('reason', 240);
            $t->string('reference', 120)->nullable();
            $t->text('note')->nullable();
            $t->timestamp('created_at');
        });
        foreach (['update', 'delete'] as $op) {
            if (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER stock_movements_no_{$op} BEFORE {$op} ON stock_movements BEGIN SELECT RAISE(ABORT, 'Stock movements are immutable'); END");
            } elseif (DB::getDriverName() === 'mysql') {
                DB::unprepared("CREATE TRIGGER stock_movements_no_{$op} BEFORE {$op} ON stock_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock movements are immutable'");
            }
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE FUNCTION deny_stock_mutation() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Stock movements are immutable'; END; $$");
            DB::unprepared('CREATE TRIGGER stock_movements_immutable BEFORE UPDATE OR DELETE ON stock_movements FOR EACH ROW EXECUTE FUNCTION deny_stock_mutation()');
        }
    }

    public function down(): void
    {
        foreach (['stock_movements', 'stock_operations', 'stock_balances', 'stock_locations', 'catalogue_media', 'catalogue_variants', 'catalogue_option_values', 'catalogue_options', 'catalogue_collection_product', 'catalogue_slugs', 'catalogue_products', 'catalogue_collections'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS deny_stock_mutation()');
        }
    }
};
