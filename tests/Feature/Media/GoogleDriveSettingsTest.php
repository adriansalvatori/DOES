<?php

namespace Tests\Feature\Media;

use App\Http\Controllers\GoogleDriveAuthController;
use App\Livewire\Settings\GoogleDriveSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleDriveSettingsTest extends TestCase
{
    use FakesGoogleDrive, RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        $this->withSession(['locale' => 'es']);
        Http::preventStrayRequests();
        Cache::forget(GoogleDriveService::TOKEN_CACHE_KEY);
        $this->configureGoogleDrive(connected: false);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_only_admins_can_manage_the_connection(): void
    {
        $this->actingAs(User::factory()->designer()->create());

        $this->get(route('settings.google-drive'))->assertForbidden();
        $this->get(route('google-drive.connect'))->assertForbidden();
        $this->get(route('google-drive.callback', ['state' => 'x', 'code' => 'y']))->assertForbidden();
    }

    public function test_page_shows_missing_setup_and_the_redirect_uri_to_register(): void
    {
        config(['services.google_drive.client_id' => null]);

        $this->actingAs($this->admin)
            ->get(route('settings.google-drive'))
            ->assertOk()
            ->assertSee(__('Missing in .env'))
            ->assertSee(route('google-drive.callback'))
            ->assertDontSee(__('Connect Google Drive'));
    }

    public function test_page_shows_the_connected_account(): void
    {
        $this->configureGoogleDrive();

        $this->actingAs($this->admin)
            ->get(route('settings.google-drive'))
            ->assertOk()
            ->assertSee('drive@kudos.com')
            ->assertSee(__('Reconnect'))
            ->assertSee(__('Test connection'));
    }

    public function test_connect_sends_the_admin_to_google_with_a_session_state(): void
    {
        $response = $this->actingAs($this->admin)->get(route('google-drive.connect'));

        $state = session(GoogleDriveAuthController::STATE_SESSION_KEY);
        $this->assertNotEmpty($state);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $response->headers->get('Location'));
        $this->assertStringContainsString('state='.$state, $response->headers->get('Location'));
        $this->assertStringContainsString('redirect_uri='.urlencode(route('google-drive.callback')), $response->headers->get('Location'));
    }

    public function test_connect_without_an_oauth_client_explains_what_is_missing(): void
    {
        config(['services.google_drive.client_secret' => null]);

        $this->actingAs($this->admin)
            ->get(route('google-drive.connect'))
            ->assertRedirect(route('settings.google-drive'))
            ->assertSessionHas('error');
    }

    public function test_callback_rejects_a_state_that_does_not_match_the_session(): void
    {
        $this->actingAs($this->admin)
            ->withSession([GoogleDriveAuthController::STATE_SESSION_KEY => 'expected-state'])
            ->get(route('google-drive.callback', ['state' => 'forged-state', 'code' => 'auth-code']))
            ->assertRedirect(route('settings.google-drive'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertFalse(app(GoogleDriveService::class)->isConnected());
    }

    public function test_callback_reports_a_cancelled_consent(): void
    {
        $this->actingAs($this->admin)
            ->withSession([GoogleDriveAuthController::STATE_SESSION_KEY => 'expected-state'])
            ->get(route('google-drive.callback', ['state' => 'expected-state', 'error' => 'access_denied']))
            ->assertRedirect(route('settings.google-drive'))
            ->assertSessionHas('error', __('The Google Drive connection was cancelled.'));

        Http::assertNothingSent();
    }

    public function test_callback_connects_the_account(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'refresh_token' => 'new-refresh-token', 'expires_in' => 3599, 'scope' => 'https://www.googleapis.com/auth/drive']),
            'www.googleapis.com/drive/v3/about*' => Http::response(['user' => ['displayName' => 'Producción Kudos', 'emailAddress' => 'produccion@kudos.com']]),
        ]);

        $this->actingAs($this->admin)
            ->withSession([GoogleDriveAuthController::STATE_SESSION_KEY => 'expected-state'])
            ->get(route('google-drive.callback', ['state' => 'expected-state', 'code' => 'auth-code']))
            ->assertRedirect(route('settings.google-drive'))
            ->assertSessionHas('success');

        $this->assertNull(session(GoogleDriveAuthController::STATE_SESSION_KEY));
        $this->assertTrue(app(GoogleDriveService::class)->isConfigured());
        $this->assertSame('produccion@kudos.com', app(GoogleDriveService::class)->connectedAccount()['email']);
    }

    public function test_callback_shows_why_google_rejected_the_code(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Bad Request'], 400)]);

        $this->actingAs($this->admin)
            ->withSession([GoogleDriveAuthController::STATE_SESSION_KEY => 'expected-state'])
            ->get(route('google-drive.callback', ['state' => 'expected-state', 'code' => 'used-code']))
            ->assertRedirect(route('settings.google-drive'))
            ->assertSessionHas('error', 'Google rechazó la conexión: Bad Request');

        $this->assertFalse(app(GoogleDriveService::class)->isConnected());
    }

    public function test_connection_test_reports_the_root_folder(): void
    {
        $this->configureGoogleDrive();
        Http::fake(array_merge(
            ['www.googleapis.com/drive/v3/files/root-folder*' => Http::response(['id' => 'root-folder', 'name' => 'DOES', 'driveId' => 'shared-drive-1'])],
            $this->googleDriveFakes(),
        ));

        Livewire::actingAs($this->admin)
            ->test(GoogleDriveSettings::class)
            ->call('testConnection')
            ->assertSet('testPassed', true)
            ->assertSee(__('Connected to the folder “:name”.', ['name' => 'DOES']));
    }

    public function test_connection_test_warns_when_the_root_folder_is_outside_a_shared_drive(): void
    {
        $this->configureGoogleDrive();
        Http::fake(array_merge(
            ['www.googleapis.com/drive/v3/files/root-folder*' => Http::response(['id' => 'root-folder', 'name' => 'Mi carpeta'])],
            $this->googleDriveFakes(),
        ));

        Livewire::actingAs($this->admin)
            ->test(GoogleDriveSettings::class)
            ->call('testConnection')
            ->assertSet('testPassed', true)
            ->assertSee(__('Connected to the folder “:name”, but it is not in a shared drive.', ['name' => 'Mi carpeta']));
    }

    public function test_connection_test_shows_drives_error(): void
    {
        $this->configureGoogleDrive();
        $this->fakeGoogleDrive();

        Livewire::actingAs($this->admin)
            ->test(GoogleDriveSettings::class)
            ->call('testConnection')
            ->assertSet('testPassed', false)
            ->assertSee('HTTP 404');
    }

    public function test_disconnect_forgets_the_account(): void
    {
        $this->configureGoogleDrive();
        $this->fakeGoogleDrive();

        Livewire::actingAs($this->admin)
            ->test(GoogleDriveSettings::class)
            ->call('disconnect')
            ->assertSee(__('Not connected'));

        $this->assertFalse(app(GoogleDriveService::class)->isConnected());
        $this->assertSame(0, Setting::where('key', GoogleDriveService::REFRESH_TOKEN_SETTING)->count());
    }
}
