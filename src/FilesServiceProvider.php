<?php

namespace Blax\Files;

class FilesServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/files.php',
            'files',
        );
    }

    public function boot()
    {
        $this->offerPublishing();
        $this->registerModelBindings();
        $this->registerRoutes();
        $this->registerCommands();
    }

    protected function offerPublishing()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/files.php' => $this->app->configPath('files.php'),
        ], 'files-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/create_blax_files_table.php.stub' => $this->getMigrationFileName('create_blax_files_table.php'),
            __DIR__ . '/../database/migrations/create_blax_filables_table.php.stub' => $this->getMigrationFileName('create_blax_filables_table.php'),
        ], 'files-migrations');
    }

    protected function getMigrationFileName(string $migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');
        $filesystem = $this->app->make(\Illuminate\Filesystem\Filesystem::class);

        return \Illuminate\Support\Collection::make([$this->app->databasePath() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR])
            ->flatMap(fn($path) => $filesystem->glob($path . '*_' . $migrationFileName))
            ->push($this->app->databasePath() . "/migrations/{$timestamp}_{$migrationFileName}")
            ->first();
    }

    protected function registerModelBindings(): void
    {
        $fileModel = $this->app->config['files.models.file'] ?? Models\File::class;
        $filableModel = $this->app->config['files.models.filable'] ?? Models\Filable::class;

        if ($fileModel !== Models\File::class) {
            $this->app->bind(Models\File::class, $fileModel);
        }
        if ($filableModel !== Models\Filable::class) {
            $this->app->bind(Models\Filable::class, $filableModel);
        }
    }

    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/files.php');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\CleanupOrphanedFilesCommand::class,
            ]);
        }
    }
}
