<?php

namespace Blax\Files\Models;

use Blax\Files\Enums\FileLinkType;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

class Filable extends MorphPivot
{
    protected $fillable = [
        'file_id',
        'filable_id',
        'filable_type',
        'as',
        'order',
        'meta',
    ];

    protected $casts = [
        'meta' => 'json',
        'order' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('files.table_names.filables') ?: 'filables';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function file()
    {
        return $this->belongsTo(config('files.models.file', File::class));
    }

    public function filable()
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeOrdered($query)
    {
        return $query->orderByRaw('CASE WHEN "order" IS NULL THEN 1 ELSE 0 END, "order" ASC');
    }

    public function scopeAs($query, string|FileLinkType $type)
    {
        $value = $type instanceof FileLinkType ? $type->value : $type;

        return $query->where('as', $value);
    }

    public static function boot()
    {
        parent::boot();

        static::addGlobalScope('ordered', function ($query) {
            $query->ordered();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function setAs(string|FileLinkType $value, bool $save = true): static
    {
        $this->as = $value instanceof FileLinkType ? $value->value : $value;

        if ($save) {
            $this->save();
        }

        return $this;
    }

    public function setOrder(int $value, bool $save = true): static
    {
        $this->order = $value;

        if ($save) {
            $this->save();
        }

        return $this;
    }

    public function getLinkType(): ?FileLinkType
    {
        return FileLinkType::tryFrom($this->as);
    }
}
