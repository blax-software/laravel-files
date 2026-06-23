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
        $this->registerMigrations();
        $this->registerModelBindings();
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->registerCommands();
    }

    /**
     * Expose the serve-time access-control middleware under the `files.access`
     * alias so host apps that wire their own warehouse route can guard it with
     * `->middleware('files.access')`. The package's own route attaches the
     * middleware class directly (see routes/files.php).
     */
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware(
            'files.access',
            Http\Middleware\FileAccessControl::class,
        );
    }

    /**
     * Auto-load the package's migrations so fresh installs work without
     * publishing. Disabled via `files.run_migrations = false` for projects
     * that prefer to publish + manage migrations themselves.
     *
     * Follows the hybrid auto-load + publishable pattern documented in
     * `laravel-workkit/PRINCIPLES/laravel-composer-packages.md`.
     */
    protected function registerMigrations(): void
    {
        if (! config('files.run_migrations', true)) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Set up publishing of config and migrations for `php artisan vendor:publish`.
     *
     * Migrations are published preserving the SOURCE filename — the
     * migrations table records filenames, so keeping them identical means
     * any migration already executed via auto-load is recognised as run
     * for the published copy too. Slap a fresh `date('Y_m_d_His')` on the
     * publish destination and Laravel sees a brand-new migration and runs
     * it again, causing the "table already exists" failures consumers used
     * to hit before this rewrite.
     */
    protected function offerPublishing()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/files.php' => $this->app->configPath('files.php'),
        ], ['files-config', 'config']);

        $migrationsPath = __DIR__ . '/../database/migrations';
        $publishMap = [];
        foreach (glob($migrationsPath . '/*.php') as $sourcePath) {
            $publishMap[$sourcePath] = $this->app->databasePath('migrations/' . basename($sourcePath));
        }

        $this->publishes($publishMap, ['files-migrations', 'migrations']);

        // Convenience tag: publish everything the package owns in one go.
        $this->publishes(
            array_merge(
                [__DIR__ . '/../config/files.php' => $this->app->configPath('files.php')],
                $publishMap,
            ),
            'files',
        );
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
