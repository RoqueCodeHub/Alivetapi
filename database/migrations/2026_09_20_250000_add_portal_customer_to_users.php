<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('is_active')->constrained('clientes')->nullOnDelete();
            $table->unique('cliente_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['staff_user_id']);
        });
        DB::statement('ALTER TABLE appointments MODIFY staff_user_id BIGINT UNSIGNED NULL');
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('staff_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
        });
    }
};
