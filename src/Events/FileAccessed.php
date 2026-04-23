<?php

namespace Blax\Files\Events;

use Blax\Files\Models\File;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

/**
 * Fired by the warehouse controller after a file is resolved and about to be
 * served. Listen for side-effects that need the resolved File model —
 * access logs, analytics, tracking pixels, etc.
 */
class FileAccessed
{
    use Dispatchable;

    public function __construct(
        public readonly File $file,
        public readonly Request $request,
    ) {}
}
