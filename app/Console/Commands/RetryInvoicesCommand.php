<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

class RetryInvoicesCommand extends Command
{
    protected $signature = 'invoices:retry';

    protected $description = 'Reenvía comprobantes en cola o rechazados al PSE/OSE';

    public function handle(BillingService $billing): int
    {
        $count = $billing->retryPending();
        $this->info("Reintentos: {$count}");

        return self::SUCCESS;
    }
}
