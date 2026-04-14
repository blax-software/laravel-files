<?php

namespace Blax\Files\Tests\Unit;

use Blax\Files\Enums\FileLinkType;
use Blax\Files\FilesServiceProvider;
use Blax\Files\Models\Filable;
use Blax\Files\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase;

class FileLinkTypeTest extends TestCase
{
    // ─── label() ───────────────────────────────────────────────────

    public function test_all_cases_have_labels()
    {
        foreach (FileLinkType::cases() as $case) {
            $label = $case->label();
            $this->assertNotEmpty($label);
            $this->assertIsString($label);
        }
    }

    public function test_specific_labels()
    {
        $this->assertEquals('Avatar', FileLinkType::Avatar->label());
        $this->assertEquals('Profile Image', FileLinkType::ProfileImage->label());
        $this->assertEquals('Cover Image', FileLinkType::CoverImage->label());
        $this->assertEquals('Other', FileLinkType::Other->label());
    }

    // ─── isImage() ─────────────────────────────────────────────────

    public function test_image_types_return_true()
    {
        $imageTypes = [
            FileLinkType::Avatar,
            FileLinkType::ProfileImage,
            FileLinkType::CoverImage,
            FileLinkType::Banner,
            FileLinkType::Background,
            FileLinkType::Logo,
            FileLinkType::Icon,
            FileLinkType::Thumbnail,
            FileLinkType::Gallery,
        ];

        foreach ($imageTypes as $type) {
            $this->assertTrue($type->isImage(), "{$type->value} should be an image type");
        }
    }

    public function test_non_image_types_return_false()
    {
        $nonImageTypes = [
            FileLinkType::Document,
            FileLinkType::Invoice,
            FileLinkType::Contract,
            FileLinkType::Certificate,
            FileLinkType::Report,
            FileLinkType::Video,
            FileLinkType::Audio,
            FileLinkType::Attachment,
            FileLinkType::Download,
            FileLinkType::Other,
        ];

        foreach ($nonImageTypes as $type) {
            $this->assertFalse($type->isImage(), "{$type->value} should NOT be an image type");
        }
    }

    // ─── tryFrom / from ────────────────────────────────────────────

    public function test_try_from_valid_value()
    {
        $this->assertEquals(FileLinkType::Avatar, FileLinkType::tryFrom('avatar'));
        $this->assertEquals(FileLinkType::Document, FileLinkType::tryFrom('document'));
    }

    public function test_try_from_invalid_value_returns_null()
    {
        $this->assertNull(FileLinkType::tryFrom('nonexistent'));
    }

    // ─── count ─────────────────────────────────────────────────────

    public function test_has_expected_number_of_cases()
    {
        $this->assertCount(19, FileLinkType::cases());
    }
}
