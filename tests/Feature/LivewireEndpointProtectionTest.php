<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Tests\TestCase;

class LivewireEndpointProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_request_to_livewire_update_endpoint_redirects_gracefully_to_referer(): void
    {
        $updatePath = EndpointResolver::updatePath();

        $response = $this->withHeaders([
            'Referer' => config('app.url').'/planner',
        ])->get($updatePath);

        $response->assertStatus(302);
        $response->assertRedirect(config('app.url').'/planner');
    }

    public function test_get_request_to_livewire_update_without_referer_redirects_to_dashboard(): void
    {
        $updatePath = EndpointResolver::updatePath();

        $response = $this->get($updatePath);

        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_get_request_to_fallback_livewire_update_redirects_gracefully(): void
    {
        $response = $this->withHeaders([
            'Referer' => config('app.url').'/kanban',
        ])->get('/livewire/update');

        $response->assertStatus(302);
        $response->assertRedirect(config('app.url').'/kanban');
    }

    public function test_unauthenticated_request_to_preview_without_valid_signature_returns_401(): void
    {
        $previewUrl = class_exists(EndpointResolver::class)
            ? str_replace('{filename}', 'nonexistent.png', EndpointResolver::previewPath())
            : '/livewire/preview-file/nonexistent.png';

        $response = $this->get($previewUrl);
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_preview_endpoint(): void
    {
        $user = User::factory()->create();
        $storage = FileUploadConfiguration::storage();
        $filename = 'test_preview_file.png';
        $path = FileUploadConfiguration::path($filename);
        $storage->put($path, 'fake_png_data');

        $previewUrl = class_exists(EndpointResolver::class)
            ? str_replace('{filename}', $filename, EndpointResolver::previewPath())
            : "/livewire/preview-file/{$filename}";

        $response = $this->actingAs($user)->get($previewUrl);

        $response->assertStatus(200);
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));

        $storage->delete($path);
    }

    public function test_guest_with_valid_signed_url_can_access_preview_endpoint(): void
    {
        $storage = FileUploadConfiguration::storage();
        $filename = 'signed_preview_file.png';
        $path = FileUploadConfiguration::path($filename);
        $storage->put($path, 'signed_fake_png_data');

        $signedUrl = URL::temporarySignedRoute(
            'livewire.preview-file',
            now()->addMinutes(30),
            ['filename' => $filename]
        );

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));

        $storage->delete($path);
    }
}
