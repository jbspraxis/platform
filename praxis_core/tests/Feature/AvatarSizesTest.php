<?php

namespace Tests\Feature;

use App\Support\Avatars;
use App\Support\Hosts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * People's photos after the performance pass (11 Nov 2026): lists and the top bar draw them at 22 to 36 px, so they
 * ask for a small copy, made from the stored 512 px photo the first time it is asked for and kept next to it. Both
 * are sent from the disk as files the browser keeps; the 512 px photo stays the original.
 */
class AvatarSizesTest extends TestCase
{
    use UsesThrowawayDatabase;

    public function test_a_small_copy_is_made_once_from_the_photo_and_both_are_sent_as_files(): void
    {
        Storage::fake('local');
        $me = $this->staff('eng', ['engineer']);
        Avatars::store($me, UploadedFile::fake()->image('me.png', 900, 600));
        $me->refresh();
        $disk = Storage::disk('local');
        $small = preg_replace('/\.jpg$/', '-' . Avatars::SMALL . '.jpg', $me->avatar_path);
        $disk->assertMissing($small);

        // Lists and the top bar ask for the small copy; a large drawing gets the photo itself
        $html = Blade::render('<x-hq.avatar :user="$u" :size="24" /><x-hq.avatar :user="$u" :size="200" />', ['u' => $me]);
        $this->assertStringContainsString('s=' . Avatars::SMALL, $html);
        $this->assertSame(1, substr_count($html, 's=' . Avatars::SMALL));
        $url = $me->avatarUrl() . '&s=' . Avatars::SMALL;

        $first = $this->actingAs($me)->get('https://' . Hosts::hq() . $url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertInstanceOf(BinaryFileResponse::class, $first->baseResponse);
        $this->assertStringContainsString('private', $first->headers->get('Cache-Control'));
        $this->assertNotNull($first->headers->get('Last-Modified'));
        $disk->assertExists($small);
        $this->assertSame([Avatars::SMALL, Avatars::SMALL], array_slice(getimagesizefromstring($disk->get($small)), 0, 2));
        $this->assertSame([Avatars::SIZE, Avatars::SIZE], array_slice(getimagesizefromstring($disk->get($me->avatar_path)), 0, 2), 'The photo itself is kept');

        // Asked again: the same copy (not made again), and a browser that has it gets "not modified"
        $made = $disk->lastModified($small);
        $this->get('https://' . Hosts::hq() . $url, ['If-Modified-Since' => $first->headers->get('Last-Modified')])->assertStatus(304);
        $this->assertSame($made, $disk->lastModified($small));

        // Any other size is the photo itself, never a new file
        $this->get('https://' . Hosts::hq() . $me->avatarUrl() . '&s=9999')->assertOk();
        $this->assertCount(2, $disk->files('avatars'));

        // A new photo, or removing it, takes its small copy with it
        Avatars::store($me, UploadedFile::fake()->image('new.jpg', 600, 600));
        $disk->assertMissing($small);
        $me->refresh();
        $this->get('https://' . Hosts::hq() . $me->avatarUrl() . '&s=' . Avatars::SMALL)->assertOk();
        $this->assertCount(2, $disk->files('avatars'));
        Avatars::remove($me);
        $this->assertSame([], $disk->files('avatars'));
    }

    public function test_photos_are_for_signed_in_staff_only(): void
    {
        Storage::fake('local');
        $me = $this->staff('eng', ['engineer']);
        Avatars::store($me, UploadedFile::fake()->image('me.png', 300, 300));

        $this->get('https://' . Hosts::hq() . $me->fresh()->avatarUrl() . '&s=' . Avatars::SMALL)->assertRedirect();
        Storage::disk('local')->assertMissing(preg_replace('/\.jpg$/', '-' . Avatars::SMALL . '.jpg', $me->fresh()->avatar_path));
    }
}
