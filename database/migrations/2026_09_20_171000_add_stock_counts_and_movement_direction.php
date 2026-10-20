<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('direction', 10)->default('in')->after('type');
        });

        DB::table('stock_movements')
            ->whereIn('type', ['venta', 'merma', 'vencido'])
            ->update(['direction' => 'out']);

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('counted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('variantes')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->integer('system_qty');
            $table->integer('counted_qty')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
