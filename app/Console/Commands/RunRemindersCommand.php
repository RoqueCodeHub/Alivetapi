<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class RunRemindersCommand extends Command
{
    protected $signature = 'reminders:run {--days=7}';

    protected $description = 'Genera recordatorios (vacunas, citas, stock, caja) y envía correos pendientes';

    public function handle(ReminderService $reminders): int
    {
        $created = $reminders->generate((int) $this->option('days'));
        $sent = $reminders->sendPending();
        $this->info("Recordatorios nuevos: {$created}. Correos enviados: {$sent}.");

        return self::SUCCESS;
    }
}
