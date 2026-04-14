# Serving Files (Warehouse)

The **Warehouse** is a public-facing route that resolves file identifiers and serves the contents. It acts as a unified file-serving gateway.

## Warehouse Route

```
GET /warehouse/{identifier?}
```

**Middleware:** `web` (configurable)

The identifier can be:

| Format       | Example                                         |
|--------------|-------------------------------------------------|
| UUID         | `9c3a7b2e-...`                                  |
| Encrypted ID | `eyJpdiI6...` (legacy support)                  |
| Asset path   | `images/logo` (auto-tries preferred extensions) |
| Storage path | `files/2024/06/15/abc-123`                      |

### Resolution Order

The `WarehouseService::searchFile()` method tries each strategy in order:

1. **UUID** — direct `File::find($identifier)`
2. **Encrypted ID** — `decrypt()` → `File::find()`
3. **Asset path** — check if file exists on disk; if no extension is provided, try `svg`, `webp`, `png`, `jpg`, `jpeg` in order
4. **Raw storage path** — strip `storage/` prefix and check disk

The first match wins. If nothing is found, a `404` is returned.

### Example URLs

```
https://app.test/warehouse/9c3a7b2e-1234-5678-abcd-ef0123456789
https://app.test/warehouse/images/logo
https://app.test/warehouse/images/logo?size=200x200
```

---

## Serving Files Programmatically

### Inline Response

```php
return $file->respond();           // serves inline with correct content type
return $file->respond($request);   // enables image resizing via query params
```

### Download Response

```php
return $file->download();                    // "filename.ext"
return $file->download('custom-name.pdf');   // override download name
```

### Generating URLs

```php
use Blax\Files\Services\WarehouseService;

// From a File model
$url = $file->url;                           // https://app.test/warehouse/{uuid}

// From a service
$url = WarehouseService::url($file);         // same as above
$url = WarehouseService::url($fileId);       // pass UUID string
```

---

## Image Resizing via Query Params

When serving images through the warehouse, you can request resized variants:

```
/warehouse/{id}?size=300x200
/warehouse/{id}?size=400x400&quality=90&webp=true
/warehouse/{id}?size=800x600&position=contain
```

See [Image Optimization](image-optimization.md) for all parameters.

---

## Access Control

By default, all files are served publicly. To restrict access:

### 1. Enable in config

```php
// config/files.php
'access_control' => [
    'enabled' => true,
],
```

### 2. Install laravel-roles

```bash
composer require blax-software/laravel-roles
```

### 3. Behavior

When enabled, the `WarehouseController`:

1. Checks if the request user is authenticated (403 if not)
2. Calls `$user->hasAccess($file)` via the `HasAccess` trait from laravel-roles (403 if denied)
3. Serves the file if access is granted

Access control only applies to persisted `File` records. Non-persisted asset-path lookups bypass the check.

---

## Disabling the Warehouse

```php
// config/files.php
'warehouse' => [
    'enabled' => false,
],
```

When disabled, no warehouse routes are registered. You can still serve files manually using `$file->respond()` or `$file->download()`.

---

## Customizing the Route

```php
// config/files.php
'warehouse' => [
    'enabled'    => true,
    'prefix'     => 'files',         // changes URL to /files/{id}
    'middleware' => ['web', 'auth'],  // add authentication
],
```

---

Next: [Image Optimization](image-optimization.md)
