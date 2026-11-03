<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Introduced together with Phase 8, before any development return data exists.
        // Never guess physical delivery evidence for legacy claims.
        if (DB::table('return_unit_claims')->exists()) {
            throw new RuntimeException('Existing return claims need verified delivery mapping before this migration. No data was changed.');
        }
        Schema::table('return_unit_claims', function (Blueprint $t) {
            $t->foreignId('fulfilment_item_id')->constrained('fulfilment_items')->restrictOnDelete();
            $t->unsignedInteger('delivery_unit');
            $t->unique(['fulfilment_item_id', 'delivery_unit']);
        });
    }

    public function down(): void
    {
        Schema::table('return_unit_claims', function (Blueprint $t) {
            $t->dropForeign(['fulfilment_item_id']);
            $t->dropUnique(['fulfilment_item_id', 'delivery_unit']);
            $t->dropColumn(['fulfilment_item_id', 'delivery_unit']);
        });
    }
};
