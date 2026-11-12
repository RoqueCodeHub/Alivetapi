<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'metodo_pago')) {
                $table->string('metodo_pago', 30)->nullable()->after('estado');
            }
            if (! Schema::hasColumn('ventas', 'monto_pagado')) {
                $table->decimal('monto_pagado', 10, 2)->nullable()->after('metodo_pago');
            }
        });

        $clave = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS nombre
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            ['ventas', 'trabajador_id']
        );
        if ($clave) {
            DB::statement('ALTER TABLE ventas DROP FOREIGN KEY `'.$clave->nombre.'`');
        }
        DB::statement('ALTER TABLE ventas MODIFY trabajador_id BIGINT UNSIGNED NULL');
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreign('trabajador_id')->references('id')->on('trabajadores');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $presentes = array_values(array_filter(
                ['metodo_pago', 'monto_pagado'],
                fn ($columna) => Schema::hasColumn('ventas', $columna)
            ));
            if ($presentes !== []) {
                $table->dropColumn($presentes);
            }
        });
    }
};
