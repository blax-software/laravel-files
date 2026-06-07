<?php

namespace Blax\Files\Tests;

use Blax\Files\FilesServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Shared base test case for the package suite.
 *
 * The single most important thing this class does is load the package's
 * REAL shipped migrations (database/migrations) rather than a hand-copied
 * duplicate. The workbench used to ship its own create_blax_file_tables
 * migration which drifted from the shipped schema (bigint `id()`/`morphs()`
 * vs the real `uuid('id')`/`uuidMorphs()`), so every UUID-keyed test was
 * silently exercising the wrong schema and erroring with SQLite "datatype
 * mismatch". Loading the shipped migrations makes the suite a faithful
 * mirror of what consumers actually get, and makes that class of drift
 * impossible to reintroduce.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [FilesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Needed by anything exercising encrypt()/decrypt() (e.g. encrypted
        // warehouse IDs). Harmless for the rest.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        // The suite loads the shipped migrations explicitly (see
        // defineDatabaseMigrations); disable the provider's auto-load so the
        // same migration files aren't registered from two paths.
        $app['config']->set('files.run_migrations', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Host-app tables (users, articles) used as morph targets in tests.
        $this->loadMigrationsFrom(__DIR__ . '/../workbench/database/migrations');

        // The package's actual shipped schema — the single source of truth.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }
}
