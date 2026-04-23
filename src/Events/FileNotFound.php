<?php

namespace Blax\Files\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

/**
 * Fired by the warehouse controller when the requested identifier does not
 * resolve to a File (persisted or asset-backed). Fires immediately before the
 * 404 abort, so listeners can record the miss for analytics / tamper alerting.
 */
class FileNotFound
{
    use Dispatchable;

    public function __construct(
        public readonly ?string $identifier,
        public readonly Request $request,
    ) {}
}
