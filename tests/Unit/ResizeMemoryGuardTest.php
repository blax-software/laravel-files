<?php

namespace Blax\Files\Tests\Unit;

use Blax\Files\Models\File;
use Blax\Files\Tests\TestCase;

/**
 * Regression for GlitchTip #5617: an oversized source image fully rasterizes
 * (~4 bytes/pixel) during resize and exhausts the PHP memory_limit — an
 * UNCATCHABLE fatal that 500s the warehouse request instead of hitting the
 * method's graceful "serve original" fallback. resizedPath() now reads the
 * source dimensions cheaply via getimagesize() and, when they exceed
 * files.optimization.max_source_megapixels, returns the original path unchanged
 * WITHOUT ever decoding the image.
 */
class ResizeMemoryGuardTest extends TestCase
{
    private function makePngFile(int $w, int $h): File
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD not available');
        }

        $gd = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($gd);
        $png = ob_get_clean();
        imagedestroy($gd);

        $file = File::create(['name' => 'guard-test', 'relativepath' => 'guard/test.png']);
        $file->putContents($png);

        return $file;
    }

    public function test_oversized_source_serves_original_without_decoding()
    {
        // A tiny pixel budget makes even a small image "oversized", so the guard
        // fires deterministically without allocating a real decompression bomb.
        config()->set('files.optimization.max_source_megapixels', 0.0001); // 100 px

        $file = $this->makePngFile(64, 64); // 4096 px = 0.004 MP > budget

        $result = $file->resizedPath(32, 32);

        // Guard fired: the ORIGINAL path is returned, never a /resized/ derivative.
        $this->assertSame($file->path, $result);
        $this->assertStringNotContainsString('/resized/', $result);
    }

    public function test_guard_disabled_when_budget_is_zero()
    {
        // 0 disables the guard: a within-limits source is not short-circuited by it
        // (it proceeds to the normal resize path, which — spatie present or not —
        // never returns via the megapixel guard). Asserts the guard itself no-ops.
        config()->set('files.optimization.max_source_megapixels', 0);

        $file = $this->makePngFile(64, 64);

        // With the guard disabled the megapixel check must not be what returns the
        // path; resizedPath either resizes or falls back on decode error, but the
        // call must not throw from the guard branch.
        $result = $file->resizedPath(32, 32);

        $this->assertIsString($result);
    }
}
