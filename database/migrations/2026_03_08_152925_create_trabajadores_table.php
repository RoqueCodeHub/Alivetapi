<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trabajadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cargo');
            $table->string('especialidad')->nullable();
            $table->decimal('salario', 10, 2)->nullable();
            $table->date('fecha_contratacion');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajadores');
    }
};
