<?php

namespace Blax\Files\Services;

use Blax\Files\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WarehouseService
{
    /**
     * Search for a file based on the request and identifier.
     */
    public static function searchFile(Request $request, ?string $identifier): ?File
    {
        if (! $identifier) {
            return null;
        }

        // Strip query string if present
        if (str_contains($identifier, '?')) {
            $identifier = explode('?', $identifier)[0];
        }

        // 1. Try UUID lookup
        $file = static::searchByUuid($identifier);
        if ($file) {
            return $file;
        }

        // 2. Try encrypted ID
        $file = static::searchByEncryptedId($identifier);
        if ($file) {
            return $file;
        }

        // 3. Try as static asset path
        $file = static::searchAssetPath($identifier);
        if ($file) {
            return $file;
        }

        // 4. Try raw storage path
        return static::searchStoragePath($identifier);
    }

    /**
     * Search by UUID (direct File ID).
     */
    protected static function searchByUuid(string $identifier): ?File
    {
        return File::find($identifier);
    }

    /**
     * Search by encrypted (legacy) ID.
     */
    protected static function searchByEncryptedId(string $identifier): ?File
    {
        try {
            $decryptedId = decrypt($identifier);
            if ($decryptedId) {
                return File::find($decryptedId);
            }
        } catch (\Exception $e) {
            // Not an encrypted ID — ignore
        }

        return null;
    }

    /**
     * Search for a static asset, trying preferred extensions.
     */
    protected static function searchAssetPath(string $path): ?File
    {
        $disk = config('files.disk', 'local');
        $extensions = config('files.optimization.preferred_extensions', ['svg', 'webp', 'png', 'jpg', 'jpeg']);

        // Try exact path
        if (Storage::disk($disk)->exists($path)) {
            return static::fileInstanceFromPath($path, $disk);
        }

        // Try with preferred extensions if no extension detected
        if (! pathinfo($path, PATHINFO_EXTENSION)) {
            foreach ($extensions as $ext) {
                if (Storage::disk($disk)->exists($path . '.' . $ext)) {
                    return static::fileInstanceFromPath($path . '.' . $ext, $disk);
                }
            }
        }

        return null;
    }

    /**
     * Search by raw storage path.
     */
    protected static function searchStoragePath(string $path): ?File
    {
        $disk = config('files.disk', 'local');
        $path = str_replace('storage/', '', $path);

        if (Storage::disk($disk)->exists($path)) {
            return static::fileInstanceFromPath($path, $disk);
        }

        return null;
    }

    /**
     * Create a non-persisted File model instance for serving a storage path.
     */
    protected static function fileInstanceFromPath(string $relativePath, string $disk): File
    {
        $file = new File;
        $file->name = basename($relativePath);
        $file->relativepath = $relativePath;
        $file->disk = $disk;
        $file->extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        $file->exists = false; // not persisted

        return $file;
    }

    /**
     * Generate the public warehouse URL for a file.
     */
    public static function url(File|string $file): string
    {
        $id = $file instanceof File ? $file->id : $file;
        $prefix = config('files.warehouse.prefix', 'warehouse');

        return url("{$prefix}/{$id}");
    }
}
