<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['cash_session_id']);
        });
        DB::statement('ALTER TABLE sales MODIFY cash_session_id BIGINT UNSIGNED NULL');
        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('cash_session_id')->references('id')->on('cash_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['cash_session_id']);
        });
        DB::statement('ALTER TABLE sales MODIFY cash_session_id BIGINT UNSIGNED NOT NULL');
        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('cash_session_id')->references('id')->on('cash_sessions');
        });
    }
};
