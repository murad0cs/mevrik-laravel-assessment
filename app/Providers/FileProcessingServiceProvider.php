<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\FileStorageInterface;
use App\Contracts\StatusRepositoryInterface;
use App\Repositories\FileStorageRepository;
use App\Repositories\FileStatusRepository;
use App\Repositories\FileProcessingStatusRepository;
use App\Services\FileProcessingService;
use App\Services\FileProcessors\FileProcessorFactory;

class FileProcessingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind interfaces to implementations
        $this->app->bind(
            FileStorageInterface::class,
            FileStorageRepository::class
        );

        $this->app->bind(
            StatusRepositoryInterface::class,
            FileProcessingStatusRepository::class // Changed from FileStatusRepository (JSON) to Database
        );

        // Register FileProcessorFactory as singleton for performance
        $this->app->singleton(FileProcessorFactory::class, function ($app) {
            return new FileProcessorFactory();
        });

        // Register FileProcessingService as singleton
        $this->app->singleton(FileProcessingService::class, function ($app) {
            return new FileProcessingService(
                $app->make(FileStorageInterface::class),
                $app->make(StatusRepositoryInterface::class),
                $app->make(FileProcessorFactory::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register custom configuration if needed
        $this->registerConfig();

        // Create required directories
        $this->ensureDirectoriesExist();
    }

    /**
     * Register configuration
     */
    private function registerConfig(): void
    {
        // You can publish config files here if needed
        // $this->publishes([
        //     __DIR__.'/../config/file-processing.php' => config_path('file-processing.php'),
        // ], 'config');
    }

    /**
     * Ensure required directories exist
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [
            storage_path('app/uploads'),
            storage_path('app/processed'),
            storage_path('app/processing_status'),
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }
}