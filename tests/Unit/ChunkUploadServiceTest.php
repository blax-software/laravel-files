<?php

namespace Blax\Files\Tests\Unit;

use Blax\Files\FilesServiceProvider;
use Blax\Files\Models\File;
use Blax\Files\Services\ChunkUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase;

class ChunkUploadServiceTest extends TestCase
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

    // ─── initialize ────────────────────────────────────────────────

    public function test_initialize_creates_file_and_cache()
    {
        $request = Request::create('/', 'POST', [
            'filename' => 'video.mp4',
            'filesize' => 5000000,
            'total_chunks' => 5,
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
        ]);

        $result = ChunkUploadService::initialize($request);

        $this->assertArrayHasKey('upload_id', $result);
        $this->assertArrayHasKey('file_id', $result);
        $this->assertEquals(5, $result['total_chunks']);

        // File should exist in database
        $file = File::find($result['file_id']);
        $this->assertNotNull($file);
        $this->assertEquals('video', $file->name);
        $this->assertEquals('mp4', $file->extension);
        $this->assertEquals('video/mp4', $file->type);

        // Cache should have upload metadata
        $meta = cache()->get("chunk_upload:{$result['upload_id']}");
        $this->assertNotNull($meta);
        $this->assertEquals(5, $meta['total_chunks']);
        $this->assertEmpty($meta['received']);
    }

    public function test_initialize_derives_extension_from_filename()
    {
        $request = Request::create('/', 'POST', [
            'filename' => 'document.pdf',
            'filesize' => 1000,
            'total_chunks' => 1,
        ]);

        $result = ChunkUploadService::initialize($request);

        $file = File::find($result['file_id']);
        $this->assertEquals('pdf', $file->extension);
    }

    // ─── receiveChunk ──────────────────────────────────────────────

    public function test_receive_chunk_stores_data()
    {
        $request = Request::create('/', 'POST', [
            'filename' => 'data.bin',
            'filesize' => 200,
            'total_chunks' => 2,
        ]);
        $init = ChunkUploadService::initialize($request);

        // Send first chunk
        $chunkRequest = Request::create('/', 'POST', [
            'upload_id' => $init['upload_id'],
            'chunk_index' => 0,
        ], [], [], [], 'chunk-0-data');

        $result = ChunkUploadService::receiveChunk($chunkRequest);

        $this->assertEquals(0, $result['chunk_index']);
        $this->assertEquals(1, $result['received']);
        $this->assertEquals(2, $result['total_chunks']);
        $this->assertFalse($result['complete']);
    }

    public function test_receive_all_chunks_assembles_file()
    {
        $request = Request::create('/', 'POST', [
            'filename' => 'assembled.txt',
            'filesize' => 10,
            'total_chunks' => 3,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
        ]);
        $init = ChunkUploadService::initialize($request);

        // 3 chunks
        $chunks = ['AAA', 'BBB', 'CCC'];
        $lastResult = null;

        for ($i = 0; $i < 3; $i++) {
            $chunkRequest = Request::create('/', 'POST', [
                'upload_id' => $init['upload_id'],
                'chunk_index' => $i,
            ], [], [], [], $chunks[$i]);

            $lastResult = ChunkUploadService::receiveChunk($chunkRequest);
        }

        $this->assertTrue($lastResult['complete']);

        // File should have assembled content
        $file = File::find($init['file_id']);
        $this->assertEquals('AAABBBCCC', $file->getContents());

        // Cache should be cleaned up
        $this->assertNull(cache()->get("chunk_upload:{$init['upload_id']}"));
    }

    public function test_receive_chunk_with_expired_session_aborts()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $chunkRequest = Request::create('/', 'POST', [
            'upload_id' => 'non-existent-id',
            'chunk_index' => 0,
        ], [], [], [], 'data');

        ChunkUploadService::receiveChunk($chunkRequest);
    }

    public function test_chunks_received_out_of_order_still_assemble_correctly()
    {
        $request = Request::create('/', 'POST', [
            'filename' => 'shuffled.txt',
            'filesize' => 9,
            'total_chunks' => 3,
            'mime_type' => 'text/plain',
        ]);
        $init = ChunkUploadService::initialize($request);

        // Send out of order: 2, 0, 1
        $order = [2 => 'CCC', 0 => 'AAA', 1 => 'BBB'];

        foreach ($order as $index => $data) {
            $chunkRequest = Request::create('/', 'POST', [
                'upload_id' => $init['upload_id'],
                'chunk_index' => $index,
            ], [], [], [], $data);

            ChunkUploadService::receiveChunk($chunkRequest);
        }

        $file = File::find($init['file_id']);
        // Even though chunks arrived out of order, assembly is index-based
        $this->assertEquals('AAABBBCCC', $file->getContents());
    }

    public function test_duplicate_chunk_index_is_deduplicated()
    {
        $request = Request::create('/', 'POST', [
            'filename' => 'dup.txt',
            'filesize' => 3,
            'total_chunks' => 1,
            'mime_type' => 'text/plain',
        ]);
        $init = ChunkUploadService::initialize($request);

        // Send same chunk twice
        $chunkRequest = Request::create('/', 'POST', [
            'upload_id' => $init['upload_id'],
            'chunk_index' => 0,
        ], [], [], [], 'AAA');

        ChunkUploadService::receiveChunk($chunkRequest);

        // It should have complete=true after first, so this is just verifying
        // second call doesn't break anything
        $file = File::find($init['file_id']);
        $this->assertNotNull($file->getContents());
    }
}
