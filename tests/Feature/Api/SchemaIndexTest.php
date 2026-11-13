<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaIndexTest extends TestCase
{
    public function test_hot_path_indexes_and_customer_foreign_key(): void
    {
        $expected = [
            'sales' => 'sales_status_payment_id_index',
            'cash_sessions' => 'cash_sessions_user_id_closed_at_index',
            'batches' => 'batches_variant_expiry_id_index',
            'stock_movements' => 'stock_movements_variant_created_id_index',
            'appointments' => 'appointments_status_scheduled_at_index',
            'vaccinations' => 'vaccinations_next_due_at_index',
            'dewormings' => 'dewormings_next_due_at_index',
            'audit_logs' => 'audit_logs_model_list_index',
        ];

        foreach ($expected as $table => $index) {
            $this->assertNotNull($this->indexName($table, $index), $index);
        }

        $this->assertNotNull($this->indexName('batches', 'batches_expiry_date_index'));

        $orphans = DB::table('sales')
            ->leftJoin('clientes', 'clientes.id', '=', 'sales.customer_id')
            ->whereNotNull('sales.customer_id')
            ->whereNull('clientes.id')
            ->count();

        $foreignKey = $this->foreignKeyName('sales', 'customer_id', 'clientes');

        if ($orphans === 0) {
            $this->assertNotNull($foreignKey);
        } else {
            $this->assertNull($foreignKey);
            $this->assertNotNull($this->indexName('sales', 'sales_customer_id_index'));
        }
    }

    private function indexName(string $table, string $name): ?string
    {
        $row = DB::selectOne(
            'select index_name from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [DB::getDatabaseName(), $table, $name]
        );

        return $row->index_name ?? $row->INDEX_NAME ?? null;
    }

    private function foreignKeyName(string $table, string $column, string $referenced): ?string
    {
        $row = DB::selectOne(
            'select constraint_name from information_schema.key_column_usage where table_schema = ? and table_name = ? and column_name = ? and referenced_table_name = ? limit 1',
            [DB::getDatabaseName(), $table, $column, $referenced]
        );

        return $row->constraint_name ?? $row->CONSTRAINT_NAME ?? null;
    }
};
