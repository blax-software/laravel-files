<?php

namespace Blax\Files\Tests\Unit;

use Blax\Files\Enums\FileLinkType;
use Blax\Files\FilesServiceProvider;
use Blax\Files\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase;

class FileModelTest extends TestCase
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

    // ─── Creation & UUID ───────────────────────────────────────────

    public function test_file_is_created_with_uuid()
    {
        $file = File::create(['name' => 'test']);

        $this->assertNotNull($file->id);
        $this->assertIsString($file->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $file->id,
        );
    }

    public function test_file_sets_default_disk_on_save()
    {
        $file = File::create(['name' => 'test']);

        $this->assertEquals('local', $file->disk);
    }

    public function test_file_generates_relativepath_on_save()
    {
        $file = File::create(['name' => 'test']);

        $this->assertNotNull($file->relativepath);
        $this->assertStringContainsString(now()->format('Y/m/d'), $file->relativepath);
    }

    public function test_file_preserves_explicit_disk()
    {
        $file = File::create(['name' => 'test', 'disk' => 's3']);

        $this->assertEquals('s3', $file->disk);
    }

    public function test_file_preserves_explicit_relativepath()
    {
        $file = File::create([
            'name' => 'test',
            'relativepath' => 'custom/path/file.txt',
        ]);

        $this->assertEquals('custom/path/file.txt', $file->relativepath);
    }

    // ─── putContents ───────────────────────────────────────────────

    public function test_put_contents_stores_file_on_disk()
    {
        $file = File::create(['name' => 'hello']);
        $file->putContents('Hello, World!');

        Storage::disk('local')->assertExists($file->relativepath);
        $this->assertEquals('Hello, World!', $file->getContents());
    }

    public function test_put_contents_detects_extension_when_missing()
    {
        $file = File::create(['name' => 'test']);

        // Plain text content
        $file->putContents('plain text content');

        $this->assertNotNull($file->extension);
    }

    public function test_put_contents_detects_mime_type_when_missing()
    {
        $file = File::create(['name' => 'test']);
        $file->putContents('plain text content');

        $this->assertNotNull($file->type);
    }

    public function test_put_contents_calculates_size()
    {
        $file = File::create(['name' => 'test']);
        $content = str_repeat('x', 1234);
        $file->putContents($content);

        $this->assertEquals(1234, $file->size);
    }

    public function test_put_contents_does_not_overwrite_existing_extension()
    {
        $file = File::create(['name' => 'test', 'extension' => 'pdf']);
        $file->putContents('fake pdf content');

        $this->assertEquals('pdf', $file->extension);
    }

    // ─── getContents / hasContents ─────────────────────────────────

    public function test_get_contents_returns_stored_data()
    {
        $file = File::create(['name' => 'data']);
        $file->putContents('binary data here');

        $this->assertEquals('binary data here', $file->getContents());
    }

    public function test_has_contents_returns_true_when_file_exists()
    {
        $file = File::create(['name' => 'data']);
        $file->putContents('content');

        $this->assertTrue($file->hasContents());
    }

    public function test_has_contents_returns_false_when_file_missing()
    {
        $file = File::create(['name' => 'ghost']);

        $this->assertFalse($file->hasContents());
    }

    // ─── deleteContents ────────────────────────────────────────────

    public function test_delete_contents_removes_file_from_disk()
    {
        $file = File::create(['name' => 'doomed']);
        $file->putContents('to be deleted');

        $this->assertTrue($file->hasContents());

        $file->deleteContents();

        Storage::disk('local')->assertMissing($file->relativepath);
    }

    // ─── Accessors ─────────────────────────────────────────────────

    public function test_size_human_bytes()
    {
        $file = new File(['size' => 500]);
        $this->assertEquals('500 B', $file->size_human);
    }

    public function test_size_human_kilobytes()
    {
        $file = new File(['size' => 2048]);
        $this->assertEquals('2 KB', $file->size_human);
    }

    public function test_size_human_megabytes()
    {
        $file = new File(['size' => 5 * 1048576]);
        $this->assertEquals('5 MB', $file->size_human);
    }

    public function test_size_human_gigabytes()
    {
        $file = new File(['size' => 2 * 1073741824]);
        $this->assertEquals('2 GB', $file->size_human);
    }

    public function test_size_human_zero()
    {
        $file = new File(['size' => null]);
        $this->assertEquals('0 B', $file->size_human);
    }

    public function test_url_attribute_uses_warehouse_prefix()
    {
        $file = File::create(['name' => 'test']);

        $this->assertStringContainsString('warehouse/' . $file->id, $file->url);
    }

    // ─── isImage ───────────────────────────────────────────────────

    public function test_is_image_by_type()
    {
        $file = new File(['type' => 'image/png']);
        $this->assertTrue($file->isImage());
    }

    public function test_is_image_by_extension()
    {
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'] as $ext) {
            $file = new File(['extension' => $ext]);
            $this->assertTrue($file->isImage(), "Extension '{$ext}' should be detected as image.");
        }
    }

    public function test_is_not_image_for_non_image()
    {
        $file = new File(['type' => 'application/pdf', 'extension' => 'pdf']);
        $this->assertFalse($file->isImage());
    }

    // ─── meta (JSON) ──────────────────────────────────────────────

    public function test_meta_is_cast_to_array()
    {
        $file = File::create([
            'name' => 'test',
            'meta' => ['width' => 100, 'height' => 200],
        ]);

        $file->refresh();

        $this->assertIsArray($file->meta);
        $this->assertEquals(100, $file->meta['width']);
        $this->assertEquals(200, $file->meta['height']);
    }

    // ─── Deletion cascades ─────────────────────────────────────────

    public function test_deleting_file_removes_contents_and_filables()
    {
        $user = \Workbench\App\Models\User::create(['name' => 'Jane', 'email' => 'jane@test.com']);
        $file = File::create(['name' => 'bye']);
        $file->putContents('farewell');

        $user->attachFile($file, 'avatar');

        $this->assertDatabaseHas('filables', ['file_id' => $file->id]);

        $file->delete();

        $this->assertDatabaseMissing('filables', ['file_id' => $file->id]);
        Storage::disk('local')->assertMissing($file->relativepath);
    }

    // ─── putContentsFromUpload ────────────────────────────────────

    public function test_put_contents_from_upload_sets_attributes()
    {
        $file = File::create([]);

        $upload = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $file->putContentsFromUpload($upload);

        $this->assertEquals('document', $file->name);
        $this->assertEquals('pdf', $file->extension);
        $this->assertEquals('application/pdf', $file->type);
        $this->assertTrue($file->hasContents());
    }

    // ─── Configurable table name ──────────────────────────────────

    public function test_file_uses_configured_table_name()
    {
        $file = new File;
        $this->assertEquals('files', $file->getTable());
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function test_scope_images_filters_by_type_and_extension()
    {
        File::create(['name' => 'photo', 'type' => 'image/jpeg', 'extension' => 'jpg']);
        File::create(['name' => 'logo', 'type' => null, 'extension' => 'png']);
        File::create(['name' => 'doc', 'type' => 'application/pdf', 'extension' => 'pdf']);

        $images = File::images()->get();
        $this->assertCount(2, $images);
    }

    public function test_scope_by_extension()
    {
        File::create(['name' => 'a', 'extension' => 'pdf']);
        File::create(['name' => 'b', 'extension' => 'png']);
        File::create(['name' => 'c', 'extension' => 'pdf']);

        $pdfs = File::byExtension('pdf')->get();
        $this->assertCount(2, $pdfs);
    }

    public function test_scope_by_extension_multiple()
    {
        File::create(['name' => 'a', 'extension' => 'pdf']);
        File::create(['name' => 'b', 'extension' => 'png']);
        File::create(['name' => 'c', 'extension' => 'doc']);

        $results = File::byExtension('pdf', 'doc')->get();
        $this->assertCount(2, $results);
    }

    public function test_scope_by_disk()
    {
        File::create(['name' => 'local-file', 'disk' => 'local']);
        File::create(['name' => 's3-file', 'disk' => 's3']);

        $local = File::byDisk('local')->get();
        $this->assertCount(1, $local);
        $this->assertEquals('local-file', $local->first()->name);
    }

    public function test_scope_orphaned()
    {
        $attached = File::create(['name' => 'attached']);
        $orphan = File::create(['name' => 'orphan']);

        // Simulate a filable attachment for the first file
        \Blax\Files\Models\Filable::create([
            'file_id' => $attached->id,
            'filable_id' => 1,
            'filable_type' => 'App\Models\User',
            'as' => 'avatar',
            'order' => 0,
        ]);

        $orphans = File::orphaned()->get();
        $this->assertCount(1, $orphans);
        $this->assertEquals('orphan', $orphans->first()->name);
    }

    public function test_scope_recent()
    {
        $recent = File::create(['name' => 'recent']);
        $old = File::create(['name' => 'old']);

        // Backdate the old file
        File::where('id', $old->id)->update(['created_at' => now()->subDays(30)]);

        $recentFiles = File::recent(7)->get();
        $this->assertCount(1, $recentFiles);
        $this->assertEquals('recent', $recentFiles->first()->name);
    }

    // ─── Download ─────────────────────────────────────────────────

    public function test_download_returns_binary_file_response()
    {
        $file = File::create(['name' => 'doc', 'extension' => 'pdf', 'type' => 'application/pdf']);
        $file->putContents('PDF_CONTENT_HERE');

        $response = $file->download();

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
    }

    public function test_download_with_custom_filename()
    {
        $file = File::create(['name' => 'doc', 'extension' => 'pdf', 'type' => 'application/pdf']);
        $file->putContents('PDF_CONTENT_HERE');

        $response = $file->download('custom-name.pdf');

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
    }

    // ─── Duplicate ────────────────────────────────────────────────

    public function test_duplicate_creates_copy()
    {
        $original = File::create(['name' => 'original', 'extension' => 'txt', 'type' => 'text/plain']);
        $original->putContents('Hello World');

        $clone = $original->duplicate();

        $this->assertNotEquals($original->id, $clone->id);
        $this->assertEquals('original (copy)', $clone->name);
        $this->assertEquals('txt', $clone->extension);
        $this->assertEquals('Hello World', $clone->getContents());
    }

    public function test_duplicate_with_custom_name()
    {
        $original = File::create(['name' => 'original', 'extension' => 'txt', 'type' => 'text/plain']);
        $original->putContents('Content');

        $clone = $original->duplicate('renamed');
        $this->assertEquals('renamed', $clone->name);
    }

    // ─── toArray ──────────────────────────────────────────────────

    public function test_to_array_includes_computed_attributes()
    {
        $file = File::create(['name' => 'test', 'extension' => 'pdf', 'size' => 2048]);

        $array = $file->toArray();

        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('size_human', $array);
        $this->assertEquals('2 KB', $array['size_human']);
        $this->assertStringContainsString('warehouse', $array['url']);
    }
}
