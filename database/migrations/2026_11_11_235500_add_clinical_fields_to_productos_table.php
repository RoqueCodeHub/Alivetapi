<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'principio_activo')) {
                $table->string('principio_activo', 150)->nullable();
            }
            if (! Schema::hasColumn('productos', 'concentracion')) {
                $table->string('concentracion', 80)->nullable();
            }
            if (! Schema::hasColumn('productos', 'tipo_receta')) {
                $table->string('tipo_receta', 30)->nullable();
            }
            if (! Schema::hasColumn('productos', 'especies_destino')) {
                $table->json('especies_destino')->nullable();
            }
            if (! Schema::hasColumn('productos', 'cadena_frio')) {
                $table->boolean('cadena_frio')->default(false);
            }
            if (! Schema::hasColumn('productos', 'tiempo_retiro_dias')) {
                $table->unsignedSmallInteger('tiempo_retiro_dias')->nullable();
            }
            if (! Schema::hasColumn('productos', 'permite_fraccionamiento')) {
                $table->boolean('permite_fraccionamiento')->default(false);
            }
            if (! Schema::hasColumn('productos', 'unidad_dispensacion')) {
                $table->string('unidad_dispensacion', 50)->nullable();
            }
            if (! Schema::hasColumn('productos', 'factor_conversion')) {
                $table->decimal('factor_conversion', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('productos', 'precio_venta_dosis')) {
                $table->decimal('precio_venta_dosis', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('productos', 'codigo_lote')) {
                $table->string('codigo_lote', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $columnas = [
                'principio_activo',
                'concentracion',
                'tipo_receta',
                'especies_destino',
                'cadena_frio',
                'tiempo_retiro_dias',
                'permite_fraccionamiento',
                'unidad_dispensacion',
                'factor_conversion',
                'precio_venta_dosis',
                'codigo_lote',
            ];
            $presentes = array_values(array_filter($columnas, fn ($columna) => Schema::hasColumn('productos', $columna)));
            if ($presentes !== []) {
                $table->dropColumn($presentes);
            }
        });
    }
};
