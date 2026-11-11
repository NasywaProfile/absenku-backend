<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup {--keep-days= : Jumlah hari retensi file backup sebelum dihapus otomatis}';

    protected $description = 'Membuat backup database terkompresi (.gz) dengan retensi otomatis tanpa mengganggu operasional.';

    public function handle(): int
    {
        $keepDays = (int) ($this->option('keep-days') ?: config('absenku.backup_retention_days', 14));
        $connection = (string) config('database.default');
        $dbConfig = config("database.connections.{$connection}", []);
        $driver = strtolower((string) ($dbConfig['driver'] ?? $connection));

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir, 0750, true);

        $timestamp = now('Asia/Jakarta')->format('Y-m-d_His');

        try {
            if ($driver === 'sqlite') {
                $dbPath = (string) ($dbConfig['database'] ?? '');
                if ($dbPath === '' || $dbPath === ':memory:' || ! is_file($dbPath)) {
                    $this->error('Database SQLite pada disk tidak ditemukan untuk di-backup.');
                    return self::FAILURE;
                }

                $targetFile = "{$backupDir}/absenku_sqlite_{$timestamp}.sqlite.gz";
                $rawContent = file_get_contents($dbPath);
                if ($rawContent === false) {
                    $this->error('Gagal membaca berkas database SQLite.');
                    return self::FAILURE;
                }
                file_put_contents($targetFile, gzencode($rawContent, 9));
                @chmod($targetFile, 0640);
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $host = (string) ($dbConfig['host'] ?? '127.0.0.1');
                $port = (string) ($dbConfig['port'] ?? '3306');
                $database = (string) ($dbConfig['database'] ?? '');
                $username = (string) ($dbConfig['username'] ?? '');
                $password = (string) ($dbConfig['password'] ?? '');

                if ($database === '' || $username === '') {
                    $this->error('Konfigurasi DB_DATABASE atau DB_USERNAME belum lengkap.');
                    return self::FAILURE;
                }

                $sqlFile = "{$backupDir}/absenku_{$database}_{$timestamp}.sql";
                $gzFile = "{$sqlFile}.gz";

                $args = [
                    'mysqldump',
                    "--host={$host}",
                    "--port={$port}",
                    "--user={$username}",
                    '--single-transaction',
                    '--quick',
                    '--routines',
                    '--triggers',
                    $database,
                ];

                $env = [];
                if ($password !== '') {
                    $env['MYSQL_PWD'] = $password;
                }

                $process = new Process($args, base_path(), $env, null, 300);
                $process->run();

                if (! $process->isSuccessful()) {
                    $errOutput = trim($process->getErrorOutput() ?: $process->getOutput());
                    Log::error('[db:backup] mysqldump gagal: ' . $errOutput);
                    $this->error('mysqldump gagal dijalankan: ' . $errOutput);
                    return self::FAILURE;
                }

                $dumpOutput = $process->getOutput();
                if ($dumpOutput === '') {
                    $this->error('Output mysqldump kosong.');
                    return self::FAILURE;
                }

                file_put_contents($gzFile, gzencode($dumpOutput, 9));
                @chmod($gzFile, 0640);
                $targetFile = $gzFile;
            } else {
                $this->error("Driver database '{$driver}' belum didukung oleh perintah db:backup.");
                return self::FAILURE;
            }

            $deletedOld = $this->pruneExpiredBackups($backupDir, max(1, $keepDays));

            Log::info("[db:backup] Backup berhasil disimpan di {$targetFile} (dihapus {$deletedOld} backup kadaluwarsa).");
            $this->info("Backup berhasil dibuat: {$targetFile}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('[db:backup] Exception: ' . $e->getMessage());
            $this->error('Gagal membuat backup database: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function pruneExpiredBackups(string $backupDir, int $keepDays): int
    {
        $cutoffTimestamp = now()->subDays($keepDays)->getTimestamp();
        $deleted = 0;

        foreach (File::files($backupDir) as $file) {
            if ($file->getMTime() < $cutoffTimestamp) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        return $deleted;
    }
}
