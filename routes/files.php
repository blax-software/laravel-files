<?php

use Blax\Files\Http\Controllers\FileUploadController;
use Blax\Files\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Warehouse (file serving)
|--------------------------------------------------------------------------
*/

if (config('files.warehouse.enabled', true)) {
    Route::middleware(config('files.warehouse.middleware', ['web']))
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
