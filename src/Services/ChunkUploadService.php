<?php

namespace Blax\Files\Services;

use Blax\Files\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChunkUploadService
{
    /**
     * Initialize a chunked upload session.
     *
     * Returns a File model and a temporary upload identifier.
     */
    public static function initialize(Request $request): array
    {
        $fileName = $request->input('filename', 'upload');
        $fileSize = $request->input('filesize');
        $mimeType = $request->input('mime_type');
        $extension = $request->input('extension') ?? pathinfo($fileName, PATHINFO_EXTENSION);
        $totalChunks = $request->input('total_chunks', 1);

        $fileModel = config('files.models.file', File::class);
        $file = new $fileModel;
        $file->name = pathinfo($fileName, PATHINFO_FILENAME);
        $file->extension = $extension;
        $file->type = $mimeType;
        $file->size = $fileSize;
        $file->save();

        // Store upload metadata in cache
        $uploadId = $file->id;
        cache()->put("chunk_upload:{$uploadId}", [
            'file_id'      => $file->id,
            'total_chunks' => (int) $totalChunks,
            'received'     => [],
            'temp_dir'     => "chunk_uploads/{$uploadId}",
        ], now()->addHours(24));

        return [
            'upload_id'    => $uploadId,
            'file_id'      => $file->id,
            'total_chunks' => (int) $totalChunks,
        ];
    }

    /**
     * Receive a single chunk.
     */
    public static function receiveChunk(Request $request): array
    {
        $uploadId = $request->input('upload_id');
        $chunkIndex = (int) $request->input('chunk_index', 0);
        $meta = cache()->get("chunk_upload:{$uploadId}");

        if (! $meta) {
            abort(404, 'Upload session not found or expired.');
        }

        $tempDir = $meta['temp_dir'];
        $disk = config('files.disk', 'local');

        // Accept chunk as file upload or raw body
        if ($request->hasFile('chunk')) {
            $content = $request->file('chunk')->getContent();
        } else {
            $content = $request->getContent();
        }

        Storage::disk($disk)->put("{$tempDir}/{$chunkIndex}", $content);

        $meta['received'][] = $chunkIndex;
        $meta['received'] = array_unique($meta['received']);
        cache()->put("chunk_upload:{$uploadId}", $meta, now()->addHours(24));

        $complete = count($meta['received']) >= $meta['total_chunks'];

        if ($complete) {
            static::assembleChunks($uploadId);
        }

        // Broadcast progress if websockets available
        static::broadcastProgress($uploadId, $chunkIndex, $meta['total_chunks'], $complete);

        return [
            'upload_id'      => $uploadId,
            'chunk_index'    => $chunkIndex,
            'received'       => count($meta['received']),
            'total_chunks'   => $meta['total_chunks'],
            'complete'       => $complete,
        ];
    }

    /**
     * Assemble all chunks into the final file.
     */
    protected static function assembleChunks(string $uploadId): void
    {
        $meta = cache()->get("chunk_upload:{$uploadId}");
        if (! $meta) {
            return;
        }

        $disk = config('files.disk', 'local');
        $file = File::findOrFail($meta['file_id']);
        $tempDir = $meta['temp_dir'];

        // Concatenate chunks in order
        $assembled = '';
        for ($i = 0; $i < $meta['total_chunks']; $i++) {
            $chunkPath = "{$tempDir}/{$i}";
            if (Storage::disk($disk)->exists($chunkPath)) {
                $assembled .= Storage::disk($disk)->get($chunkPath);
            }
        }

        $file->putContents($assembled);

        // Cleanup temp chunks
        for ($i = 0; $i < $meta['total_chunks']; $i++) {
            Storage::disk($disk)->delete("{$tempDir}/{$i}");
        }
        Storage::disk($disk)->deleteDirectory($tempDir);

        cache()->forget("chunk_upload:{$uploadId}");
    }

    /**
     * Broadcast upload progress via event (works with or without websockets).
     */
    protected static function broadcastProgress(string $uploadId, int $chunkIndex, int $total, bool $complete): void
    {
        try {
            if (class_exists(\Illuminate\Support\Facades\Broadcast::class)) {
                event(new \Blax\Files\Events\ChunkUploadProgress($uploadId, $chunkIndex, $total, $complete));
            }
        } catch (\Throwable $e) {
            // Websockets not available — ignore silently
        }
    }
}
