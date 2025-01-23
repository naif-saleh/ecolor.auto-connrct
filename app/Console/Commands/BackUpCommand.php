<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BackUpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:back-up-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            Log::info('Backup process started...');

            $date = Carbon::now()->format('Y-m-d_H-i-s');
            $backupPath = storage_path("backups/backup_{$date}.tar.gz");

            // Backup Database
            exec("mysqldump -u aamaldb -p'Ecolor@Aamal@2030' new_db_prod > database.sql");

            // Compress Files
            exec("tar -czf {$backupPath} /path/to/files database.sql");

            // Upload to FTP
            Storage::disk('ftp')->put("backups/backup_{$date}.tar.gz", file_get_contents($backupPath));

            // Remove local temp files
            unlink("database.sql");
            unlink($backupPath);

            Log::info('Backup completed successfully.');
        } catch (\Exception $e) {
            Log::error('Backup failed: ' . $e->getMessage());
        }
    }
}
