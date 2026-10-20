<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('categorias')->nullOnDelete();
        });

        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('marca_id')->nullable()->after('categoria_id')->constrained('marcas')->nullOnDelete();
            $table->string('especie')->nullable()->after('descripcion');
            $table->string('tipo')->nullable()->after('especie');
            $table->boolean('requires_batch')->default(false)->after('requiere_receta');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['proveedor_id']);
        });
        DB::statement('ALTER TABLE productos MODIFY proveedor_id BIGINT UNSIGNED NULL');
        Schema::table('productos', function (Blueprint $table) {
            $table->foreign('proveedor_id')->references('id')->on('proveedores');
        });

        Schema::create('variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('presentation')->nullable();
            $table->string('unit')->default('unidad');
            $table->string('sale_unit')->default('unidad');
            $table->unsignedInteger('units_per_box')->default(1);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->integer('min_stock')->default(0);
            $table->boolean('needs_review')->default(false);
            $table->text('review_note')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variantes');

        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marca_id');
            $table->dropColumn(['especie', 'tipo', 'requires_batch']);
        });

        Schema::dropIfExists('marcas');

        Schema::table('categorias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
