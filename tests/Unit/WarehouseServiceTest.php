<?php

namespace Blax\Files\Tests\Unit;

use Blax\Files\FilesServiceProvider;
use Blax\Files\Models\File;
use Blax\Files\Services\WarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase;

class WarehouseServiceTest extends TestCase
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
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../workbench/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ─── searchFile — UUID lookup ──────────────────────────────────

    public function test_search_finds_file_by_uuid()
    {
        $file = File::create(['name' => 'found']);
        $file->putContents('content');

        $request = new \Illuminate\Http\Request;
        $result = WarehouseService::searchFile($request, $file->id);

        $this->assertNotNull($result);
        $this->assertEquals($file->id, $result->id);
    }

    // ─── searchFile — encrypted ID ─────────────────────────────────

    public function test_search_finds_file_by_encrypted_id()
    {
        $file = File::create(['name' => 'encrypted']);
        $file->putContents('content');

        $encrypted = encrypt($file->id);

        $request = new \Illuminate\Http\Request;
        $result = WarehouseService::searchFile($request, $encrypted);

        $this->assertNotNull($result);
        $this->assertEquals($file->id, $result->id);
    }

    // ─── searchFile — null / empty ─────────────────────────────────

    public function test_search_returns_null_for_null_identifier()
    {
        $request = new \Illuminate\Http\Request;
        $result = WarehouseService::searchFile($request, null);

        $this->assertNull($result);
    }

    public function test_search_returns_null_for_nonexistent_id()
    {
        $request = new \Illuminate\Http\Request;
        $result = WarehouseService::searchFile($request, 'nonexistent-uuid-here');

        $this->assertNull($result);
    }

    // ─── searchFile — query string stripping ──────────────────────

    public function test_search_strips_query_string_from_identifier()
    {
        $file = File::create(['name' => 'qs']);
        $file->putContents('content');

        $request = new \Illuminate\Http\Request;
        $result = WarehouseService::searchFile($request, $file->id . '?size=100x100');

        $this->assertNotNull($result);
        $this->assertEquals($file->id, $result->id);
    }

    // ─── searchFile — asset path ──────────────────────────────────

    public function test_search_finds_asset_by_exact_path()
    {
        Storage::disk('local')->put('images/logo.png', 'png-data');

        $request = new \Illuminate\Http\Request;
        $result = WarehouseService::searchFile($request, 'images/logo.png');

        $this->assertNotNull($result);
        $this->assertEquals('logo.png', $result->name);
        $this->assertEquals('png', $result->extension);
    }

    public function test_search_finds_asset_with_auto_extension()
    {
        Storage::disk('local')->put('icons/arrow.svg', '<svg/>');

        $request = new \Illuminate\Http\Request;
        $result = WarehouseService::searchFile($request, 'icons/arrow');

        $this->assertNotNull($result);
        $this->assertEquals('svg', $result->extension);
    }

    public function test_search_prefers_svg_over_png_when_both_exist()
    {
        Storage::disk('local')->put('icons/logo.svg', '<svg/>');
        Storage::disk('local')->put('icons/logo.png', 'png-data');

        $request = new \Illuminate\Http\Request;
        $result = WarehouseService::searchFile($request, 'icons/logo');

        $this->assertNotNull($result);
        // svg comes first in preferred_extensions
        $this->assertEquals('svg', $result->extension);
    }

    // ─── searchFile — storage path ─────────────────────────────────

    public function test_search_finds_by_storage_path()
    {
        Storage::disk('local')->put('audio/clip.mp3', 'audio-data');

        $request = new \Illuminate\Http\Request;
        $result = WarehouseService::searchFile($request, 'storage/audio/clip.mp3');

        $this->assertNotNull($result);
        $this->assertEquals('clip.mp3', $result->name);
    }

    // ─── url() ─────────────────────────────────────────────────────

    public function test_url_generates_warehouse_path()
    {
        $file = File::create(['name' => 'test']);

        $url = WarehouseService::url($file);

        $this->assertStringContainsString('warehouse/' . $file->id, $url);
    }

    public function test_url_accepts_string_id()
    {
        $url = WarehouseService::url('some-uuid');

        $this->assertStringContainsString('warehouse/some-uuid', $url);
    }
}
