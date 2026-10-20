<?php

namespace App\Services;

use App\Models\DailySalesSummary;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DailySalesSummaryService
{
    public function rebuildDate(CarbonInterface|string $date): DailySalesSummary
    {
        $day = Carbon::parse($date)->startOfDay();
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        $sales = Sale::query()
            ->with(['items', 'returns'])
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $cost = 0.0;
        $itemsSold = 0;
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $cost += (float) $item->unit_cost * (int) $item->quantity;
                if ($item->item_type === SaleItem::TYPE_PRODUCT) {
                    $itemsSold += (int) $item->quantity;
                }
            }
        }

        $refund = (float) $sales->sum(fn (Sale $sale) => (float) $sale->returns->sum('refund_total'));
        $total = (float) $sales->sum('total');
        $subtotal = (float) $sales->sum('subtotal');
        $discount = (float) $sales->sum('discount');

        return DailySalesSummary::query()->updateOrCreate(
            ['summary_date' => $day->toDateString()],
            [
                'sales_count' => $sales->count(),
                'subtotal' => round($subtotal, 2),
                'discount' => round($discount, 2),
                'total' => round($total, 2),
                'cost' => round($cost, 2),
                'refund_total' => round($refund, 2),
                'profit' => round($total - $cost - $refund, 2),
                'items_sold' => $itemsSold,
            ]
        );
    }

    public function rebuildRange(CarbonInterface $from, CarbonInterface $to): void
    {
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $this->rebuildDate($cursor);
            $cursor->addDay();
        }
    }

    public function forRange(CarbonInterface $from, CarbonInterface $to)
    {
        $exists = DailySalesSummary::query()
            ->whereBetween('summary_date', [$from->toDateString(), $to->toDateString()])
            ->count();
        $days = $from->diffInDays($to) + 1;
        if ($exists < $days) {
            $this->rebuildRange($from, $to);
        }

        return DailySalesSummary::query()
            ->whereBetween('summary_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('summary_date')
            ->get();
    }
}
