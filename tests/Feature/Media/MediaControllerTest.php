<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use FakesGoogleDrive, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Cache::forget(GoogleDriveService::TOKEN_CACHE_KEY);
        $this->configureGoogleDrive();
        $this->fakeGoogleDrive();
    }

    public function test_guests_cannot_reach_drive_files(): void
    {
        $media = Media::factory()->create();

        $this->get(route('media.file', $media))->assertRedirect(route('login'));
        $this->get(route('media.thumbnail', $media))->assertRedirect(route('login'));
    }

    public function test_file_is_streamed_inline_from_drive(): void
    {
        $media = $this->storedMedia('Arte final "v2".pdf', 'application/pdf', '%PDF-BYTES');

        $response = $this->actingAs(User::factory()->create())->get(route('media.file', $media));

        $response->assertOk();
        $this->assertSame('%PDF-BYTES', $response->streamedContent());
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline; filename="Arte final \"v2\".pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_file_can_be_downloaded_as_an_attachment(): void
    {
        $media = $this->storedMedia('render.png', 'image/png', 'PNG');

        $response = $this->actingAs(User::factory()->create())->get(route('media.file', [$media, 'download' => 1]));

        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_link_media_redirects_to_the_external_url(): void
    {
        $media = Media::factory()->link()->create(['external_url' => 'https://docs.google.com/document/d/brief']);

        $this->actingAs(User::factory()->create())
            ->get(route('media.file', $media))
            ->assertRedirect('https://docs.google.com/document/d/brief');
    }

    public function test_thumbnail_is_served_for_images_and_404s_when_drive_has_none(): void
    {
        $user = User::factory()->create();
        $image = $this->storedMedia('render.png', 'image/png', 'PNG');
        $pdf = $this->storedMedia('doc.pdf', 'application/pdf', 'PDF');

        $this->actingAs($user)->get(route('media.thumbnail', $image))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->actingAs($user)->get(route('media.thumbnail', $pdf))->assertNotFound();
    }

    public function test_a_file_missing_from_drive_returns_bad_gateway(): void
    {
        $media = Media::factory()->create(['drive_file_id' => 'gone']);

        $this->actingAs(User::factory()->create())->get(route('media.file', $media))->assertStatus(502);
    }

    protected function storedMedia(string $name, string $mime, string $content): Media
    {
        $file = app(GoogleDriveService::class)->uploadFile($content, $name, $mime, 'folder-1');

        return Media::factory()->create([
            'drive_file_id' => $file['id'],
            'name' => $name,
            'mime_type' => $mime,
        ]);
    }
}
