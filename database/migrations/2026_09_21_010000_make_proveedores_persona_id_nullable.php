<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropForeign(['persona_id']);
        });

        DB::statement('ALTER TABLE proveedores MODIFY persona_id BIGINT UNSIGNED NULL');

        Schema::table('proveedores', function (Blueprint $table) {
            $table->foreign('persona_id')->references('id')->on('personas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropForeign(['persona_id']);
        });

        DB::statement('ALTER TABLE proveedores MODIFY persona_id BIGINT UNSIGNED NOT NULL');

        Schema::table('proveedores', function (Blueprint $table) {
            $table->foreign('persona_id')->references('id')->on('personas');
        });
    }
};
