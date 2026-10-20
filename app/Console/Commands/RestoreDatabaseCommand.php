<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class RestoreDatabaseCommand extends Command
{
    protected $signature = 'backup:restore {file?} {--latest} {--force}';

    protected $description = 'Restaura un respaldo SQL desde storage/app/backups';

    public function handle(BackupService $backups): int
    {
        if (! $this->option('force')) {
            $this->error('Confirma con --force. Indica --latest o el archivo .sql.');

            return self::FAILURE;
        }
        $file = $this->option('latest') ? $backups->latest() : $this->argument('file');
        if (! $file) {
            $this->error('No hay respaldos.');

            return self::FAILURE;
        }
        $backups->restore($file);
        $this->info('Restauración aplicada: '.basename(is_string($file) ? $file : ''));

        return self::SUCCESS;
    }
}
