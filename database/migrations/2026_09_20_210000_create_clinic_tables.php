<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->string('staff_role', 30)->default('veterinario');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('pet_id')->constrained('pets');
            $table->foreignId('clinic_service_id')->constrained('clinic_services');
            $table->foreignId('staff_user_id')->constrained('users');
            $table->timestamp('scheduled_at');
            $table->string('status', 20)->default('pendiente');
            $table->text('notes')->nullable();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets');
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type', 40)->default('consulta');
            $table->text('diagnostico')->nullable();
            $table->text('tratamiento')->nullable();
            $table->decimal('peso', 8, 2)->nullable();
            $table->text('examenes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('medical_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->string('path');
            $table->string('kind', 20)->default('adjunto');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });

        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets');
            $table->foreignId('medical_record_id')->nullable()->constrained('medical_records')->nullOnDelete();
            $table->string('vaccine_name');
            $table->date('applied_at');
            $table->date('next_due_at')->nullable();
            $table->foreignId('variant_id')->nullable()->constrained('variantes')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dewormings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets');
            $table->foreignId('medical_record_id')->nullable()->constrained('medical_records')->nullOnDelete();
            $table->string('product_name');
            $table->date('applied_at');
            $table->date('next_due_at')->nullable();
            $table->foreignId('variant_id')->nullable()->constrained('variantes')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('clinical_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('medical_record_id')->nullable()->constrained('medical_records')->nullOnDelete();
            $table->foreignId('variant_id')->constrained('variantes');
            $table->foreignId('batch_id')->constrained('batches');
            $table->unsignedInteger('quantity');
            $table->string('description');
            $table->boolean('charged')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_consumptions');
        Schema::dropIfExists('dewormings');
        Schema::dropIfExists('vaccinations');
        Schema::dropIfExists('medical_attachments');
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('clinic_services');
    }
};
