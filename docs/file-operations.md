# File Operations

The `File` model is the central entity. It uses UUID primary keys, stores metadata, and wraps disk operations for reading, writing, and deleting file contents.

## Creating a File

```php
use Blax\Files\Models\File;

$file = new File;
$file->name = 'report';
$file->extension = 'pdf';
$file->save();
```

On save, the model automatically sets:
- `disk` — from `config('files.disk')` (default: `local`)
- `relativepath` — generated from the `storage_path` template (default: `files/{date}/{uuid}`)
- `user_id` — from the authenticated user, if available

## Writing Content

### From a string

```php
$file->putContents('Hello, world!');
```

### From a local file path

```php
$file->putContentsFromPath('/tmp/export.csv');
```

### From a URL

```php
$file->putContentsFromUrl('https://example.com/data.json');
```

### From an `UploadedFile`

```php
$file->putContentsFromUpload($request->file('document'));
```

This also sets `name`, `extension`, and `type` from the upload metadata if they aren't already set.

All `putContents*` methods auto-detect `extension`, `type` (MIME), and `size`, then persist the model.

---

## Reading Content

```php
$contents = $file->getContents();  // raw string or null
$exists   = $file->hasContents();  // bool
```

---

## Deleting Content

```php
$file->deleteContents();  // removes file from disk (and resized variants)
$file->delete();          // deletes the model + contents + pivot entries
```

When a `File` model is deleted, the `deleting` event automatically calls `deleteContents()` and removes all `filables` pivot rows.

---

## Serving Files

### Inline Response

```php
return $file->respond();           // serves the file inline
return $file->respond($request);   // uses request params for resizing
```

When the request includes a `size` parameter and the file is an image, `respond()` automatically serves a resized variant. See [Image Optimization](image-optimization.md).

### Download Response

```php
return $file->download();                    // downloads as "name.ext"
return $file->download('custom-name.pdf');   // custom filename
```

---

## Duplicating a File

```php
$copy = $file->duplicate();                  // "report (copy)"
$copy = $file->duplicate('report-backup');   // custom name
```

Creates a new `File` record with a new UUID and copies the disk contents. The copy is independent — changing one does not affect the other.

---

## Checking Image Status

```php
$file->isImage();  // true if MIME starts with "image" or extension is an image type
```

Recognized image extensions: `jpg`, `jpeg`, `png`, `gif`, `webp`, `svg`, `bmp`, `ico`.

---

## Accessors

| Accessor            | Returns              | Example                              |
|---------------------|----------------------|--------------------------------------|
| `$file->path`       | Absolute local path  | `/storage/files/2024/06/15/abc-123`  |
| `$file->url`        | Public warehouse URL | `https://app.test/warehouse/abc-123` |
| `$file->size_human` | Human-readable size  | `2.4 MB`                             |

`url` and `size_human` are also included when the model is serialized via `toArray()` or `toJson()`.

---

## Scopes

```php
// Only images
File::images()->get();

// By extension
File::byExtension('pdf', 'docx')->get();

// By disk
File::byDisk('s3')->get();

// Orphaned files (not attached to any model)
File::orphaned()->get();

// Recent files (last N days, default 7)
File::recent()->get();
File::recent(30)->get();
```

---

## Metadata

The `meta` column is cast to JSON. Use it for arbitrary structured data:

```php
$file->meta = ['source' => 'import', 'batch_id' => 42];
$file->save();

$file->meta['source'];  // 'import'
```

---

## Model Attributes

| Column             | Type           | Description                                    |
|--------------------|----------------|------------------------------------------------|
| `id`               | UUID string    | Primary key (auto-generated)                   |
| `user_id`          | string\|null   | Owner (auto-filled from auth)                  |
| `name`             | string\|null   | Display name (without extension)               |
| `extension`        | string\|null   | File extension (`pdf`, `jpg`, …)               |
| `type`             | string\|null   | MIME type (`application/pdf`, `image/jpeg`, …) |
| `size`             | int\|null      | Size in bytes                                  |
| `disk`             | string         | Filesystem disk name                           |
| `relativepath`     | string         | Relative path on the disk                      |
| `meta`             | json\|null     | Arbitrary metadata                             |
| `last_accessed_at` | datetime\|null | Tracking field                                 |
| `created_at`       | datetime       |                                                |
| `updated_at`       | datetime       |                                                |

---

Next: [Uploading Files](uploading.md)
