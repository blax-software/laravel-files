<?php

namespace Blax\Files\Tests\Unit;

use Blax\Files\Enums\FileLinkType;
use Blax\Files\Models\Filable;
use Blax\Files\Models\File;
use Blax\Files\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class FilableModelTest extends TestCase
{

    // ─── scopeAs ───────────────────────────────────────────────────

    public function test_scope_as_filters_by_role()
    {
        $file = File::create([
            'name' => 'test',
            'extension' => 'png',
            'type' => 'image/png',
            'disk' => 'local',
        ]);

        Filable::create([
            'file_id' => $file->id,
            'filable_id' => 1,
            'filable_type' => 'App\Models\User',
            'as' => 'avatar',
            'order' => 0,
        ]);

        Filable::create([
            'file_id' => $file->id,
            'filable_id' => 1,
            'filable_type' => 'App\Models\User',
            'as' => 'banner',
            'order' => 0,
        ]);

        $avatars = Filable::as('avatar')->get();
        $this->assertCount(1, $avatars);
        $this->assertEquals('avatar', $avatars->first()->as);
    }

    // ─── set helpers ───────────────────────────────────────────────

    public function test_set_as_updates_in_memory()
    {
        $file = File::create([
            'name' => 'test',
            'extension' => 'png',
            'type' => 'image/png',
            'disk' => 'local',
        ]);

        $filable = Filable::create([
            'file_id' => $file->id,
            'filable_id' => 1,
            'filable_type' => 'App\Models\User',
            'as' => 'avatar',
            'order' => 0,
        ]);

        $filable->setAs('thumbnail', save: false);
        $this->assertEquals('thumbnail', $filable->as);
    }

    public function test_set_order_updates_in_memory()
    {
        $file = File::create([
            'name' => 'test',
            'extension' => 'png',
            'type' => 'image/png',
            'disk' => 'local',
        ]);

        $filable = Filable::create([
            'file_id' => $file->id,
            'filable_id' => 1,
            'filable_type' => 'App\Models\User',
            'as' => 'gallery',
            'order' => 0,
        ]);

        $filable->setOrder(5, save: false);
        $this->assertEquals(5, $filable->order);
    }

    // ─── getLinkType ───────────────────────────────────────────────

    public function test_get_link_type_returns_enum()
    {
        $file = File::create([
            'name' => 'test',
            'extension' => 'png',
            'type' => 'image/png',
            'disk' => 'local',
        ]);

        $filable = Filable::create([
            'file_id' => $file->id,
            'filable_id' => 1,
            'filable_type' => 'App\Models\User',
            'as' => 'avatar',
            'order' => 0,
        ]);

        $linkType = $filable->getLinkType();
        $this->assertEquals(FileLinkType::Avatar, $linkType);
    }

    public function test_get_link_type_returns_null_for_unknown()
    {
        $file = File::create([
            'name' => 'test',
            'extension' => 'png',
            'type' => 'image/png',
            'disk' => 'local',
        ]);

        $filable = Filable::create([
            'file_id' => $file->id,
            'filable_id' => 1,
            'filable_type' => 'App\Models\User',
            'as' => 'something_custom',
            'order' => 0,
        ]);

        $this->assertNull($filable->getLinkType());
    }

    // ─── meta cast ─────────────────────────────────────────────────

    public function test_meta_is_json_cast()
    {
        $file = File::create([
            'name' => 'test',
            'extension' => 'png',
            'type' => 'image/png',
            'disk' => 'local',
        ]);

        $filable = Filable::create([
            'file_id' => $file->id,
            'filable_id' => 1,
            'filable_type' => 'App\Models\User',
            'as' => 'gallery',
            'order' => 0,
            'meta' => ['width' => 800, 'height' => 600],
        ]);

        $refreshed = Filable::where('file_id', $file->id)->where('as', 'gallery')->first();
        $this->assertIsArray($refreshed->meta);
        $this->assertEquals(800, $refreshed->meta['width']);
    }

    // ─── global scope ordering ─────────────────────────────────────

    public function test_default_ordering_by_order_column()
    {
        // Three DISTINCT files attached to the same host as 'gallery'. They must
        // be distinct files so they don't collide on the real
        // (file_id, filable_type, filable_id, as) unique constraint; inserted
        // out of order to prove the global `ordered` scope sorts ascending.
        foreach ([3, 1, 2] as $order) {
            $file = File::create([
                'name' => 'test',
                'extension' => 'png',
                'type' => 'image/png',
                'disk' => 'local',
            ]);

            Filable::create([
                'file_id' => $file->id,
                'filable_id' => 1,
                'filable_type' => 'App\Models\User',
                'as' => 'gallery',
                'order' => $order,
            ]);
        }

        $filables = Filable::where('filable_type', 'App\Models\User')->get();
        $this->assertEquals(1, $filables[0]->order);
        $this->assertEquals(2, $filables[1]->order);
        $this->assertEquals(3, $filables[2]->order);
    }

    // ─── file relationship ─────────────────────────────────────────

    public function test_filable_belongs_to_file()
    {
        $file = File::create([
            'name' => 'linked',
            'extension' => 'pdf',
            'type' => 'application/pdf',
            'disk' => 'local',
        ]);

        $filable = Filable::create([
            'file_id' => $file->id,
            'filable_id' => 1,
            'filable_type' => 'App\Models\User',
            'as' => 'document',
            'order' => 0,
        ]);

        $this->assertEquals($file->id, $filable->file->id);
    }
}
