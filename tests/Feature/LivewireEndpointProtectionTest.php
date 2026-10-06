<?php

namespace Tests\Feature;

use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Tests\TestCase;

class LivewireEndpointProtectionTest extends TestCase
{
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
}
