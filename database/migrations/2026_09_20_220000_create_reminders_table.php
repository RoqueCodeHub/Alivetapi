<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('audience', 20)->default('customer');
            $table->string('reference_type', 40);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->date('due_at')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('whatsapp_url', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('channel', 20)->default('email');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['type', 'reference_type', 'reference_id', 'due_at'], 'reminders_unique_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
