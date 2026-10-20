<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clientes') && Schema::hasColumn('clientes', 'whatsapp')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('whatsapp');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clientes') && ! Schema::hasColumn('clientes', 'whatsapp')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->string('whatsapp', 50)->nullable()->after('persona_id');
            });
        }
    }
};
