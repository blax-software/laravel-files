<?php

namespace Blax\Files\Http\Controllers;

use Blax\Files\Events\FileAccessed;
use Blax\Files\Events\FileNotFound;
use Blax\Files\Models\File;
use Blax\Files\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WarehouseController extends Controller
{
    public function __invoke(Request $request, ?string $identifier = null)
    {
        $identifier ??= $request->get('id');

        $file = WarehouseService::searchFile($request, $identifier);

        if (! $file) {
            FileNotFound::dispatch($identifier, $request);
            abort(404);
        }

        // Access control check (optional, via laravel-roles)
        if (config('files.access_control.enabled') && $file->exists) {
            $this->checkAccess($request, $file);
        }

        FileAccessed::dispatch($file, $request);

        return $file->respond($request);
    }

    protected function checkAccess(Request $request, File $file): void
    {
        // If laravel-roles is installed, check HasAccess
        if (
            trait_exists(\Blax\Roles\Traits\HasAccess::class)
            && method_exists($file, 'hasAccess')
        ) {
            $user = $request->user();

            if (! $user) {
                abort(403, 'Authentication required.');
            }

            if (! $user->hasAccess($file)) {
                abort(403, 'Access denied.');
            }
        }
    }
}
