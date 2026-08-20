<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-load migrations
    |--------------------------------------------------------------------------
    |
    | When true (the default) the package auto-loads its migrations from
    | `vendor/blax-software/laravel-files/database/migrations` so a fresh
    | `composer require` + `php artisan migrate` Just Works™. Set to false
    | if you publish the migrations and want to manage them yourself — the
    | package will then defer entirely to your published copies.
    |
    | See: laravel-workkit/PRINCIPLES/laravel-composer-packages.md
    |
    */

    'run_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Override these to use your own model classes.
    |
    */

    'models' => [
        'file'    => \Blax\Files\Models\File::class,
        'filable' => \Blax\Files\Models\Filable::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */

    'table_names' => [
        'files'    => 'files',
        'filables' => 'filables',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk used for storing files. Any disk configured in
    | config/filesystems.php is supported (local, s3, gcs, …).
    |
    */

    'disk' => env('FILES_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Storage Path Template
    |--------------------------------------------------------------------------
    |
    | The relative path template for new uploads. Placeholders:
    |   {user_id}  – authenticated user ID or "anonymous"
    |   {uuid}     – generated UUID of the file
    |   {date}     – Y/m/d subdirectory
    |
    */

    'storage_path' => 'files/{date}/{uuid}',

    /*
    |--------------------------------------------------------------------------
    | Warehouse Route
    |--------------------------------------------------------------------------
    |
    | These settings control the public warehouse route that serves files.
    |
    */

    'warehouse' => [
        'enabled'    => true,
        'prefix'     => 'warehouse',
        'middleware' => ['web'],

        /*
        | Resolver used by the access-control middleware to find the file for a
        | warehouse request. A class implementing
        | Blax\Files\Contracts\ResolvesWarehouseFiles (or any invokable). When
        | null, the package WarehouseService is used. Host apps with a custom
        | lookup flow (encrypted ids, client/server assets, …) point this at
        | their own resolver so the middleware reuses it. Keep this a class
        | string — closures here would break `config:cache`.
        */
        'resolver'   => env('FILES_WAREHOUSE_RESOLVER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    */

    'upload' => [
        'max_size'       => 50 * 1024, // KB (50 MB)
        'chunk_size'     => 1024,       // KB per chunk (1 MB)
        'allowed_mimes'  => [], // empty = allow all
        'route_prefix'   => 'api/files',
        'middleware'      => ['api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Optimization
    |--------------------------------------------------------------------------
    |
    | Requires spatie/image ^3.8. Optimization is skipped if not installed.
    |
    */

    'optimization' => [
        'enabled'            => true,
        'default_quality'    => 85,
        'webp_conversion'    => true,
        'round_dimensions'   => true,
        'round_to'           => 50,
        'skip_formats'       => ['gif', 'svg', 'svg+xml'],
        'preferred_extensions' => ['svg', 'webp', 'png', 'jpg', 'jpeg'],

        // Decompression-bomb guard. Decoding an image allocates ~4 bytes/pixel,
        // so a very large source (e.g. a 74-megapixel upload) can blow the PHP
        // memory_limit with an UNCATCHABLE fatal that 500s the warehouse request.
        // When the source exceeds this pixel budget, resizing is skipped and the
        // original is served instead. 0 disables the guard.
        'max_source_megapixels' => 40,
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Control
    |--------------------------------------------------------------------------
    |
    | When enabled, the FileAccessControl middleware enforces the File model's
    | canBeAccessedBy() decision on every warehouse request and 403s anyone who
    | is not allowed. Default false = serve all files publicly (backwards
    | compatible). Override canBeAccessedBy() on your File model to define the
    | per-file policy.
    |
    */

    'access_control' => [
        'enabled' => env('FILES_ACCESS_CONTROL', false),
    ],

];
