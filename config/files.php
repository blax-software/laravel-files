<?php

return [

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
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Control
    |--------------------------------------------------------------------------
    |
    | When laravel-roles is installed, files can be protected via access
    | checks. Set 'enabled' to false to serve all files publicly.
    |
    */

    'access_control' => [
        'enabled' => false,
    ],

];
