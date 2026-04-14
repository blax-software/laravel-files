# Installation

## Requirements

- PHP 8.1+
- Laravel 9, 10, 11, or 12

## Install via Composer

```bash
composer require blax-software/laravel-files
```

The service provider is auto-discovered. No manual registration needed.

## Publish Config

```bash
php artisan vendor:publish --tag=files-config
```

This creates `config/files.php` where you can customize models, table names, disk, storage paths, upload limits, optimization settings, and more. See the [Configuration Reference](configuration.md) for details.

## Publish & Run Migrations

```bash
php artisan vendor:publish --tag=files-migrations
php artisan migrate
```

This creates two tables:

| Table      | Purpose                                                                                |
|------------|----------------------------------------------------------------------------------------|
| `files`    | Stores file metadata (UUID primary key, name, extension, type, size, disk, path, meta) |
| `filables` | Polymorphic pivot — links files to any model with a role (`as`), order, and meta       |

## Environment Variables

Add to your `.env` if you want to change the storage disk:

```env
FILES_DISK=s3
```

By default, files are stored on the `local` disk.

## Optional: Image Optimization

To enable automatic image resizing and WebP conversion:

```bash
composer require spatie/image "^3.8"
```

No further configuration needed — the package detects `spatie/image` at runtime.

## Optional: Access Control

To protect files behind role-based access checks, install [laravel-roles](https://github.com/blax-software/laravel-roles) and enable it in `config/files.php`:

```php
'access_control' => [
    'enabled' => true,
],
```

## Optional: WebSocket Progress

For real-time chunk upload progress, install [laravel-websockets](https://github.com/blax-software/laravel-websockets):

```bash
composer require blax-software/laravel-websockets
```

The `ChunkUploadProgress` event is broadcast automatically when websockets are available.

---

Next: [Attaching Files](attaching-files.md)
