<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->after('sale_id')->constrained('cash_sessions')->nullOnDelete();
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->after('user_id')->constrained('cash_sessions')->nullOnDelete();
        });

        $payments = DB::table('sale_payments')->whereNull('cash_session_id')->get(['id', 'sale_id']);
        foreach ($payments as $payment) {
            $sessionId = DB::table('sales')->where('id', $payment->sale_id)->value('cash_session_id');
            if ($sessionId) {
                DB::table('sale_payments')->where('id', $payment->id)->update(['cash_session_id' => $sessionId]);
            }
        }

        $returns = DB::table('sale_returns')->whereNull('cash_session_id')->get(['id', 'sale_id']);
        foreach ($returns as $return) {
            $sessionId = DB::table('sales')->where('id', $return->sale_id)->value('cash_session_id');
            if ($sessionId) {
                DB::table('sale_returns')->where('id', $return->id)->update(['cash_session_id' => $sessionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
        });
    }
};
