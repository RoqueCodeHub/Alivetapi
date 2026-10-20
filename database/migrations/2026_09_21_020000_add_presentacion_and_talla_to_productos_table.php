<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $backup = 'productos_backup_'.now()->format('Ymd');
        if (! Schema::hasTable($backup) && Schema::hasTable('productos')) {
            DB::statement("CREATE TABLE `{$backup}` AS SELECT * FROM productos");
        }

        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'presentacion')) {
                $table->string('presentacion', 150)->nullable()->after('proveedor_id');
            }
            if (! Schema::hasColumn('productos', 'talla')) {
                $table->string('talla', 50)->nullable()->after('presentacion');
            }
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
        });
        DB::statement('ALTER TABLE productos MODIFY categoria_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE productos MODIFY codigo VARCHAR(100) NULL');
        DB::statement('ALTER TABLE productos MODIFY stock_actual DECIMAL(10,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE productos MODIFY stock_minimo DECIMAL(10,2) NOT NULL DEFAULT 0');
        Schema::table('productos', function (Blueprint $table) {
            $table->foreign('categoria_id')->references('id')->on('categorias')->nullOnDelete();
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->index('nombre', 'idx_productos_nombre');
            $table->index('categoria_id', 'idx_productos_categoria');
            $table->index('proveedor_id', 'idx_productos_proveedor');
            $table->index('especie', 'idx_productos_especie');
            $table->index('tipo', 'idx_productos_tipo');
            $table->index('fecha_vencimiento', 'idx_productos_vencimiento');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('idx_productos_nombre');
            $table->dropIndex('idx_productos_categoria');
            $table->dropIndex('idx_productos_proveedor');
            $table->dropIndex('idx_productos_especie');
            $table->dropIndex('idx_productos_tipo');
            $table->dropIndex('idx_productos_vencimiento');
            $table->dropColumn(['presentacion', 'talla']);
        });
    }
};
