<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clientes')) {
            return;
        }

        if (Schema::hasColumn('clientes', 'nombre')) {
            $rows = DB::table('clientes')
                ->whereNotNull('nombre')
                ->where('nombre', '!=', '')
                ->get(['id', 'persona_id', 'nombre']);

            foreach ($rows as $row) {
                $persona = DB::table('personas')->where('id', $row->persona_id)->first();
                if (! $persona) {
                    continue;
                }
                $parts = preg_split('/\s+/', trim((string) $row->nombre)) ?: [];
                $nombres = $parts[0] ?? $persona->nombres;
                $apellidos = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $persona->apellidos;
                DB::table('personas')->where('id', $persona->id)->update([
                    'nombres' => $nombres ?: $persona->nombres,
                    'apellidos' => $apellidos ?: $persona->apellidos,
                    'updated_at' => now(),
                ]);
            }

            Schema::table('clientes', function ($table) {
                $table->dropColumn('nombre');
            });
        }

        DB::statement('ALTER TABLE clientes MODIFY whatsapp VARCHAR(50) NULL AFTER persona_id');
        DB::statement('ALTER TABLE clientes MODIFY observaciones TEXT NULL AFTER whatsapp');
        DB::statement('ALTER TABLE clientes MODIFY activo TINYINT(1) NOT NULL DEFAULT 1 AFTER observaciones');
    }

    public function down(): void
    {
        if (! Schema::hasTable('clientes') || Schema::hasColumn('clientes', 'nombre')) {
            return;
        }

        Schema::table('clientes', function ($table) {
            $table->string('nombre')->nullable()->after('persona_id');
        });
    }
};
