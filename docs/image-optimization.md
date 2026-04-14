# Image Optimization

The package provides on-the-fly image resizing with caching, WebP conversion, and configurable quality. Requires `spatie/image`.

## Requirements

```bash
composer require spatie/image "^3.8"
```

If `spatie/image` is not installed, all resizing calls gracefully return the original file path.

---

## Resizing via URL

When serving images through the [Warehouse](serving-files.md), append query parameters:

```
/warehouse/{id}?size=300x200
/warehouse/{id}?size=400&quality=90
/warehouse/{id}?size=800x600&webp=false&position=contain
```

### Query Parameters

| Param      | Type     | Default | Description                                                                                 |
|------------|----------|---------|---------------------------------------------------------------------------------------------|
| `size`     | `string` | —       | Dimensions as `WIDTHxHEIGHT` (e.g. `300x200`). Use a single number for square (e.g. `300`). |
| `quality`  | `int`    | `85`    | JPEG/WebP quality (1–100)                                                                   |
| `webp`     | `bool`   | `true`  | Convert output to WebP                                                                      |
| `position` | `string` | `cover` | Fit mode: `cover`, `contain`, `fill`, `max`, `stretch`                                      |
| `cached`   | `bool`   | `true`  | Use cached resize if available                                                              |
| `rounding` | `bool`   | `true`  | Round dimensions to nearest step                                                            |

---

## Resizing Programmatically

Use the `resizedPath()` method to generate a resized variant:

```php
$path = $file->resizedPath(
    width: 300,
    height: 200,
    toWebp: true,
    quality: 85,
    position: 'cover',
);
```

### Parameters

| Parameter   | Type                | Default   | Description                                   |
|-------------|---------------------|-----------|-----------------------------------------------|
| `$width`    | `string\|int\|null` | —         | Target width. Use `'auto'` for proportional.  |
| `$height`   | `string\|int\|null` | —         | Target height. Use `'auto'` for proportional. |
| `$rounding` | `bool`              | `true`    | Round to nearest step (default: 50px)         |
| `$toWebp`   | `bool`              | `true`    | Convert to WebP format                        |
| `$cached`   | `bool`              | `true`    | Return cached version if available            |
| `$quality`  | `?int`              | `null`    | Quality (1–100). `null` uses config default.  |
| `$position` | `string`            | `'cover'` | Fit mode                                      |

The method returns an absolute file path to the resized image.

---

## Fit Modes

| Mode      | Behavior                                       |
|-----------|------------------------------------------------|
| `cover`   | Crop to fill exact dimensions (default)        |
| `contain` | Fit within dimensions, preserving aspect ratio |
| `fill`    | Fill dimensions, padding if necessary          |
| `max`     | Resize within dimensions, no upscaling         |
| `stretch` | Stretch to exact dimensions                    |

---

## Caching

Resized images are cached on disk in a `resized/` subdirectory next to the original:

```
files/2024/06/15/abc-123            ← original
files/2024/06/15/resized/
    abc-123.300x200.jpg.webp        ← resized variant
    abc-123.800x600.contain.q90.jpg ← another variant
```

Cache key components: `{width}x{height}`, `.{position}` (if not cover), `.q{quality}` (if not default). Requesting the same size again serves from cache instantly.

When a file is deleted, all resized variants are cleaned up automatically.

---

## Dimension Rounding

To reduce cache fragmentation, dimensions are rounded **up** to the nearest step (default: 50px):

| Requested | Rounded to |
|-----------|------------|
| `280x180` | `300x200`  |
| `310x310` | `350x350`  |
| `50x50`   | `50x50`    |

Configure the step size:

```php
// config/files.php
'optimization' => [
    'round_to' => 100, // round to nearest 100px
],
```

Disable rounding per request with `?rounding=false` or `rounding: false`.

---

## Skipped Formats

Some formats cannot be processed by image libraries and are served as-is:

```php
'optimization' => [
    'skip_formats' => ['gif', 'svg', 'svg+xml'],
],
```

---

## Preferred Extensions (Asset Auto-Resolution)

When the warehouse receives a path without an extension (e.g. `/warehouse/images/logo`), it tries these extensions in order:

```php
'optimization' => [
    'preferred_extensions' => ['svg', 'webp', 'png', 'jpg', 'jpeg'],
],
```

---

## Configuration Reference

All optimization settings in `config/files.php`:

```php
'optimization' => [
    'enabled'              => true,
    'default_quality'      => 85,       // default JPEG/WebP quality
    'webp_conversion'      => true,     // convert to WebP by default
    'round_dimensions'     => true,     // round sizes to reduce cache variants
    'round_to'             => 50,       // rounding step in pixels
    'skip_formats'         => ['gif', 'svg', 'svg+xml'],
    'preferred_extensions' => ['svg', 'webp', 'png', 'jpg', 'jpeg'],
],
```

---

Next: [Configuration](configuration.md)
