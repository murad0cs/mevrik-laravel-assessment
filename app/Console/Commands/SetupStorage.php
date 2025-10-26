<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupStorage extends Command
{
    protected $signature = 'storage:setup';
    protected $description = 'Setup storage directories with proper permissions for queue processing';

    public function handle()
    {
        $this->info('Setting up storage directories...');

        // Define all required directories
        $directories = [
            storage_path('app/uploads'),
            storage_path('app/processed'),
            storage_path('app/processing_status'),
            storage_path('logs/notifications'),
            storage_path('logs/custom'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0777, true, true);
                $this->info("Created: {$dir}");
            } else {
                // Ensure permissions are correct even if directory exists
                @chmod($dir, 0777);
                $this->info("Updated permissions: {$dir}");
            }
        }

        // Set permissions for the entire storage directory recursively
        $this->setPermissionsRecursively(storage_path());

        $this->info('Storage setup complete!');
        return Command::SUCCESS;
    }

    private function setPermissionsRecursively($path)
    {
        if (is_dir($path)) {
            @chmod($path, 0777);
            $items = scandir($path);
            foreach ($items as $item) {
                if ($item != '.' && $item != '..') {
                    $this->setPermissionsRecursively($path . DIRECTORY_SEPARATOR . $item);
                }
            }
        } else {
            @chmod($path, 0666);
        }
    }
}