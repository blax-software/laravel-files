<?php

namespace Blax\Files\Http\Controllers;

use Blax\Files\Models\File;
use Blax\Files\Services\ChunkUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FileUploadController extends Controller
{
    /**
     * Standard single-file upload.
     */
    public function upload(Request $request): JsonResponse
    {
        $rules = [
            'file' => 'required|file|max:' . config('files.upload.max_size', 51200),
        ];

        $allowedMimes = config('files.upload.allowed_mimes', []);
        if (! empty($allowedMimes)) {
            $rules['file'] .= '|mimes:' . implode(',', $allowedMimes);
        }

        $request->validate($rules);

        $uploaded = $request->file('file');

        $fileModel = config('files.models.file', File::class);
        $file = new $fileModel;
        $file->save();
        $file->putContentsFromUpload($uploaded);

        return response()->json([
            'id'         => $file->id,
            'name'       => $file->name,
            'type'       => $file->type,
            'extension'  => $file->extension,
            'size'       => $file->size,
            'size_human' => $file->size_human,
            'url'        => $file->url,
        ], 201);
    }

    /**
     * Initialize a chunked upload.
     */
    public function chunkInit(Request $request): JsonResponse
    {
        $request->validate([
            'filename'     => 'required|string',
            'filesize'     => 'required|integer',
            'total_chunks' => 'required|integer|min:1',
            'mime_type'    => 'nullable|string',
            'extension'    => 'nullable|string',
        ]);

        $result = ChunkUploadService::initialize($request);

        return response()->json($result, 201);
    }

    /**
     * Receive a chunk.
     */
    public function chunkUpload(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id'   => 'required|string',
            'chunk_index' => 'required|integer|min:0',
        ]);

        $result = ChunkUploadService::receiveChunk($request);

        return response()->json($result);
    }
}
