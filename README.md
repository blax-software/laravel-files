[![Blax Software OSS](https://raw.githubusercontent.com/blax-software/laravel-workkit/master/art/oss-initiative-banner.svg)](https://github.com/blax-software)

# Laravel Files

A universal, plug-and-play file management system for Laravel. Upload, optimize, serve, and attach files to any Eloquent model — with disk-agnostic storage, automatic image optimization, chunked uploads, and a built-in warehouse endpoint.

---

## Features

- **Attach files to any model** — polymorphic MorphToMany relationship via the `HasFiles` trait
- **Role-based attachments** — tag files as `avatar`, `gallery`, `document`, etc. using the `FileLinkType` enum or custom strings
- **Disk-agnostic** — works with any Laravel filesystem disk (local, S3, GCS, …)
- **Automatic image optimization** — on-the-fly resizing and WebP conversion via [spatie/image](https://github.com/spatie/image)
- **Chunked uploads** — upload large files in pieces with real-time progress broadcasting
- **Warehouse endpoint** — a single route that resolves and serves any file by UUID, encrypted ID, or asset path
- **UUID primary keys** — every file gets a unique, non-sequential identifier
- **Artisan cleanup** — remove orphaned files that are no longer attached to any model

---

## Quick Start

```bash
composer require blax-software/laravel-files
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=files-config
php artisan vendor:publish --tag=files-migrations
php artisan migrate
```

Add the trait to any model:

```php
use Blax\Files\Traits\HasFiles;

class User extends Model
{
    use HasFiles;
}
```

Attach a file:

```php
use Blax\Files\Enums\FileLinkType;

$user = User::find(1);

// Upload and attach in one call
$file = $user->uploadFile($request->file('avatar'), as: FileLinkType::Avatar, replace: true);

// Get the avatar back
$avatar = $user->getAvatar();
echo $avatar->url;       // → /warehouse/019d8ab8-…
echo $avatar->size_human; // → "2.4 MB"
```

---

## Documentation

| Guide                                            | Description                                                   |
|--------------------------------------------------|---------------------------------------------------------------|
| [Installation](docs/installation.md)             | Requirements, installation, configuration                     |
| [Attaching Files](docs/attaching-files.md)       | The `HasFiles` trait, roles, attach/detach, reordering        |
| [File Operations](docs/file-operations.md)       | Creating files, reading/writing contents, duplication, scopes |
| [Uploading](docs/uploading.md)                   | Single uploads, chunked uploads, progress events              |
| [Serving Files](docs/serving-files.md)           | The Warehouse, inline responses, downloads                    |
| [Image Optimization](docs/image-optimization.md) | On-the-fly resizing, WebP, quality control                    |
| [Configuration](docs/configuration.md)           | Full reference for `config/files.php`                         |
| [Artisan Commands](docs/artisan-commands.md)     | `files:cleanup` and maintenance                               |

---

## Optional Dependencies

| Package                            | Purpose                                        |
|------------------------------------|------------------------------------------------|
| `spatie/image ^3.8`                | Image optimization and resizing                |
| `blax-software/laravel-roles`      | Access control on the warehouse endpoint       |
| `blax-software/laravel-websockets` | Real-time chunk upload progress via WebSockets |

---

## Testing

```bash
composer test
# or
./vendor/bin/phpunit
```

---

## License

MIT — see [LICENSE](LICENSE) for details.
