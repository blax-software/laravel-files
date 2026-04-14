# Uploading Files

The package provides a complete upload API with support for single-file uploads and chunked uploads for large files.

## Single File Upload

### Endpoint

```
POST /api/files/upload
```

**Middleware:** `api`, `auth:sanctum` (configurable)

### Request

Send a multipart form upload with a `file` field:

```bash
curl -X POST https://app.test/api/files/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@photo.jpg"
```

### Validation

- **Max size:** 50 MB (configurable via `files.upload.max_size`)
- **Allowed MIME types:** all by default; restrict via `files.upload.allowed_mimes`

```php
// config/files.php
'upload' => [
    'max_size'      => 50 * 1024, // KB
    'allowed_mimes' => ['jpg', 'png', 'pdf', 'docx'], // empty = allow all
],
```

### Response (201)

```json
{
    "id": "9c3a...",
    "name": "photo",
    "type": "image/jpeg",
    "extension": "jpg",
    "size": 245760,
    "size_human": "240 KB",
    "url": "https://app.test/warehouse/9c3a..."
}
```

---

## Chunked Upload

For large files that exceed browser or server limits, use the chunked upload flow.

### Step 1: Initialize

```
POST /api/files/chunk/init
```

**Body (JSON):**

```json
{
    "filename": "video.mp4",
    "filesize": 104857600,
    "total_chunks": 100,
    "mime_type": "video/mp4",
    "extension": "mp4"
}
```

**Response (201):**

```json
{
    "upload_id": "9c3a...",
    "file_id": "9c3a...",
    "total_chunks": 100
}
```

### Step 2: Upload Chunks

```
POST /api/files/chunk/upload
```

Send each chunk as a multipart upload or raw body:

```bash
curl -X POST https://app.test/api/files/chunk/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F "upload_id=9c3a..." \
  -F "chunk_index=0" \
  -F "chunk=@chunk_0.bin"
```

**Response:**

```json
{
    "upload_id": "9c3a...",
    "chunk_index": 0,
    "received": 1,
    "total_chunks": 100,
    "complete": false
}
```

When the last chunk is received (`complete: true`), all chunks are automatically assembled into the final file.

### Step 3: Done

No finalization call needed. The file is ready to use once `complete` is `true`. The temporary chunk files are cleaned up automatically.

---

## Upload Sessions

Chunk upload sessions are stored in the application cache and expire after **24 hours**. If a session expires before all chunks are received, the upload must be restarted.

---

## Real-Time Progress

If [laravel-websockets](https://github.com/blax-software/laravel-websockets) is installed, a `ChunkUploadProgress` event is broadcast after each chunk:

```php
// Event payload
[
    'uploadId'   => '9c3a...',
    'chunkIndex' => 42,
    'totalChunks' => 100,
    'complete'   => false,
]
```

Listen on the client side to show upload progress bars.

---

## Upload via the HasFiles Trait

When working with models that use `HasFiles`, you can upload and attach in a single call:

```php
// From a form upload
$file = $product->uploadFile(
    $request->file('photo'),
    as: FileLinkType::Gallery,
);

// From raw content
$file = $product->uploadFileFromContents(
    contents: $csvData,
    name: 'export',
    extension: 'csv',
    as: FileLinkType::Document,
);

// From a URL
$file = $product->uploadFileFromUrl(
    url: 'https://cdn.example.com/image.jpg',
    as: FileLinkType::Banner,
    replace: true,
);
```

See [Attaching Files](attaching-files.md#upload-helpers) for full parameter details.

---

## Route Configuration

Customize upload route prefix and middleware in `config/files.php`:

```php
'upload' => [
    'route_prefix' => 'api/files',
    'middleware'    => ['api', 'auth:sanctum'],
],
```

**Routes registered:**

| Method | URI                     | Name                 |
|--------|-------------------------|----------------------|
| `POST` | `{prefix}/upload`       | `files.upload`       |
| `POST` | `{prefix}/chunk/init`   | `files.chunk.init`   |
| `POST` | `{prefix}/chunk/upload` | `files.chunk.upload` |

---

Next: [Serving Files](serving-files.md)
