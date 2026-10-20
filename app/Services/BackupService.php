<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class BackupService
{
    public function directory(): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    public function create(): string
    {
        $name = 'backup-'.now()->format('Ymd-His').'.sql';
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        File::put($path, $this->dumpSql());
        $this->prune((int) env('BACKUP_KEEP', 7));

        return $path;
    }

    public function restore(string $path): void
    {
        $full = $this->resolvePath($path);
        $sql = File::get($full);
        if (trim($sql) === '') {
            throw new InvalidArgumentException('El respaldo está vacío.');
        }
        DB::unprepared($sql);
    }

    public function latest(): ?string
    {
        $files = $this->listFiles();

        return $files[0] ?? null;
    }

    /**
     * @return list<array{name: string, size: int, modified_at: string}>
     */
    public function list(): array
    {
        return array_map(function (string $path) {
            return [
                'name' => basename($path),
                'size' => File::size($path),
                'modified_at' => date('Y-m-d H:i:s', File::lastModified($path)),
            ];
        }, $this->listFiles());
    }

    public function resolvePath(string $name): string
    {
        $base = realpath($this->directory()) ?: $this->directory();
        $path = str_contains($name, DIRECTORY_SEPARATOR) || str_contains($name, '/')
            ? $name
            : $this->directory().DIRECTORY_SEPARATOR.basename($name);
        $real = realpath($path);
        if (! $real || ! str_starts_with($real, $base) || ! str_ends_with(strtolower($real), '.sql')) {
            throw new InvalidArgumentException('Archivo de respaldo no válido.');
        }

        return $real;
    }

    private function dumpSql(): string
    {
        $sql = "-- veterinaria backup\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n";
        $database = DB::getDatabaseName();
        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_'.$database;
        foreach ($tables as $row) {
            $table = $row->$key ?? array_values((array) $row)[0];
            $create = DB::select('SHOW CREATE TABLE `'.$table.'`');
            $createSql = $create[0]->{'Create Table'} ?? null;
            if (! $createSql) {
                continue;
            }
            $sql .= "\nDROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n";
            $rows = DB::table($table)->get();
            foreach ($rows as $item) {
                $values = [];
                foreach ((array) $item as $value) {
                    $values[] = $this->quote($value);
                }
                $sql .= 'INSERT INTO `'.$table.'` VALUES ('.implode(',', $values).");\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    private function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return DB::getPdo()->quote((string) $value);
    }

    private function prune(int $keep): void
    {
        $files = $this->listFiles();
        foreach (array_slice($files, $keep) as $old) {
            File::delete($old);
        }
    }

    /**
     * @return list<string>
     */
    private function listFiles(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'*.sql') ?: [];
        usort($files, fn ($a, $b) => File::lastModified($b) <=> File::lastModified($a));

        return array_values($files);
    }
}
