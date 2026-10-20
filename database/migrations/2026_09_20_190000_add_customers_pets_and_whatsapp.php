<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE personas MODIFY tipo_documento ENUM('DNI','CE','PASAPORTE','RUC') NOT NULL DEFAULT 'DNI'");

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('nombre')->nullable()->after('persona_id');
            $table->string('whatsapp', 50)->nullable()->after('nombre');
            $table->boolean('activo')->default(true)->after('whatsapp');
        });

        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('especie')->nullable();
            $table->string('raza')->nullable();
            $table->string('sexo', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->decimal('peso', 8, 2)->nullable();
            $table->text('alergias')->nullable();
            $table->string('foto')->nullable();
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('pet_id')->nullable()->after('customer_id')->constrained('pets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pet_id');
        });
        Schema::dropIfExists('pets');
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'whatsapp', 'activo']);
        });
    }
};
