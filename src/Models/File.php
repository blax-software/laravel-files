<?php

namespace Blax\Files\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'extension',
        'type',
        'size',
        'disk',
        'relativepath',
        'meta',
    ];

    protected $casts = [
        'id' => 'string',
        'meta' => 'json',
        'last_accessed_at' => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('files.table_names.files') ?: parent::getTable();
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    public static function booted()
    {
        static::saving(function (self $file) {
            $file->disk ??= config('files.disk', 'local');

            if (! $file->relativepath) {
                $file->relativepath = static::buildStoragePath($file);
            }

            $file->user_id ??= optional(optional(auth())->user())->id;
        });
    }

    protected static function buildStoragePath(self $file): string
    {
        $template = config('files.storage_path', 'files/{date}/{uuid}');

        return str_replace(
            ['{user_id}', '{uuid}', '{date}'],
            [
                optional(optional(auth())->user())->id ?? 'anonymous',
                $file->id ?? (string) \Illuminate\Support\Str::uuid(),
                now()->format('Y/m/d'),
            ],
            $template,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(
            config('auth.providers.users.model', 'App\\Models\\User'),
            'user_id',
        );
    }

    public function filables()
    {
        return $this->hasMany(config('files.models.filable', Filable::class));
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeImages($query)
    {
        return $query->where(function ($q) {
            $q->where('type', 'like', 'image/%')
                ->orWhereIn('extension', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico']);
        });
    }

    public function scopeByExtension($query, string ...$extensions)
    {
        return $query->whereIn('extension', $extensions);
    }

    public function scopeByDisk($query, string $disk)
    {
        return $query->where('disk', $disk);
    }

    public function scopeOrphaned($query)
    {
        return $query->whereDoesntHave('filables');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getSizeHumanAttribute(): string
    {
        $bytes = $this->size ?? 0;
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    public function getPathAttribute(): string
    {
        return Storage::disk($this->disk)->path($this->relativepath);
    }

    public function getUrlAttribute(): string
    {
        $prefix = config('files.warehouse.prefix', 'warehouse');

        return url("{$prefix}/{$this->id}");
    }

    /*
    |--------------------------------------------------------------------------
    | File Content Operations
    |--------------------------------------------------------------------------
    */

    public function putContents(string $contents): static
    {
        Storage::disk($this->disk)->put($this->relativepath, $contents);

        if (! $this->extension) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($contents);
            $this->extension = explode('/', $mimeType)[1] ?? null;
        }

        if (! $this->type) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $this->type = $finfo->buffer($contents);
        }

        $this->size = Storage::disk($this->disk)->size($this->relativepath);
        $this->save();

        return $this;
    }

    public function putContentsFromPath(string $absolutePath): static
    {
        return $this->putContents(file_get_contents($absolutePath));
    }

    public function putContentsFromUrl(string $url): static
    {
        return $this->putContents(file_get_contents($url));
    }

    public function putContentsFromUpload(\Illuminate\Http\UploadedFile $upload): static
    {
        $this->name ??= pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME);
        $this->extension ??= $upload->getClientOriginalExtension();
        $this->type ??= $upload->getMimeType();
        $this->save();

        return $this->putContents($upload->getContent());
    }

    public function getContents(): ?string
    {
        return Storage::disk($this->disk)->get($this->relativepath);
    }

    public function hasContents(): bool
    {
        return Storage::disk($this->disk)->exists($this->relativepath);
    }

    public function deleteContents(): static
    {
        Storage::disk($this->disk)->delete($this->relativepath);

        // Remove resized variants
        $dir = pathinfo($this->path, PATHINFO_DIRNAME);
        $resizedDir = $dir . '/resized';
        if (is_dir($resizedDir)) {
            $files = glob($resizedDir . '/' . basename($this->relativepath) . '*');
            foreach ($files as $f) {
                @unlink($f);
            }
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Response / Serving
    |--------------------------------------------------------------------------
    */

    public function respond(?\Illuminate\Http\Request $request = null): \Symfony\Component\HttpFoundation\Response
    {
        $request ??= request();

        // If a size is requested and optimization is available, serve resized
        if ($request->has('size') && $this->isImage()) {
            $path = $this->resolveResizedPath($request);
        } else {
            $path = $this->path;
        }

        if (! file_exists($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function download(?string $filename = null): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = $this->path;

        if (! file_exists($path)) {
            abort(404);
        }

        $name = $filename ?? ($this->name . '.' . $this->extension);

        return response()->download($path, $name);
    }

    public function isImage(): bool
    {
        if ($this->type && str_starts_with($this->type, 'image')) {
            return true;
        }

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];

        return in_array(strtolower($this->extension ?? ''), $imageExtensions);
    }

    /*
    |--------------------------------------------------------------------------
    | Image Optimization / Resizing
    |--------------------------------------------------------------------------
    */

    public function resolveResizedPath(\Illuminate\Http\Request $request): string
    {
        if (! class_exists(\Spatie\Image\Image::class)) {
            return $this->path;
        }

        $config = config('files.optimization', []);
        $skipFormats = $config['skip_formats'] ?? ['gif', 'svg', 'svg+xml'];
        $ext = strtolower($this->extension ?? '');

        // Skip non-optimizable formats
        if (in_array($ext, $skipFormats) || str_contains($ext, 'svg')) {
            return $this->path;
        }

        $size = $request->get('size', '');
        $parts = explode('x', $size);
        $width = $parts[0] ?? null;
        $height = $parts[1] ?? $width;

        $quality = $request->has('quality') ? (int) $request->get('quality') : ($config['default_quality'] ?? 85);
        $webp = filter_var($request->get('webp', $config['webp_conversion'] ?? true), FILTER_VALIDATE_BOOLEAN);
        $cached = filter_var($request->get('cached', true), FILTER_VALIDATE_BOOLEAN);
        $rounding = filter_var($request->get('rounding', $config['round_dimensions'] ?? true), FILTER_VALIDATE_BOOLEAN);
        $position = $request->get('position', 'cover');

        return $this->resizedPath(
            $width,
            $height,
            rounding: $rounding,
            toWebp: $webp,
            cached: $cached,
            quality: $quality,
            position: $position,
        );
    }

    public function resizedPath(
        string|int|null $width,
        string|int|null $height,
        bool $rounding = true,
        bool $toWebp = true,
        bool $cached = true,
        ?int $quality = null,
        string $position = 'cover',
    ): string {
        $path = $this->path;
        $ext = strtolower($this->extension ?? pathinfo($path, PATHINFO_EXTENSION));

        // Normalize dimensions
        if ($width !== null && strtolower((string) $width) !== 'auto') {
            $width = max(1, (int) $width);
        }
        if ($height !== null && strtolower((string) $height) !== 'auto') {
            $height = max(1, (int) $height);
        }

        $width = $width ?: $height;
        $height = $height ?: $width;

        // Round to nearest step
        $roundTo = config('files.optimization.round_to', 50);
        if ($rounding) {
            $width = ($width === 'auto') ? $width : (int) (ceil((int) $width / $roundTo) * $roundTo);
            $height = ($height === 'auto') ? $height : (int) (ceil((int) $height / $roundTo) * $roundTo);
        }

        // Build cache key
        $dir = pathinfo($path, PATHINFO_DIRNAME);
        $resizedDir = $dir . '/resized';
        if (! is_dir($resizedDir)) {
            @mkdir($resizedDir, 0755, true);
        }

        $cacheKey = $width . 'x' . $height;
        if ($position !== 'cover') {
            $cacheKey .= '.' . $position;
        }
        if ($quality !== null && $quality > 0 && $quality < 100) {
            $cacheKey .= '.q' . $quality;
        }

        $cachedPath = $resizedDir . '/' . basename($path, '.' . $ext) . '.' . $cacheKey . '.' . $ext;
        if ($toWebp && $this->isImage()) {
            $cachedPath .= '.webp';
        }

        // Return cached version if available
        if ($cached && file_exists($cachedPath)) {
            return $cachedPath;
        }

        // Generate resized version
        copy($path, $cachedPath);

        $fit = match ($position) {
            'contain' => \Spatie\Image\Enums\Fit::Contain,
            'fill'    => \Spatie\Image\Enums\Fit::Fill,
            'max'     => \Spatie\Image\Enums\Fit::Max,
            'stretch' => \Spatie\Image\Enums\Fit::Stretch,
            default   => \Spatie\Image\Enums\Fit::Crop,
        };

        $image = \Spatie\Image\Image::load($cachedPath)
            ->fit(
                $fit,
                ($width === 'auto') ? null : (int) $width,
                ($height === 'auto') ? null : (int) $height,
            );

        if ($quality !== null && $quality > 0) {
            $image->quality(min(100, max(1, $quality)));
        }

        $image->save($cachedPath);

        return $cachedPath;
    }

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    */

    protected static function booting()
    {
        static::deleting(function (self $file) {
            $file->deleteContents();
            $file->filables()->delete();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Duplication
    |--------------------------------------------------------------------------
    */

    public function duplicate(?string $newName = null): static
    {
        $clone = $this->replicate(['id', 'relativepath']);
        $clone->name = $newName ?? ($this->name . ' (copy)');
        $clone->save();

        if ($this->hasContents()) {
            $clone->putContents($this->getContents());
        }

        return $clone;
    }

    /*
    |--------------------------------------------------------------------------
    | Serialization
    |--------------------------------------------------------------------------
    */

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['url'] = $this->url;
        $array['size_human'] = $this->size_human;

        return $array;
    }
}
