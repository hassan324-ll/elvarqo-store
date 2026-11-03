<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogue_collections', function (Blueprint $t) {
            $t->uuid('image_id')->nullable();
            $t->longText('image_webp')->nullable();
            $t->string('image_alt', 240)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalogue_collections', fn (Blueprint $t) => $t->dropColumn(['image_id', 'image_webp', 'image_alt']));
    }
};
