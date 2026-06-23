<?php

use Blax\Files\Http\Controllers\FileUploadController;
use Blax\Files\Http\Controllers\WarehouseController;
use Blax\Files\Http\Middleware\FileAccessControl;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Warehouse (file serving)
|--------------------------------------------------------------------------
*/

if (config('files.warehouse.enabled', true)) {
    // The access-control middleware is always attached; it no-ops unless
    // `files.access_control.enabled` is true, so this stays a public file
    // server by default while letting consumers opt into per-file guards.
    $warehouseMiddleware = array_merge(
        (array) config('files.warehouse.middleware', ['web']),
        [FileAccessControl::class],
    );

    Route::middleware($warehouseMiddleware)
        ->get(config('files.warehouse.prefix', 'warehouse') . '/{identifier?}', WarehouseController::class)
        ->name('files.warehouse')
        ->where('identifier', '[\/\w\.\-\=&@]*');
}

/*
|--------------------------------------------------------------------------
| File Upload API
|--------------------------------------------------------------------------
*/

Route::prefix(config('files.upload.route_prefix', 'api/files'))
    ->middleware(config('files.upload.middleware', ['api', 'auth:sanctum']))
    ->group(function () {
        Route::post('upload', [FileUploadController::class, 'upload'])->name('files.upload');
        Route::post('chunk/init', [FileUploadController::class, 'chunkInit'])->name('files.chunk.init');
        Route::post('chunk/upload', [FileUploadController::class, 'chunkUpload'])->name('files.chunk.upload');
    });
