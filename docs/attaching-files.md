# Attaching Files to Models

The `HasFiles` trait connects any Eloquent model to the file system. Add the trait, and your model gains a polymorphic many-to-many relationship with the `File` model.

## Setup

```php
use Blax\Files\Traits\HasFiles;

class Product extends Model
{
    use HasFiles;
}
```

No further configuration needed — the trait registers a `files()` relationship automatically.

## The `files()` Relationship

```php
$product->files;                  // Collection of all attached File models
$product->files()->count();       // number of files
$product->files()->get();         // query builder — add your own constraints
```

Every pivot row carries three extra columns: `as` (role), `order`, and `meta` (JSON).

---

## Attaching Files

### `attachFile()`

```php
$product->attachFile($file, as: FileLinkType::Gallery, order: 0);
```

| Parameter  | Type                         | Default | Description                                                      |
|------------|------------------------------|---------|------------------------------------------------------------------|
| `$file`    | `File\|string`               | —       | A File model or its UUID                                         |
| `$as`      | `string\|FileLinkType\|null` | `null`  | Role / category for this attachment                              |
| `$order`   | `?int`                       | `null`  | Sort position                                                    |
| `$meta`    | `?array`                     | `null`  | Arbitrary metadata stored as JSON on the pivot                   |
| `$replace` | `bool`                       | `false` | If `true`, removes existing attachments with the same role first |

Duplicate prevention is built-in — attaching the same file with the same role twice is a no-op.

#### Replace Mode

Use `replace: true` for singular fields like avatars:

```php
$user->attachFile($newAvatar, as: FileLinkType::Avatar, replace: true);
// The old avatar is detached; the new one takes its place.
```

#### Storing Metadata

```php
$product->attachFile($file, as: 'document', meta: [
    'description' => 'Product specification sheet',
    'version'     => '2.1',
]);
```

### Method Chaining

All attach/detach methods return `$this`, so you can chain:

```php
$product
    ->attachFile($logo, as: FileLinkType::Logo, replace: true)
    ->attachFile($hero, as: FileLinkType::Banner, replace: true)
    ->attachFile($spec, as: FileLinkType::Document);
```

---

## Detaching Files

### `detachFile()`

Remove a specific file (optionally scoped by role):

```php
$product->detachFile($file);
$product->detachFile($file, as: 'gallery');
```

### `detachFilesAs()`

Remove **all** files with a given role:

```php
$product->detachFilesAs(FileLinkType::Gallery);
$product->detachFilesAs('document');
```

### `detachAllFiles()`

Remove every file attachment from the model:

```php
$product->detachAllFiles();
```

> **Note:** Detaching does not delete the `File` record or its contents — it only removes the pivot link. To delete a file permanently, call `$file->delete()`.

---

## Querying Attached Files

### By Role

```php
// Get all gallery images
$images = $product->filesAs(FileLinkType::Gallery)->get();

// Get the first document
$doc = $product->fileAs('document');
```

### Convenience Getters

These methods resolve common roles with built-in fallback logic:

```php
$user->getAvatar();      // tries Avatar, then ProfileImage
$user->getThumbnail();
$user->getBanner();
$user->getCoverImage();
$user->getBackground();
$user->getLogo();
$user->getGallery();     // returns a Collection
```

Each returns a `?File` (or a `Collection` for `getGallery()`).

---

## Pivot Access

### Reading Pivot Data

```php
$pivot = $product->getFilePivot($file); // returns ?Filable

$pivot->as;    // 'gallery'
$pivot->order; // 2
$pivot->meta;  // ['description' => '...']
```

### Updating Pivot Data

The `Filable` model provides convenient setters:

```php
$pivot->setAs(FileLinkType::Banner);   // updates and saves
$pivot->setOrder(5);                   // updates and saves
$pivot->getLinkType();                 // returns ?FileLinkType enum
```

---

## Reordering Files

Reorder a set of files by passing their IDs in the desired order:

```php
$product->reorderFiles([
    $fileC->id,
    $fileA->id,
    $fileB->id,
]);
```

Scope to a specific role:

```php
$product->reorderFiles($ids, as: FileLinkType::Gallery);
```

The pivot `order` column is set to the array index (0, 1, 2, …). Files are auto-sorted by `order` thanks to a global scope on the `Filable` model.

---

## Upload Helpers

The trait includes three convenience methods that create a file **and** attach it in a single call:

### `uploadFile()`

Upload from a Laravel `UploadedFile` (e.g. `$request->file('photo')`):

```php
$file = $product->uploadFile(
    $request->file('photo'),
    as: FileLinkType::Gallery,
    order: 0,
    replace: false,
);
```

### `uploadFileFromContents()`

Create a file from raw string content:

```php
$file = $product->uploadFileFromContents(
    contents: $pdfBinary,
    name: 'invoice-2024',
    extension: 'pdf',
    as: FileLinkType::Invoice,
    replace: true,
);
```

### `uploadFileFromUrl()`

Download from a remote URL and attach:

```php
$file = $product->uploadFileFromUrl(
    url: 'https://example.com/photo.jpg',
    name: 'product-hero',
    as: FileLinkType::Banner,
    replace: true,
);
```

All three return the created `File` model.

---

## FileLinkType Enum

The `FileLinkType` enum provides 19 predefined roles grouped into categories:

| Group           | Cases                                                                                       |
|-----------------|---------------------------------------------------------------------------------------------|
| Visual Identity | `Avatar`, `ProfileImage`, `CoverImage`, `Banner`, `Background`, `Logo`, `Icon`, `Thumbnail` |
| Documents       | `Document`, `Invoice`, `Contract`, `Certificate`, `Report`                                  |
| Media           | `Gallery`, `Video`, `Audio`                                                                 |
| Attachments     | `Attachment`, `Download`                                                                    |
| Catch-All       | `Other`                                                                                     |

```php
use Blax\Files\Enums\FileLinkType;

FileLinkType::Avatar->value;     // 'avatar'
FileLinkType::Avatar->label();   // 'Avatar'
FileLinkType::Avatar->isImage(); // true
FileLinkType::Document->isImage(); // false
```

You can also pass plain strings as the `$as` parameter if the built-in enum doesn't fit your use case.

---

Next: [File Operations](file-operations.md)
