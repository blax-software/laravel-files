<?php

namespace Blax\Files\Tests\Unit;

use Blax\Files\Contracts\ResolvesWarehouseFiles;
use Blax\Files\Http\Middleware\FileAccessControl;
use Blax\Files\Models\File;
use Blax\Files\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FileAccessControlTest extends TestCase
{
    /** A file whose access is gated: only user #1 (or any user, see flag) may read. */
    protected function gatedFile(bool $ownerOnly = true): File
    {
        $file = new class extends File
        {
            public bool $ownerOnly = true;

            public function canBeAccessedBy(?Authenticatable $user): bool
            {
                if (! $this->ownerOnly) {
                    return $user !== null; // authenticated-level
                }

                return $user !== null && (int) $user->getAuthIdentifier() === 1; // owner-level
            }
        };
        $file->ownerOnly = $ownerOnly;
        $file->exists = true; // simulate a persisted file without touching the DB

        return $file;
    }

    protected function withResolver(File $file): void
    {
        config(['files.warehouse.resolver' => new class($file) implements ResolvesWarehouseFiles
        {
            public function __construct(private File $file) {}

            public function resolve(Request $request): ?Model
            {
                return $this->file;
            }
        }]);
    }

    protected function pass(FileAccessControl $mw, Request $request): mixed
    {
        return $mw->handle($request, fn ($req) => response('served'));
    }

    public function test_disabled_serves_everything_without_checking(): void
    {
        config(['files.access_control.enabled' => false]);
        // Resolver returns a file that would deny everyone; must be ignored.
        $this->withResolver($this->gatedFile());

        $response = $this->pass(new FileAccessControl, Request::create('/warehouse/x'));

        $this->assertEquals('served', $response->getContent());
    }

    public function test_enabled_serves_public_base_file(): void
    {
        config(['files.access_control.enabled' => true]);
        $public = new File(['name' => 'pub']);
        $public->exists = true; // base File::canBeAccessedBy() returns true
        $this->withResolver($public);

        $response = $this->pass(new FileAccessControl, Request::create('/warehouse/x'));

        $this->assertEquals('served', $response->getContent());
    }

    public function test_enabled_403s_guest_on_gated_file(): void
    {
        config(['files.access_control.enabled' => true]);
        $this->withResolver($this->gatedFile());

        try {
            $this->pass(new FileAccessControl, Request::create('/warehouse/x'));
            $this->fail('Expected a 403 HttpException for a guest on a gated file.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    public function test_enabled_allows_owner_on_gated_file(): void
    {
        config(['files.access_control.enabled' => true]);
        $this->withResolver($this->gatedFile());

        $request = Request::create('/warehouse/x');
        $request->setUserResolver(fn () => new class extends \Illuminate\Foundation\Auth\User
        {
            public function getAuthIdentifier()
            {
                return 1;
            }
        });

        $response = $this->pass(new FileAccessControl, $request);

        $this->assertEquals('served', $response->getContent());
    }

    public function test_enabled_403s_non_owner_on_owner_only_file(): void
    {
        config(['files.access_control.enabled' => true]);
        $this->withResolver($this->gatedFile(ownerOnly: true));

        $request = Request::create('/warehouse/x');
        $request->setUserResolver(fn () => new class extends \Illuminate\Foundation\Auth\User
        {
            public function getAuthIdentifier()
            {
                return 999;
            }
        });

        try {
            $this->pass(new FileAccessControl, $request);
            $this->fail('Expected a 403 for a non-owner user on an owner-only file.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    public function test_resolved_file_is_stashed_on_request(): void
    {
        config(['files.access_control.enabled' => true]);
        $public = new File(['name' => 'pub']);
        $public->exists = true;
        $this->withResolver($public);

        $request = Request::create('/warehouse/x');
        $this->pass(new FileAccessControl, $request);

        $this->assertTrue($request->attributes->has(FileAccessControl::ATTRIBUTE));
        $this->assertSame($public, $request->attributes->get(FileAccessControl::ATTRIBUTE));
    }
}
