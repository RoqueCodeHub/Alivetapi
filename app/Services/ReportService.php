<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use App\Models\Variante;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        private readonly DailySalesSummaryService $summaries,
        private readonly StockService $stock,
        private readonly CashSessionService $cash,
    ) {
    }

    /**
     * @return array{from: Carbon, to: Carbon, category_id: ?int, cashier_id: ?int}
     */
    public function filters(array $input, User $user): array
    {
        $to = isset($input['to']) ? Carbon::parse($input['to'])->endOfDay() : now()->endOfDay();
        $from = isset($input['from']) ? Carbon::parse($input['from'])->startOfDay() : now()->copy()->subDays(29)->startOfDay();
        $cashierId = isset($input['cashier_id']) ? (int) $input['cashier_id'] : null;
        $categoryId = isset($input['category_id']) ? (int) $input['category_id'] : null;

        if (! $this->seesAll($user)) {
            $cashierId = $user->id;
        }

        return [
            'from' => $from,
            'to' => $to,
            'category_id' => $categoryId ?: null,
            'cashier_id' => $cashierId,
        ];
    }

    public function seesAll(User $user): bool
    {
        return $user->can('reports.view_all') || $user->hasRole('Administrador');
    }

    public function dashboard(User $user, array $input = []): array
    {
        $todayFrom = now()->startOfDay();
        $todayTo = now()->endOfDay();
        $weekFrom = now()->startOfWeek();
        $monthFrom = now()->startOfMonth();

        if (! $this->seesAll($user)) {
            $today = $this->periodTotals($todayFrom, $todayTo, null, $user->id);

            return [
                'role' => 'operator',
                'today' => $today,
                'cash' => $this->cash->current($user),
            ];
        }

        $filters = $this->filters($input, $user);
        $today = $this->periodTotals($todayFrom, $todayTo, null, null);
        $week = $this->periodTotals($weekFrom, $todayTo, null, null);
        $month = $this->periodTotals($monthFrom, $todayTo, null, null);

        $prevToday = $this->periodTotals($todayFrom->copy()->subDay(), $todayTo->copy()->subDay(), null, null);
        $prevWeek = $this->periodTotals($weekFrom->copy()->subWeek(), $todayTo->copy()->subWeek(), null, null);
        $prevMonth = $this->periodTotals($monthFrom->copy()->subMonth(), $todayTo->copy()->subMonth(), null, null);

        return [
            'role' => 'admin',
            'today' => $today,
            'week' => $week,
            'month' => $month,
            'comparison' => [
                'today' => $this->compare($today['total'], $prevToday['total']),
                'week' => $this->compare($week['total'], $prevWeek['total']),
                'month' => $this->compare($month['total'], $prevMonth['total']),
            ],
            'sales_by_day' => $this->salesByDay($filters),
            'products_by_day' => $this->productsByDay($filters),
            'top_products' => $this->topProducts($filters),
            'by_category' => $this->byCategory($filters),
            'by_cashier' => $this->byCashier($filters),
            'by_payment' => $this->byPayment($filters),
            'by_hour' => $this->byHour($filters),
            'profit' => $this->profit($filters),
            'slow_movers' => $this->slowMovers($filters),
            'inventory_value' => $this->inventoryValue(),
            'expiring' => $this->stock->expiryAlerts(),
        ];
    }

    public function salesByDay(array $filters): array
    {
        if (! $filters['category_id'] && ! $filters['cashier_id']) {
            return $this->summaries->forRange($filters['from'], $filters['to'])->map(fn ($row) => [
                'date' => $row->summary_date->toDateString(),
                'sales_count' => (int) $row->sales_count,
                'total' => (float) $row->total,
                'profit' => (float) $row->profit,
                'items_sold' => (int) $row->items_sold,
            ])->values()->all();
        }

        $rows = $this->salesQuery($filters)
            ->selectRaw('DATE(sales.created_at) as d, COUNT(*) as sales_count, SUM(sales.total) as total')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => (string) $row->d,
            'sales_count' => (int) $row->sales_count,
            'total' => (float) $row->total,
            'profit' => null,
            'items_sold' => null,
        ])->all();
    }

    public function productsByDay(array $filters): array
    {
        $rows = $this->itemQuery($filters)
            ->selectRaw('DATE(sales.created_at) as d, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as total')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => (string) $row->d,
            'quantity' => (int) $row->qty,
            'total' => (float) $row->total,
        ])->all();
    }

    public function topProducts(array $filters, int $limit = 10): array
    {
        $rows = $this->itemQuery($filters)
            ->where('sale_items.item_type', SaleItem::TYPE_PRODUCT)
            ->selectRaw('sale_items.variant_id, sale_items.description, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as total')
            ->groupBy('sale_items.variant_id', 'sale_items.description')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => [
            'variant_id' => $row->variant_id,
            'description' => $row->description,
            'quantity' => (int) $row->qty,
            'total' => (float) $row->total,
        ])->all();
    }

    public function byCategory(array $filters): array
    {
        $rows = $this->itemQuery($filters)
            ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->selectRaw("COALESCE(categorias.nombre, 'Servicios / sin categoría') as categoria, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as total")
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($row) => [
            'categoria' => $row->categoria,
            'quantity' => (int) $row->qty,
            'total' => (float) $row->total,
        ])->all();
    }

    public function byCashier(array $filters): array
    {
        $rows = $this->salesQuery($filters)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw('sales.user_id, users.name as cajero, COUNT(*) as sales_count, SUM(sales.total) as total')
            ->groupBy('sales.user_id', 'users.name')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($row) => [
            'user_id' => (int) $row->user_id,
            'cajero' => $row->cajero,
            'sales_count' => (int) $row->sales_count,
            'total' => (float) $row->total,
        ])->all();
    }

    public function byPayment(array $filters): array
    {
        $saleIds = $this->salesQuery($filters)->pluck('sales.id');
        $rows = SalePayment::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($row) => [
            'method' => $row->method,
            'total' => (float) $row->total,
        ])->all();
    }

    public function byHour(array $filters): array
    {
        $rows = $this->salesQuery($filters)
            ->selectRaw('HOUR(sales.created_at) as h, COUNT(*) as sales_count, SUM(sales.total) as total')
            ->groupBy('h')
            ->orderBy('h')
            ->get();

        return $rows->map(fn ($row) => [
            'hour' => (int) $row->h,
            'sales_count' => (int) $row->sales_count,
            'total' => (float) $row->total,
        ])->all();
    }

    public function profit(array $filters): array
    {
        $sales = $this->salesQuery($filters)->with(['items', 'returns'])->get();
        $total = (float) $sales->sum('total');
        $cost = 0.0;
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $cost += (float) $item->unit_cost * (int) $item->quantity;
            }
        }
        $refund = (float) $sales->sum(fn (Sale $sale) => (float) $sale->returns->sum('refund_total'));

        return [
            'sales_total' => round($total, 2),
            'cost' => round($cost, 2),
            'refund_total' => round($refund, 2),
            'profit' => round($total - $cost - $refund, 2),
            'sales_count' => $sales->count(),
        ];
    }

    public function slowMovers(array $filters, int $limit = 20): array
    {
        $soldIds = $this->itemQuery($filters)
            ->where('sale_items.item_type', SaleItem::TYPE_PRODUCT)
            ->whereNotNull('sale_items.variant_id')
            ->select('sale_items.variant_id')
            ->groupBy('sale_items.variant_id')
            ->pluck('variant_id');

        $variants = Variante::query()
            ->with(['producto', 'batches'])
            ->where('activo', true)
            ->whereNotIn('id', $soldIds)
            ->get()
            ->filter(fn (Variante $v) => (int) $v->batches->sum('quantity') > 0)
            ->take($limit);

        return $variants->map(fn (Variante $v) => [
            'variant_id' => $v->id,
            'sku' => $v->sku,
            'producto' => $v->producto?->nombre,
            'presentation' => $v->presentation,
            'on_hand' => (int) $v->batches->sum('quantity'),
        ])->values()->all();
    }

    public function inventoryValue(): array
    {
        $rows = Batch::query()
            ->where('quantity', '>', 0)
            ->get(['quantity', 'cost']);

        $qty = (int) $rows->sum('quantity');
        $value = round((float) $rows->sum(fn (Batch $b) => (int) $b->quantity * (float) $b->cost), 2);

        return [
            'units' => $qty,
            'value' => $value,
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<mixed>>, title: string}
     */
    public function table(string $report, array $filters): array
    {
        return match ($report) {
            'top-products' => [
                'title' => 'Top productos',
                'headers' => ['variante', 'descripcion', 'cantidad', 'total'],
                'rows' => array_map(fn ($r) => [$r['variant_id'], $r['description'], $r['quantity'], $r['total']], $this->topProducts($filters, 50)),
            ],
            'by-category' => [
                'title' => 'Ventas por categoría',
                'headers' => ['categoria', 'cantidad', 'total'],
                'rows' => array_map(fn ($r) => [$r['categoria'], $r['quantity'], $r['total']], $this->byCategory($filters)),
            ],
            'by-cashier' => [
                'title' => 'Ventas por cajero',
                'headers' => ['cajero', 'ventas', 'total'],
                'rows' => array_map(fn ($r) => [$r['cajero'], $r['sales_count'], $r['total']], $this->byCashier($filters)),
            ],
            'profit' => [
                'title' => 'Utilidad',
                'headers' => ['ventas', 'costo', 'devoluciones', 'utilidad', 'nro_ventas'],
                'rows' => [array_values($this->profit($filters))],
            ],
            'slow-movers' => [
                'title' => 'Baja rotación',
                'headers' => ['sku', 'producto', 'presentacion', 'stock'],
                'rows' => array_map(fn ($r) => [$r['sku'], $r['producto'], $r['presentation'], $r['on_hand']], $this->slowMovers($filters)),
            ],
            'inventory-value' => [
                'title' => 'Valorización inventario',
                'headers' => ['unidades', 'valor'],
                'rows' => [array_values($this->inventoryValue())],
            ],
            default => [
                'title' => 'Ventas por día',
                'headers' => ['fecha', 'ventas', 'total', 'utilidad', 'unidades'],
                'rows' => array_map(fn ($r) => [$r['date'], $r['sales_count'], $r['total'], $r['profit'], $r['items_sold']], $this->salesByDay($filters)),
            ],
        };
    }

    /**
     * @return array{count: int, total: float}
     */
    private function periodTotals(Carbon $from, Carbon $to, ?int $categoryId, ?int $cashierId): array
    {
        $q = $this->salesQuery([
            'from' => $from,
            'to' => $to,
            'category_id' => $categoryId,
            'cashier_id' => $cashierId,
        ]);

        return [
            'count' => (int) (clone $q)->count(),
            'total' => (float) (clone $q)->sum('sales.total'),
        ];
    }

    private function compare(float $current, float $previous): array
    {
        $diff = round($current - $previous, 2);
        $pct = $previous == 0.0 ? ($current > 0 ? 100.0 : 0.0) : round(($diff / $previous) * 100, 1);

        return [
            'previous' => $previous,
            'diff' => $diff,
            'percent' => $pct,
        ];
    }

    private function salesQuery(array $filters): Builder
    {
        $query = Sale::query()
            ->from('sales')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.created_at', [$filters['from'], $filters['to']]);

        if ($filters['cashier_id']) {
            $query->where('sales.user_id', $filters['cashier_id']);
        }

        if ($filters['category_id']) {
            $query->whereExists(function ($inner) use ($filters) {
                $inner->select(DB::raw(1))
                    ->from('sale_items')
                    ->join('variantes', 'variantes.id', '=', 'sale_items.variant_id')
                    ->join('productos', 'productos.id', '=', 'variantes.producto_id')
                    ->whereColumn('sale_items.sale_id', 'sales.id')
                    ->where('productos.categoria_id', $filters['category_id']);
            });
        }

        return $query;
    }

    private function itemQuery(array $filters): Builder
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('variantes', 'variantes.id', '=', 'sale_items.variant_id')
            ->leftJoin('productos', 'productos.id', '=', 'variantes.producto_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.created_at', [$filters['from'], $filters['to']])
            ->when($filters['cashier_id'], fn ($q) => $q->where('sales.user_id', $filters['cashier_id']))
            ->when($filters['category_id'], fn ($q) => $q->where('productos.categoria_id', $filters['category_id']));
    }
}
