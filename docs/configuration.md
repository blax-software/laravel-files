# Configuration Reference

After publishing the config file (`php artisan vendor:publish --tag=files-config`), you'll find `config/files.php` with the following sections.

---

## Models

Override the default models with your own implementations:

```php
'models' => [
    'file'    => \Blax\Files\Models\File::class,
    'filable' => \Blax\Files\Models\Filable::class,
],
```

Custom models should extend the package models.

---

## Table Names

```php
'table_names' => [
    'files'    => 'files',
    'filables' => 'filables',
],
```

Change these **before** running migrations if you need custom table names.

---

## Disk

```php
'disk' => env('FILES_DISK', 'local'),
```

Any disk from `config/filesystems.php` works: `local`, `public`, `s3`, etc. Set via `FILES_DISK` in your `.env`.

---

## Storage Path Template

```php
'storage_path' => 'files/{date}/{uuid}',
```

Defines how uploaded files are organized on disk. Available placeholders:

| Placeholder | Value                                   |
|-------------|-----------------------------------------|
| `{user_id}` | Authenticated user ID, or `"anonymous"` |
| `{uuid}`    | File's UUID                             |
| `{date}`    | Current date as `Y/m/d`                 |

Examples:

```php
'storage_path' => 'uploads/{user_id}/{date}/{uuid}',
'storage_path' => 'files/{uuid}',
```

---

## Warehouse

```php
'warehouse' => [
    'enabled'    => true,
    'prefix'     => 'warehouse',
    'middleware' => ['web'],
],
```

| Key          | Type     | Default       | Description                           |
|--------------|----------|---------------|---------------------------------------|
| `enabled`    | `bool`   | `true`        | Register the warehouse route          |
| `prefix`     | `string` | `'warehouse'` | URL prefix for the route              |
| `middleware` | `array`  | `['web']`     | Middleware group applied to the route |

---

## Upload

```php
'upload' => [
    'max_size'       => 50 * 1024,              // 50 MB in KB
    'chunk_size'     => 1024,                    // 1 MB per chunk
    'allowed_mimes'  => [],                      // empty = allow all
    'route_prefix'   => 'api/files',
    'middleware'      => ['api', 'auth:sanctum'],
],
```

| Key             | Type     | Default                   | Description                                         |
|-----------------|----------|---------------------------|-----------------------------------------------------|
| `max_size`      | `int`    | `51200`                   | Max file size in KB                                 |
| `chunk_size`    | `int`    | `1024`                    | Chunk size in KB                                    |
| `allowed_mimes` | `array`  | `[]`                      | Allowed MIME types/extensions. Empty = all allowed. |
| `route_prefix`  | `string` | `'api/files'`             | URL prefix for upload routes                        |
| `middleware`    | `array`  | `['api', 'auth:sanctum']` | Middleware for upload routes                        |

### Restricting MIME Types

```php
'allowed_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'docx'],
```

This is enforced at the controller level via Laravel's `mimes` validation rule.

---

## Image Optimization

```php
'optimization' => [
    'enabled'              => true,
    'default_quality'      => 85,
    'webp_conversion'      => true,
    'round_dimensions'     => true,
    'round_to'             => 50,
    'skip_formats'         => ['gif', 'svg', 'svg+xml'],
    'preferred_extensions' => ['svg', 'webp', 'png', 'jpg', 'jpeg'],
],
```

| Key                    | Type    | Default                                 | Description                                      |
|------------------------|---------|-----------------------------------------|--------------------------------------------------|
| `enabled`              | `bool`  | `true`                                  | Enable image optimization features               |
| `default_quality`      | `int`   | `85`                                    | Default JPEG/WebP quality (1–100)                |
| `webp_conversion`      | `bool`  | `true`                                  | Convert images to WebP by default                |
| `round_dimensions`     | `bool`  | `true`                                  | Round resize dimensions to reduce cache variants |
| `round_to`             | `int`   | `50`                                    | Rounding step in pixels                          |
| `skip_formats`         | `array` | `['gif', 'svg', 'svg+xml']`             | Formats that bypass optimization                 |
| `preferred_extensions` | `array` | `['svg', 'webp', 'png', 'jpg', 'jpeg']` | Extension order for auto-resolution              |

See [Image Optimization](image-optimization.md) for detailed usage.

---

## Access Control

```php
'access_control' => [
    'enabled' => false,
],
```

| Key       | Type   | Default | Description                                            |
|-----------|--------|---------|--------------------------------------------------------|
| `enabled` | `bool` | `false` | Enable role-based access checks on the warehouse route |

Requires [laravel-roles](https://github.com/blax-software/laravel-roles). See [Serving Files](serving-files.md#access-control).

---

## Full Default Config

```php
<?php

return [
    'models' => [
        'file'    => \Blax\Files\Models\File::class,
        'filable' => \Blax\Files\Models\Filable::class,
    ],
    'table_names' => [
        'files'    => 'files',
        'filables' => 'filables',
    ],
    'disk' => env('FILES_DISK', 'local'),
    'storage_path' => 'files/{date}/{uuid}',
    'warehouse' => [
        'enabled'    => true,
        'prefix'     => 'warehouse',
        'middleware' => ['web'],
    ],
    'upload' => [
        'max_size'       => 50 * 1024,
        'chunk_size'     => 1024,
        'allowed_mimes'  => [],
        'route_prefix'   => 'api/files',
        'middleware'      => ['api', 'auth:sanctum'],
    ],
    'optimization' => [
        'enabled'              => true,
        'default_quality'      => 85,
        'webp_conversion'      => true,
        'round_dimensions'     => true,
        'round_to'             => 50,
        'skip_formats'         => ['gif', 'svg', 'svg+xml'],
        'preferred_extensions' => ['svg', 'webp', 'png', 'jpg', 'jpeg'],
    ],
    'access_control' => [
        'enabled' => false,
    ],
];
```

---

Next: [Artisan Commands](artisan-commands.md)
