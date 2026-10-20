<?php

namespace App\Console\Commands;

use App\Services\DailySalesSummaryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RebuildDailySalesSummaryCommand extends Command
{
    protected $signature = 'reports:rebuild-daily-summary {--days=90}';

    protected $description = 'Recalcula daily_sales_summaries para aliviar reportes en hosting';

    public function handle(DailySalesSummaryService $summaries): int
    {
        $days = max(1, (int) $this->option('days'));
        $to = Carbon::now()->endOfDay();
        $from = Carbon::now()->subDays($days - 1)->startOfDay();
        $summaries->rebuildRange($from, $to);
        $this->info("Resumen diario reconstruido ({$days} días).");

        return self::SUCCESS;
    }
}
