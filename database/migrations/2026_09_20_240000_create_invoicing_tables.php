<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_series', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 20);
            $table->string('affects', 20);
            $table->string('series', 10)->unique();
            $table->unsignedInteger('next_number')->default(1);
            $table->boolean('active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('sale_return_id')->nullable()->constrained('sale_returns')->nullOnDelete();
            $table->foreignId('related_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 20);
            $table->string('sunat_type', 4);
            $table->string('series', 10);
            $table->unsignedInteger('number');
            $table->string('currency', 3)->default('PEN');
            $table->decimal('tax_percent', 5, 2)->default(18);
            $table->boolean('tax_included')->default(true);
            $table->decimal('gravada', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('customer_doc_type', 10)->nullable();
            $table->string('customer_doc_number', 20)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_address')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('credit_note_reason', 4)->nullable();
            $table->string('credit_note_label')->nullable();
            $table->string('pse_driver', 40)->nullable();
            $table->json('pse_request')->nullable();
            $table->json('pse_response')->nullable();
            $table->string('pse_ticket')->nullable();
            $table->text('pse_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['series', 'number']);
            $table->index(['sale_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_series');
    }
};
