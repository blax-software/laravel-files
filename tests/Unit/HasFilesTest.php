<?php

namespace Blax\Files\Tests\Unit;

use Blax\Files\Enums\FileLinkType;
use Blax\Files\FilesServiceProvider;
use Blax\Files\Models\Filable;
use Blax\Files\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase;
use Workbench\App\Models\Article;
use Workbench\App\Models\User;

class HasFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [FilesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../workbench/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ─── files() relationship ──────────────────────────────────────

    public function test_files_returns_morph_to_many()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\MorphToMany::class,
            $user->files(),
        );
    }

    public function test_files_returns_empty_collection_by_default()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $this->assertCount(0, $user->files);
    }

    // ─── attachFile ────────────────────────────────────────────────

    public function test_attach_file_creates_pivot_entry()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'photo']);

        $user->attachFile($file, 'avatar');

        $this->assertDatabaseHas('filables', [
            'file_id' => $file->id,
            'filable_id' => $user->id,
            'filable_type' => User::class,
            'as' => 'avatar',
        ]);
    }

    public function test_attach_file_accepts_file_id_string()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'photo']);

        $user->attachFile($file->id, 'avatar');

        $this->assertDatabaseHas('filables', [
            'file_id' => $file->id,
            'filable_id' => $user->id,
        ]);
    }

    public function test_attach_file_accepts_enum()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'photo']);

        $user->attachFile($file, FileLinkType::Avatar);

        $this->assertDatabaseHas('filables', [
            'file_id' => $file->id,
            'as' => 'avatar',
        ]);
    }

    public function test_attach_file_with_order()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'photo']);

        $user->attachFile($file, 'gallery', order: 5);

        $pivot = $user->getFilePivot($file);
        $this->assertEquals(5, $pivot->order);
    }

    public function test_attach_file_with_meta()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'photo']);

        $user->attachFile($file, 'gallery', meta: ['caption' => 'Nice view']);

        $pivot = $user->getFilePivot($file);
        $decoded = json_decode($pivot->meta, true);
        $this->assertEquals('Nice view', $decoded['caption']);
    }

    public function test_attach_file_prevents_duplicate_pivot()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'photo']);

        $user->attachFile($file, 'avatar');
        $user->attachFile($file, 'avatar'); // duplicate

        $this->assertCount(1, $user->files()->where('file_id', $file->id)->get());
    }

    public function test_attach_file_allows_same_file_with_different_role()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'photo']);

        $user->attachFile($file, 'avatar');
        $user->attachFile($file, 'thumbnail');

        $this->assertCount(2, $user->files);
    }

    public function test_attach_file_replace_removes_previous_attachment()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $old = File::create(['name' => 'old']);
        $new = File::create(['name' => 'new']);

        $user->attachFile($old, FileLinkType::Avatar);
        $user->attachFile($new, FileLinkType::Avatar, replace: true);

        $user->load('files');
        $avatars = $user->filesAs(FileLinkType::Avatar)->get();

        $this->assertCount(1, $avatars);
        $this->assertEquals($new->id, $avatars->first()->id);
    }

    // ─── detachFile ────────────────────────────────────────────────

    public function test_detach_file_removes_pivot_entry()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'photo']);

        $user->attachFile($file, 'avatar');
        $user->detachFile($file);

        $this->assertDatabaseMissing('filables', [
            'file_id' => $file->id,
            'filable_id' => $user->id,
        ]);
    }

    public function test_detach_file_scoped_by_as()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'multipurpose']);

        $user->attachFile($file, 'avatar');
        $user->attachFile($file, 'thumbnail');

        $user->detachFile($file, 'avatar');

        $this->assertDatabaseMissing('filables', ['file_id' => $file->id, 'as' => 'avatar']);
        $this->assertDatabaseHas('filables', ['file_id' => $file->id, 'as' => 'thumbnail']);
    }

    // ─── detachFilesAs ─────────────────────────────────────────────

    public function test_detach_files_as_removes_all_files_with_role()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $f1 = File::create(['name' => 'a']);
        $f2 = File::create(['name' => 'b']);
        $f3 = File::create(['name' => 'c']);

        $user->attachFile($f1, 'gallery');
        $user->attachFile($f2, 'gallery');
        $user->attachFile($f3, 'avatar');

        $user->detachFilesAs('gallery');

        $this->assertDatabaseMissing('filables', ['as' => 'gallery', 'filable_id' => $user->id]);
        $this->assertDatabaseHas('filables', ['as' => 'avatar', 'filable_id' => $user->id]);
    }

    // ─── detachAllFiles ────────────────────────────────────────────

    public function test_detach_all_files()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $f1 = File::create(['name' => 'a']);
        $f2 = File::create(['name' => 'b']);

        $user->attachFile($f1, 'avatar');
        $user->attachFile($f2, 'banner');

        $user->detachAllFiles();

        $this->assertCount(0, $user->files()->get());
    }

    // ─── filesAs / fileAs ──────────────────────────────────────────

    public function test_files_as_returns_only_matching_role()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $avatar = File::create(['name' => 'avatar']);
        $banner = File::create(['name' => 'banner']);

        $user->attachFile($avatar, 'avatar');
        $user->attachFile($banner, 'banner');

        $avatars = $user->filesAs('avatar')->get();

        $this->assertCount(1, $avatars);
        $this->assertEquals($avatar->id, $avatars->first()->id);
    }

    public function test_file_as_returns_single_file()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'avatar']);
        $user->attachFile($file, FileLinkType::Avatar);

        $result = $user->fileAs(FileLinkType::Avatar);

        $this->assertNotNull($result);
        $this->assertEquals($file->id, $result->id);
    }

    public function test_file_as_returns_null_when_none_attached()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $this->assertNull($user->fileAs('avatar'));
    }

    // ─── Convenience getters ───────────────────────────────────────

    public function test_get_avatar_returns_avatar_file()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'face']);
        $user->attachFile($file, FileLinkType::Avatar);

        $this->assertEquals($file->id, $user->getAvatar()->id);
    }

    public function test_get_avatar_falls_back_to_profile_image()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'face']);
        $user->attachFile($file, FileLinkType::ProfileImage);

        $this->assertEquals($file->id, $user->getAvatar()->id);
    }

    public function test_get_thumbnail_returns_thumbnail()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'thumb']);
        $user->attachFile($file, FileLinkType::Thumbnail);

        $this->assertEquals($file->id, $user->getThumbnail()->id);
    }

    public function test_get_banner_returns_banner()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'banner-img']);
        $user->attachFile($file, FileLinkType::Banner);

        $this->assertEquals($file->id, $user->getBanner()->id);
    }

    public function test_get_cover_image_returns_cover()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'cover']);
        $user->attachFile($file, FileLinkType::CoverImage);

        $this->assertEquals($file->id, $user->getCoverImage()->id);
    }

    public function test_get_background_returns_background()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'bg']);
        $user->attachFile($file, FileLinkType::Background);

        $this->assertEquals($file->id, $user->getBackground()->id);
    }

    public function test_get_logo_returns_logo()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'logo']);
        $user->attachFile($file, FileLinkType::Logo);

        $this->assertEquals($file->id, $user->getLogo()->id);
    }

    public function test_get_gallery_returns_multiple_files()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $f1 = File::create(['name' => 'g1']);
        $f2 = File::create(['name' => 'g2']);
        $f3 = File::create(['name' => 'g3']);

        $user->attachFile($f1, FileLinkType::Gallery, order: 0);
        $user->attachFile($f2, FileLinkType::Gallery, order: 1);
        $user->attachFile($f3, FileLinkType::Gallery, order: 2);

        $gallery = $user->getGallery();

        $this->assertCount(3, $gallery);
    }

    // ─── Polymorphism (multiple models) ────────────────────────────

    public function test_same_file_attached_to_different_models()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $article = Article::create(['title' => 'My Post']);
        $file = File::create(['name' => 'shared']);

        $user->attachFile($file, 'avatar');
        $article->attachFile($file, 'thumbnail');

        $this->assertCount(1, $user->files);
        $this->assertCount(1, $article->files);
        $this->assertCount(2, $file->filables);
    }

    public function test_different_models_files_are_independent()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $article = Article::create(['title' => 'Post']);

        $userFile = File::create(['name' => 'user-pic']);
        $articleFile = File::create(['name' => 'article-pic']);

        $user->attachFile($userFile, 'avatar');
        $article->attachFile($articleFile, 'thumbnail');

        $user->detachAllFiles();

        $this->assertCount(0, $user->files()->get());
        $this->assertCount(1, $article->files()->get());
    }

    // ─── uploadFile ────────────────────────────────────────────────

    public function test_upload_file_creates_and_attaches()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $upload = \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $file = $user->uploadFile($upload, FileLinkType::Document);

        $this->assertNotNull($file->id);
        $this->assertTrue($file->hasContents());
        $this->assertEquals('doc', $file->name);
        $this->assertCount(1, $user->files()->get());
        $this->assertDatabaseHas('filables', [
            'file_id' => $file->id,
            'as' => 'document',
        ]);
    }

    public function test_upload_file_with_replace()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $u1 = \Illuminate\Http\UploadedFile::fake()->create('old.jpg', 50, 'image/jpeg');
        $u2 = \Illuminate\Http\UploadedFile::fake()->create('new.jpg', 50, 'image/jpeg');

        $user->uploadFile($u1, FileLinkType::Avatar);
        $newFile = $user->uploadFile($u2, FileLinkType::Avatar, replace: true);

        $this->assertCount(1, $user->filesAs(FileLinkType::Avatar)->get());
        $this->assertEquals($newFile->id, $user->getAvatar()->id);
    }

    // ─── uploadFileFromContents ────────────────────────────────────

    public function test_upload_file_from_contents()
    {
        $article = Article::create(['title' => 'Post']);

        $file = $article->uploadFileFromContents(
            'raw content here',
            name: 'readme',
            extension: 'txt',
            as: FileLinkType::Attachment,
        );

        $this->assertEquals('readme', $file->name);
        $this->assertEquals('txt', $file->extension);
        $this->assertTrue($file->hasContents());
        $this->assertEquals('raw content here', $file->getContents());
    }

    // ─── getFilePivot ──────────────────────────────────────────────

    public function test_get_file_pivot_returns_pivot()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'photo']);
        $user->attachFile($file, 'avatar', order: 3);

        $pivot = $user->getFilePivot($file);

        $this->assertNotNull($pivot);
        $this->assertEquals('avatar', $pivot->as);
        $this->assertEquals(3, $pivot->order);
    }

    public function test_get_file_pivot_returns_null_when_not_attached()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $file = File::create(['name' => 'orphan']);

        $this->assertNull($user->getFilePivot($file));
    }

    // ─── reorderFiles ──────────────────────────────────────────────

    public function test_reorder_files_sets_order()
    {
        $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $f1 = File::create(['name' => 'a']);
        $f2 = File::create(['name' => 'b']);
        $f3 = File::create(['name' => 'c']);

        $user->attachFile($f1, 'gallery');
        $user->attachFile($f2, 'gallery');
        $user->attachFile($f3, 'gallery');

        // Reorder: c, a, b
        $user->reorderFiles([$f3->id, $f1->id, $f2->id], 'gallery');

        $reloaded = $user->filesAs('gallery')->orderByPivot('order')->get();

        $this->assertEquals($f3->id, $reloaded[0]->id);
        $this->assertEquals($f1->id, $reloaded[1]->id);
        $this->assertEquals($f2->id, $reloaded[2]->id);
    }
}
