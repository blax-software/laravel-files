<?php

namespace Blax\Files\Traits;

use Blax\Files\Enums\FileLinkType;
use Blax\Files\Models\Filable;
use Blax\Files\Models\File;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\UploadedFile;

trait HasFiles
{
    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function files(): MorphToMany
    {
        return $this->morphToMany(
            config('files.models.file', File::class),
            'filable',
            config('files.table_names.filables', 'filables'),
        )
            ->using(config('files.models.filable', Filable::class))
            ->withPivot(['id', 'as', 'order', 'meta'])
            ->withTimestamps();
    }

    public function getFilePivot(File|string $file): ?Filable
    {
        $fileId = $file instanceof File ? $file->id : $file;

        return $this->files()
            ->where('file_id', $fileId)
            ->withPivot(['id', 'as', 'order', 'meta'])
            ->first()?->pivot;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Get files attached with a specific role (e.g. 'avatar', 'thumbnail', …).
     * Accepts a string or a FileLinkType enum.
     */
    public function filesAs(string|FileLinkType $type): MorphToMany
    {
        $value = $type instanceof FileLinkType ? $type->value : $type;

        return $this->files()->wherePivot('as', $value);
    }

    /**
     * Get the first file attached with a specific role.
     */
    public function fileAs(string|FileLinkType $type): ?File
    {
        return $this->filesAs($type)->first();
    }

    /**
     * Convenience: get profile image / avatar.
     */
    public function getAvatar(): ?File
    {
        return $this->fileAs(FileLinkType::Avatar)
            ?? $this->fileAs(FileLinkType::ProfileImage);
    }

    /**
     * Convenience: get thumbnail.
     */
    public function getThumbnail(): ?File
    {
        return $this->fileAs(FileLinkType::Thumbnail);
    }

    /**
     * Convenience: get banner.
     */
    public function getBanner(): ?File
    {
        return $this->fileAs(FileLinkType::Banner);
    }

    /**
     * Convenience: get cover image.
     */
    public function getCoverImage(): ?File
    {
        return $this->fileAs(FileLinkType::CoverImage);
    }

    /**
     * Convenience: get background.
     */
    public function getBackground(): ?File
    {
        return $this->fileAs(FileLinkType::Background);
    }

    /**
     * Convenience: get logo.
     */
    public function getLogo(): ?File
    {
        return $this->fileAs(FileLinkType::Logo);
    }

    /**
     * Convenience: get gallery images.
     */
    public function getGallery(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->filesAs(FileLinkType::Gallery)->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Attach / Detach
    |--------------------------------------------------------------------------
    */

    /**
     * Attach an existing File to this model with a role.
     *
     * If $replace is true (default for singular types like avatar), the
     * previous attachment with that role is removed first.
     */
    public function attachFile(
        File|string $file,
        string|FileLinkType|null $as = null,
        ?int $order = null,
        ?array $meta = null,
        bool $replace = false,
    ): static {
        $fileId = $file instanceof File ? $file->id : $file;
        $asValue = $as instanceof FileLinkType ? $as->value : $as;

        if ($replace && $asValue) {
            $this->detachFilesAs($asValue);
        }

        // Prevent duplicate pivot entries
        $existing = $this->files()
            ->where('file_id', $fileId)
            ->wherePivot('as', $asValue)
            ->exists();

        if (! $existing) {
            $this->files()->attach($fileId, array_filter([
                'as'    => $asValue,
                'order' => $order,
                'meta'  => $meta ? json_encode($meta) : null,
            ], fn($v) => $v !== null));
        }

        return $this;
    }

    /**
     * Detach a specific file from this model.
     */
    public function detachFile(File|string $file, ?string $as = null): static
    {
        $fileId = $file instanceof File ? $file->id : $file;

        $query = $this->files()->newPivotQuery()
            ->where('file_id', $fileId)
            ->where('filable_type', static::class)
            ->where('filable_id', $this->getKey());

        if ($as !== null) {
            $query->where('as', $as);
        }

        $query->delete();

        return $this;
    }

    /**
     * Detach all files with a specific role.
     */
    public function detachFilesAs(string|FileLinkType $type): static
    {
        $value = $type instanceof FileLinkType ? $type->value : $type;

        $this->files()->newPivotQuery()
            ->where('filable_type', static::class)
            ->where('filable_id', $this->getKey())
            ->where('as', $value)
            ->delete();

        return $this;
    }

    /**
     * Detach all files from this model.
     */
    public function detachAllFiles(): static
    {
        $this->files()->detach();

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Upload Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Upload a file and attach it to this model in one call.
     */
    public function uploadFile(
        UploadedFile $upload,
        string|FileLinkType|null $as = null,
        ?int $order = null,
        ?array $meta = null,
        bool $replace = false,
    ): File {
        $fileModel = config('files.models.file', File::class);
        $file = new $fileModel;
        $file->save();
        $file->putContentsFromUpload($upload);

        $this->attachFile($file, $as, $order, $meta, $replace);

        return $file;
    }

    /**
     * Create a file from raw content and attach.
     */
    public function uploadFileFromContents(
        string $contents,
        ?string $name = null,
        ?string $extension = null,
        string|FileLinkType|null $as = null,
        ?int $order = null,
        bool $replace = false,
    ): File {
        $fileModel = config('files.models.file', File::class);
        $file = new $fileModel;
        $file->name = $name;
        $file->extension = $extension;
        $file->save();
        $file->putContents($contents);

        $this->attachFile($file, $as, $order, replace: $replace);

        return $file;
    }

    /**
     * Download a file from URL and attach.
     */
    public function uploadFileFromUrl(
        string $url,
        ?string $name = null,
        string|FileLinkType|null $as = null,
        ?int $order = null,
        bool $replace = false,
    ): File {
        $fileModel = config('files.models.file', File::class);
        $file = new $fileModel;
        $file->name = $name ?? basename(parse_url($url, PHP_URL_PATH));
        $file->save();
        $file->putContentsFromUrl($url);

        $this->attachFile($file, $as, $order, replace: $replace);

        return $file;
    }

    /*
    |--------------------------------------------------------------------------
    | Reorder
    |--------------------------------------------------------------------------
    */

    /**
     * Reorder files — accepts array of file IDs in desired order.
     * Optionally scoped to a specific role.
     */
    public function reorderFiles(array $fileIds, string|FileLinkType|null $as = null): static
    {
        $asValue = $as instanceof FileLinkType ? $as->value : $as;

        foreach ($fileIds as $index => $fileId) {
            $query = $this->files()->newPivotQuery()
                ->where('file_id', $fileId)
                ->where('filable_type', static::class)
                ->where('filable_id', $this->getKey());

            if ($asValue !== null) {
                $query->where('as', $asValue);
            }

            $query->update(['order' => $index]);
        }

        return $this;
    }
}
