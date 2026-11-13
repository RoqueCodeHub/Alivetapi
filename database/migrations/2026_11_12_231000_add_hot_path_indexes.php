<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureIndex('sales', 'sales_status_payment_id_index', ['status', 'payment_status', 'id']);
        $this->ensureCustomerReference();
        $this->ensureIndex('cash_sessions', 'cash_sessions_user_id_closed_at_index', ['user_id', 'closed_at']);
        $this->ensureIndex('batches', 'batches_variant_expiry_id_index', ['variant_id', 'expiry_date', 'id']);
        $this->ensureIndex('batches', 'batches_expiry_date_index', ['expiry_date']);
        $this->ensureIndex('stock_movements', 'stock_movements_variant_created_id_index', ['variant_id', 'created_at', 'id']);
        $this->ensureIndex('appointments', 'appointments_status_scheduled_at_index', ['status', 'scheduled_at']);
        $this->ensureIndex('vaccinations', 'vaccinations_next_due_at_index', ['next_due_at']);
        $this->ensureIndex('dewormings', 'dewormings_next_due_at_index', ['next_due_at']);
        $this->ensureIndex('audit_logs', 'audit_logs_model_list_index', ['model', 'id']);
    }

    public function down(): void
    {
        $this->dropForeignIfExists('sales', 'sales_customer_id_foreign');
        $this->dropIndexIfExists('sales', 'sales_customer_id_foreign');
        $this->dropIndexIfExists('sales', 'sales_customer_id_index');
        $this->dropIndexIfExists('sales', 'sales_status_payment_id_index');
        $this->dropIndexIfExists('cash_sessions', 'cash_sessions_user_id_closed_at_index');
        $this->dropIndexIfExists('batches', 'batches_variant_expiry_id_index');
        $this->dropIndexIfExists('batches', 'batches_expiry_date_index');
        $this->dropIndexIfExists('stock_movements', 'stock_movements_variant_created_id_index');
        $this->dropIndexIfExists('appointments', 'appointments_status_scheduled_at_index');
        $this->dropIndexIfExists('vaccinations', 'vaccinations_next_due_at_index');
        $this->dropIndexIfExists('dewormings', 'dewormings_next_due_at_index');
        $this->dropIndexIfExists('audit_logs', 'audit_logs_model_list_index');
    }

    private function ensureCustomerReference(): void
    {
        if ($this->foreignKeyExists('sales', 'customer_id', 'clientes')) {
            return;
        }

        $orphans = DB::table('sales')
            ->leftJoin('clientes', 'clientes.id', '=', 'sales.customer_id')
            ->whereNotNull('sales.customer_id')
            ->whereNull('clientes.id')
            ->count();

        if ($orphans > 0) {
            $this->ensureIndex('sales', 'sales_customer_id_index', ['customer_id']);

            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('customer_id', 'sales_customer_id_foreign')
                ->references('id')
                ->on('clientes')
                ->nullOnDelete();
        });
    }

    private function ensureIndex(string $table, string $name, array $columns): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $row = DB::selectOne(
            'select index_name from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [DB::getDatabaseName(), $table, $name]
        );

        return $row !== null;
    }

    private function foreignKeyExists(string $table, string $column, string $referenced): bool
    {
        $row = DB::selectOne(
            'select constraint_name from information_schema.key_column_usage where table_schema = ? and table_name = ? and column_name = ? and referenced_table_name = ? limit 1',
            [DB::getDatabaseName(), $table, $column, $referenced]
        );

        return $row !== null;
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }

    private function dropForeignIfExists(string $table, string $name): void
    {
        $row = DB::selectOne(
            'select constraint_name from information_schema.table_constraints where table_schema = ? and table_name = ? and constraint_name = ? and constraint_type = ? limit 1',
            [DB::getDatabaseName(), $table, $name, 'FOREIGN KEY']
        );

        if ($row === null) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropForeign($name);
        });
    }
};
