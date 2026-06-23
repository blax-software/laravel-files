<?php

namespace Blax\Files\Http\Controllers;

use Blax\Files\Events\FileAccessed;
use Blax\Files\Events\FileNotFound;
use Blax\Files\Http\Middleware\FileAccessControl;
use Blax\Files\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WarehouseController extends Controller
{
    public function __invoke(Request $request, ?string $identifier = null)
    {
        $identifier ??= $request->get('id');

        // Reuse the file the access-control middleware already resolved (and
        // authorized) when it ran, to avoid a second lookup. Falls back to a
        // fresh search when access control is disabled (middleware no-ops).
        $file = $request->attributes->has(FileAccessControl::ATTRIBUTE)
            ? $request->attributes->get(FileAccessControl::ATTRIBUTE)
            : WarehouseService::searchFile($request, $identifier);

        if (! $file) {
            FileNotFound::dispatch($identifier, $request);
            abort(404);
        }

        FileAccessed::dispatch($file, $request);

        return $file->respond($request);
    }
}
