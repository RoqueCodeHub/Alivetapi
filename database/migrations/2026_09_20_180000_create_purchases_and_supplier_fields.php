<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('nombre')->nullable()->after('empresa');
            $table->string('ruc', 11)->nullable()->unique()->after('nombre');
            $table->string('telefono', 50)->nullable()->after('ruc');
            $table->string('email')->nullable()->after('telefono');
            $table->boolean('activo')->default(true)->after('email');
        });

        DB::table('proveedores')->orderBy('id')->get()->each(function ($row) {
            DB::table('proveedores')->where('id', $row->id)->update([
                'nombre' => $row->empresa,
            ]);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('proveedores');
            $table->string('comprobante')->nullable();
            $table->date('fecha');
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->string('payment_status', 20)->default('pendiente');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('variantes');
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->string('batch_code');
            $table->date('expiry_date')->nullable();
            $table->integer('quantity');
            $table->decimal('cost', 10, 2);
            $table->timestamps();
        });

        Schema::create('cost_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('variantes')->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->foreignId('purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
            $table->decimal('cost', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30)->default('transferencia');
            $table->string('reference')->nullable();
            $table->date('paid_at');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('cost_histories');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'ruc', 'telefono', 'email', 'activo']);
        });
    }
};
