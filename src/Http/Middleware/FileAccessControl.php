<?php

namespace Blax\Files\Http\Middleware;

use Blax\Files\Contracts\ResolvesWarehouseFiles;
use Blax\Files\Services\WarehouseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve-time access control for warehouse files.
 *
 * When `files.access_control.enabled` is true, this middleware resolves the
 * file for the incoming warehouse request and aborts with 403 unless the
 * current user may access it — as decided by the File model's
 * `canBeAccessedBy()` hook (which defaults to "public" on the base model, so
 * existing consumers are unaffected until they override it).
 *
 * The resolved file is stashed on the request as `files.warehouse_file` so
 * the downstream controller can reuse it instead of resolving a second time.
 *
 * File resolution is delegated to the resolver configured at
 * `files.warehouse.resolver` (a class implementing {@see ResolvesWarehouseFiles}
 * or any invokable), falling back to the package WarehouseService. This lets a
 * host app layer in its own lookup without the package depending on it.
 */
class FileAccessControl
{
    public const ATTRIBUTE = 'files.warehouse_file';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('files.access_control.enabled')) {
            return $next($request);
        }

        $file = $this->resolveFile($request);

        // Always set the attribute when enabled so the controller can tell
        // "middleware ran and resolved null (404)" from "middleware did not run".
        $request->attributes->set(self::ATTRIBUTE, $file);

        if ($file && $file->exists && method_exists($file, 'canBeAccessedBy')) {
            if (! $file->canBeAccessedBy($request->user())) {
                abort(403, 'You are not allowed to access this file.');
            }
        }

        return $next($request);
    }

    protected function resolveFile(Request $request)
    {
        $resolver = config('files.warehouse.resolver');

        if ($resolver) {
            $instance = is_string($resolver) ? app($resolver) : $resolver;

            if ($instance instanceof ResolvesWarehouseFiles) {
                return $instance->resolve($request);
            }

            if (is_callable($instance)) {
                return $instance($request);
            }
        }

        return WarehouseService::searchFile(
            $request,
            $request->route('identifier') ?? $request->route('encrypted_id') ?? $request->get('id'),
        );
    }
}
