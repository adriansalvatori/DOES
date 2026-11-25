<?php

namespace Tests\Feature\Media;

use App\Models\Setting;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GoogleDriveServiceTest extends TestCase
{
    use FakesGoogleDrive, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Cache::forget(GoogleDriveService::TOKEN_CACHE_KEY);
        $this->configureGoogleDrive();
    }

    public function test_is_configured_only_with_client_root_folder_and_a_connected_account(): void
    {
        $this->assertTrue(app(GoogleDriveService::class)->isConfigured());
        $this->assertSame('drive@kudos.com', app(GoogleDriveService::class)->connectedAccount()['email']);

        config(['services.google_drive.client_secret' => null]);
        $this->assertFalse(app(GoogleDriveService::class)->isConfigured());

        $this->configureGoogleDrive('');
        $this->assertFalse(app(GoogleDriveService::class)->isConfigured());

        Setting::query()->delete();
        $this->configureGoogleDrive('root-folder', connected: false);
        $this->assertFalse(app(GoogleDriveService::class)->isConfigured());
        $this->assertTrue(app(GoogleDriveService::class)->hasOAuthClient());
        $this->assertNull(app(GoogleDriveService::class)->connectedAccount());
    }

    public function test_a_stored_token_that_cannot_be_decrypted_counts_as_disconnected(): void
    {
        Setting::set(GoogleDriveService::REFRESH_TOKEN_SETTING, 'encrypted-with-another-app-key');

        $this->assertFalse(app(GoogleDriveService::class)->isConnected());
        $this->assertFalse(app(GoogleDriveService::class)->isConfigured());
    }

    public function test_access_token_is_refreshed_with_the_stored_refresh_token_and_cached(): void
    {
        $this->fakeGoogleDrive();
        $drive = app(GoogleDriveService::class);

        $this->assertSame('drive-token', $drive->accessToken());
        $this->assertSame('drive-token', $drive->accessToken());

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'stored-refresh-token'
            && $request['client_id'] === 'does-client.apps.googleusercontent.com'
            && $request['client_secret'] === 'does-client-secret');
    }

    public function test_a_revoked_connection_throws_a_clear_error(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vuelve a conectarla en Ajustes → Google Drive');

        app(GoogleDriveService::class)->accessToken();
    }

    public function test_other_authentication_failures_throw_a_clear_error(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_client'], 401)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google rechazó la autenticación');

        app(GoogleDriveService::class)->accessToken();
    }

    public function test_access_token_without_a_connection_explains_how_to_connect(): void
    {
        Setting::query()->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conéctalo en Ajustes → Google Drive');

        app(GoogleDriveService::class)->accessToken();
    }

    public function test_authorization_url_asks_for_offline_drive_access(): void
    {
        $url = app(GoogleDriveService::class)->authorizationUrl('https://does.test/settings/google-drive/callback', 'state-123');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertSame('does-client.apps.googleusercontent.com', $query['client_id']);
        $this->assertSame('https://does.test/settings/google-drive/callback', $query['redirect_uri']);
        $this->assertSame('https://www.googleapis.com/auth/drive', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame('state-123', $query['state']);
    }

    public function test_connect_stores_the_refresh_token_encrypted_and_the_account(): void
    {
        Setting::query()->delete();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'refresh_token' => 'new-refresh-token', 'expires_in' => 3599, 'scope' => 'https://www.googleapis.com/auth/drive']),
            'www.googleapis.com/drive/v3/about*' => Http::response(['user' => ['displayName' => 'Producción Kudos', 'emailAddress' => 'produccion@kudos.com']]),
        ]);

        $account = app(GoogleDriveService::class)->connect('auth-code', 'https://does.test/settings/google-drive/callback');

        $this->assertSame('produccion@kudos.com', $account['email']);
        $stored = Setting::get(GoogleDriveService::REFRESH_TOKEN_SETTING);
        $this->assertNotSame('new-refresh-token', $stored);
        $this->assertSame('new-refresh-token', Crypt::decryptString($stored));
        $this->assertSame('Producción Kudos', app(GoogleDriveService::class)->connectedAccount()['name']);
        $this->assertSame('fresh-token', app(GoogleDriveService::class)->accessToken());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'auth-code'
            && $request['redirect_uri'] === 'https://does.test/settings/google-drive/callback');
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.googleapis.com/drive/v3/about')
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
    }

    public function test_connect_rejects_a_grant_without_a_refresh_token(): void
    {
        Setting::query()->delete();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3599, 'scope' => 'https://www.googleapis.com/auth/drive'])]);

        try {
            app(GoogleDriveService::class)->connect('auth-code', 'https://does.test/callback');
            $this->fail('Expected the connection to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('token de acceso permanente', $e->getMessage());
        }

        $this->assertFalse(app(GoogleDriveService::class)->isConnected());
    }

    public function test_connect_rejects_a_grant_without_drive_access(): void
    {
        Setting::query()->delete();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'refresh_token' => 'new-refresh-token', 'scope' => 'openid email'])]);

        try {
            app(GoogleDriveService::class)->connect('auth-code', 'https://does.test/callback');
            $this->fail('Expected the connection to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Falta el permiso de Google Drive', $e->getMessage());
        }

        $this->assertFalse(app(GoogleDriveService::class)->isConnected());
    }

    public function test_disconnect_revokes_the_grant_and_forgets_it(): void
    {
        $this->fakeGoogleDrive();
        $drive = app(GoogleDriveService::class);
        $drive->accessToken();

        $drive->disconnect();

        $this->assertFalse($drive->isConnected());
        $this->assertNull($drive->connectedAccount());
        $this->assertFalse(Cache::has(GoogleDriveService::TOKEN_CACHE_KEY));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/revoke'
            && $request['token'] === 'stored-refresh-token');
    }

    public function test_ensure_folder_reuses_an_existing_folder_and_creates_missing_ones(): void
    {
        $this->fakeGoogleDrive();
        $drive = app(GoogleDriveService::class);

        $created = $drive->ensureFolder("D'ANGELO'S PIZZA", 'root-folder');
        $reused = $drive->ensureFolder("D'ANGELO'S PIZZA", 'root-folder');
        $sibling = $drive->ensureFolder('OTRO CLIENTE', 'root-folder');

        $this->assertSame($created, $reused);
        $this->assertNotSame($created, $sibling);
        $this->assertCount(2, $this->drive['folders']);
        $this->assertSame("D'ANGELO'S PIZZA", $this->drive['folders'][$created]['name']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_contains(urldecode($request->url()), "name = 'D\\'ANGELO\\'S PIZZA'")
            && str_contains($request->url(), 'supportsAllDrives=true'));
    }

    public function test_upload_file_sends_a_multipart_request_into_the_shared_drive_folder(): void
    {
        $this->fakeGoogleDrive();

        $result = app(GoogleDriveService::class)->uploadFile('PNG-BYTES', 'mockup final.png', 'image/png', 'folder-123', Carbon::parse('2025-03-04 10:00:00'));

        $this->assertSame('mockup final.png', $result['name']);
        $this->assertSame(9, $result['size']);

        $stored = $this->drive['files'][$result['id']];
        $this->assertSame('folder-123', $stored['parent']);
        $this->assertSame('PNG-BYTES', $stored['content']);
        $this->assertStringStartsWith('2025-03-04T10:00:00', $stored['createdTime']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'uploadType=multipart')
            && str_contains($request->url(), 'supportsAllDrives=true')
            && str_starts_with($request->header('Content-Type')[0], 'multipart/related; boundary=')
            && $request->header('Authorization')[0] === 'Bearer drive-token');
    }

    public function test_resumable_session_forwards_the_browser_origin_and_returns_the_session_url(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'drive-token']),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response('', 200, ['Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=abc']),
        ]);

        $url = app(GoogleDriveService::class)->createResumableUploadSession('video.mp4', 'video/mp4', 'folder-1', 50_000_000, 'https://does.example.com');

        $this->assertSame('https://www.googleapis.com/upload/drive/v3/files?upload_id=abc', $url);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'uploadType=resumable')
            && $request->header('Origin')[0] === 'https://does.example.com'
            && $request->header('X-Upload-Content-Length')[0] === '50000000');
    }

    public function test_api_errors_surface_googles_message(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'drive-token']),
            'www.googleapis.com/drive/v3/files*' => Http::response(['error' => ['code' => 403, 'message' => 'The user does not have sufficient permissions for this file.']], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sufficient permissions');

        app(GoogleDriveService::class)->createFolder('X', 'root-folder');
    }

    public function test_trash_moves_the_file_to_the_trash_and_ignores_already_missing_files(): void
    {
        $this->fakeGoogleDrive();
        $drive = app(GoogleDriveService::class);
        $file = $drive->uploadFile('x', 'a.txt', 'text/plain', 'folder-1');

        $drive->trash($file['id']);
        $drive->trash('does-not-exist');

        $this->assertTrue($this->drive['files'][$file['id']]['trashed']);
    }

    public function test_thumbnail_requests_the_resized_link_with_credentials(): void
    {
        $this->fakeGoogleDrive();
        $drive = app(GoogleDriveService::class);
        $image = $drive->uploadFile('img', 'a.png', 'image/png', 'folder-1');
        $pdf = $drive->uploadFile('pdf', 'a.pdf', 'application/pdf', 'folder-1');

        $thumbnail = $drive->thumbnail($image['id'], 640);

        $this->assertSame('THUMBNAIL-BYTES', $thumbnail['content']);
        $this->assertNull($drive->thumbnail($pdf['id']));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), "{$image['id']}=s640")
            && $request->header('Authorization')[0] === 'Bearer drive-token');
    }
}
