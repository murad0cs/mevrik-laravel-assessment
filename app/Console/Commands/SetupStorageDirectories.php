<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupStorageDirectories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create and set permissions for all required storage directories';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up storage directories...');

        $directories = [
            storage_path('app/uploads'),
            storage_path('app/processed'),
            storage_path('app/processing_status'),
            storage_path('logs/notifications'),
            storage_path('logs/custom'),
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (mkdir($dir, 0775, true)) {
                    $this->info("Created: $dir");
                } else {
                    $this->error("Failed to create: $dir");
                }
            } else {
                $this->info("Already exists: $dir");
            }

            // Try to set permissions
            if (chmod($dir, 0775)) {
                $this->info("Set permissions for: $dir");
            }
        }

        $this->info('Storage directories setup complete!');
        $this->warn('Note: You may need to run "sudo chown -R www-data:www-data storage" for proper ownership.');

        return Command::SUCCESS;
    }
}