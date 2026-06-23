<?php

namespace Blax\Files\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves the warehouse file for an incoming request.
 *
 * A host application binds an implementation at `files.warehouse.resolver`
 * so the access-control middleware can resolve files using the app's own
 * lookup flow (encrypted ids, client/server assets, raw paths, …) without
 * the package needing to know about it. Returning null means "not found".
 */
interface ResolvesWarehouseFiles
{
    public function resolve(Request $request): ?Model;
}
