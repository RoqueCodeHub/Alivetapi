<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            $table->foreignId('variante_id')
                ->nullable()
                ->after('producto_id')
                ->constrained('variantes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variante_id');
        });
    }
};
