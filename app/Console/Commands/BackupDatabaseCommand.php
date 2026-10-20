<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Genera un respaldo SQL en storage/app/backups';

    public function handle(BackupService $backups): int
    {
        $path = $backups->create();
        $this->info('Respaldo creado: '.basename($path));

        return self::SUCCESS;
    }
}
