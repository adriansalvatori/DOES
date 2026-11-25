<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin Google Drive v3 client for the Kudos shared drive, authenticated with OAuth as the Kudos
 * account an admin connected in Ajustes → Google Drive (its refresh token is stored encrypted).
 *
 * Uses the HTTP client directly (no google/apiclient) and never writes file bytes to local disk:
 * uploads are sent from memory and downloads are streamed straight through to the browser.
 */
class GoogleDriveService
{
    public const FOLDER_MIME = 'application/vnd.google-apps.folder';

    public const TOKEN_CACHE_KEY = 'google_drive_access_token';

    public const REFRESH_TOKEN_SETTING = 'google_drive_refresh_token';

    public const ACCOUNT_SETTING = 'google_drive_account';

    public const FILE_FIELDS = 'id,name,mimeType,size,createdTime,thumbnailLink,hasThumbnail,webViewLink,parents,trashed';

    protected const SCOPE = 'https://www.googleapis.com/auth/drive';

    protected const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    protected const AUTHORIZE_URI = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected const REVOKE_URI = 'https://oauth2.googleapis.com/revoke';

    protected string $apiBase = 'https://www.googleapis.com/drive/v3';

    protected string $uploadBase = 'https://www.googleapis.com/upload/drive/v3';

    /**
     * Ready to read and write files: OAuth client set, root folder set and an account connected.
     */
    public function isConfigured(): bool
    {
        return $this->hasOAuthClient() && $this->rootFolderId() !== '' && $this->isConnected();
    }

    public function hasOAuthClient(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function isConnected(): bool
    {
        return $this->refreshToken() !== null;
    }

    public function rootFolderId(): string
    {
        return trim((string) config('services.google_drive.root_folder_id'));
    }

    /**
     * The Google account that was connected, as reported by Drive when it was connected.
     *
     * @return array{email: string, name: string, connected_at: string}|null
     */
    public function connectedAccount(): ?array
    {
        if (! $this->isConnected()) {
            return null;
        }

        $account = json_decode((string) Setting::get(self::ACCOUNT_SETTING), true);

        return is_array($account) ? $account + ['email' => '', 'name' => '', 'connected_at' => ''] : null;
    }

    /**
     * URL of Google's consent screen asking for offline access to Drive.
     */
    public function authorizationUrl(string $redirectUri, string $state): string
    {
        return self::AUTHORIZE_URI.'?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Exchange the consent-screen code for tokens, then store the refresh token (encrypted) and the account.
     *
     * @return array{email: string, name: string, connected_at: string}
     */
    public function connect(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URI, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $redirectUri,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(__('Google rechazó la conexión: :reason', ['reason' => $response->json('error_description') ?? $response->json('error') ?? $response->body()]));
        }

        $refreshToken = $response->json('refresh_token');
        $accessToken = $response->json('access_token');
        $scopes = explode(' ', (string) $response->json('scope'));

        if (! is_string($refreshToken) || $refreshToken === '' || ! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException(__('Google no devolvió un token de acceso permanente. Vuelve a intentarlo.'));
        }
        if (! in_array(self::SCOPE, $scopes, true)) {
            throw new RuntimeException(__('Falta el permiso de Google Drive. Vuelve a conectar y marca la casilla de acceso a Drive.'));
        }

        $about = Http::withToken($accessToken)->acceptJson()->timeout(30)
            ->get("{$this->apiBase}/about", ['fields' => 'user(displayName,emailAddress)']);
        $this->ensureSuccessful($about, __('leer la cuenta conectada'));

        $account = [
            'email' => (string) $about->json('user.emailAddress'),
            'name' => (string) $about->json('user.displayName'),
            'connected_at' => now()->toIso8601String(),
        ];

        Setting::set(self::REFRESH_TOKEN_SETTING, Crypt::encryptString($refreshToken));
        Setting::set(self::ACCOUNT_SETTING, json_encode($account, JSON_UNESCAPED_UNICODE));
        Cache::put(self::TOKEN_CACHE_KEY, $accessToken, now()->addSeconds(max(60, (int) $response->json('expires_in', 3600) - 300)));

        return $account;
    }

    /**
     * Revoke the stored grant at Google (best effort) and forget it locally.
     */
    public function disconnect(): void
    {
        $refreshToken = $this->refreshToken();

        if ($refreshToken !== null) {
            try {
                Http::asForm()->timeout(15)->post(self::REVOKE_URI, ['token' => $refreshToken]);
            } catch (\Throwable) {
                // Forgetting the token locally is what matters; Google drops unused grants on its own.
            }
        }

        Setting::whereIn('key', [self::REFRESH_TOKEN_SETTING, self::ACCOUNT_SETTING])->delete();
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    /**
     * Return a cached OAuth access token, refreshing it with the stored refresh token when needed.
     */
    public function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        [$token, $expiresIn] = $this->requestAccessToken();
        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds(max(60, $expiresIn - 300)));

        return $token;
    }

    /**
     * Find a non-trashed folder by exact name directly under the given parent.
     */
    public function findFolder(string $name, string $parentId): ?string
    {
        $query = sprintf(
            "'%s' in parents and name = '%s' and mimeType = '%s' and trashed = false",
            $this->escapeQueryValue($parentId),
            $this->escapeQueryValue($name),
            self::FOLDER_MIME,
        );

        $response = $this->client()->get("{$this->apiBase}/files", [
            'q' => $query,
            'fields' => 'files(id,name)',
            'pageSize' => 1,
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
            'corpora' => 'allDrives',
        ]);

        $this->ensureSuccessful($response, __("buscar la carpeta ':name'", ['name' => $name]));

        return $response->json('files.0.id');
    }

    public function createFolder(string $name, string $parentId): string
    {
        $response = $this->client()->post("{$this->apiBase}/files?supportsAllDrives=true&fields=id", [
            'name' => $this->sanitizeName($name),
            'mimeType' => self::FOLDER_MIME,
            'parents' => [$parentId],
        ]);

        $this->ensureSuccessful($response, __("crear la carpeta ':name'", ['name' => $name]));

        return (string) $response->json('id');
    }

    /**
     * Return the id of the named folder under the parent, creating it when it does not exist yet.
     */
    public function ensureFolder(string $name, string $parentId): string
    {
        $name = $this->sanitizeName($name);

        return $this->findFolder($name, $parentId) ?? $this->createFolder($name, $parentId);
    }

    /**
     * Upload a file held in memory with a single multipart request (suited to files up to a few dozen MB).
     *
     * @return array{id: string, name: string, mimeType: string, size: int|null}
     */
    public function uploadFile(string $contents, string $name, string $mimeType, string $parentId, ?Carbon $createdTime = null): array
    {
        $boundary = 'does_'.Str::random(32);
        $metadata = array_filter([
            'name' => $this->sanitizeName($name),
            'parents' => [$parentId],
            'mimeType' => $mimeType,
            'createdTime' => $createdTime?->toRfc3339String(),
        ]);

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .json_encode($metadata, JSON_UNESCAPED_UNICODE)."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: {$mimeType}\r\n\r\n"
            .$contents."\r\n"
            ."--{$boundary}--";

        $response = $this->client()
            ->timeout(300)
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post("{$this->uploadBase}/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,mimeType,size");

        $this->ensureSuccessful($response, __("subir ':name'", ['name' => $name]));

        return [
            'id' => (string) $response->json('id'),
            'name' => (string) $response->json('name'),
            'mimeType' => (string) $response->json('mimeType'),
            'size' => $response->json('size') !== null ? (int) $response->json('size') : null,
        ];
    }

    /**
     * Open a resumable upload session so the browser can send the bytes directly to Google.
     * Passing the browser's Origin lets Google answer the browser's PUT with CORS headers.
     */
    public function createResumableUploadSession(string $name, string $mimeType, string $parentId, ?int $size = null, ?string $origin = null): string
    {
        $headers = array_filter([
            'X-Upload-Content-Type' => $mimeType,
            'X-Upload-Content-Length' => $size !== null ? (string) $size : null,
            'Origin' => $origin,
        ]);

        $response = $this->client()
            ->withHeaders($headers)
            ->post("{$this->uploadBase}/files?uploadType=resumable&supportsAllDrives=true&fields=id,name,mimeType,size", [
                'name' => $this->sanitizeName($name),
                'parents' => [$parentId],
                'mimeType' => $mimeType,
            ]);

        $this->ensureSuccessful($response, __("iniciar la subida de ':name'", ['name' => $name]));

        $location = $response->header('Location');
        if ($location === '') {
            throw new RuntimeException(__('Google Drive no devolvió una URL de sesión de subida.'));
        }

        return $location;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFile(string $fileId, string $fields = self::FILE_FIELDS): array
    {
        $response = $this->client()->get("{$this->apiBase}/files/{$fileId}", [
            'fields' => $fields,
            'supportsAllDrives' => 'true',
        ]);

        $this->ensureSuccessful($response, __('obtener el archivo'));

        return $response->json() ?? [];
    }

    /**
     * Open a streamed download of the file's bytes (the body is read lazily, nothing touches disk).
     */
    public function downloadStream(string $fileId): Response
    {
        $response = Http::withToken($this->accessToken())
            ->withOptions(['stream' => true])
            ->timeout(300)
            ->get("{$this->apiBase}/files/{$fileId}", [
                'alt' => 'media',
                'supportsAllDrives' => 'true',
            ]);

        $this->ensureSuccessful($response, __('descargar el archivo'));

        return $response;
    }

    /**
     * Fetch a thumbnail image for the file at the requested width, or null when Drive has none.
     *
     * @return array{content: string, mime: string}|null
     */
    public function thumbnail(string $fileId, int $size = 400): ?array
    {
        $file = $this->getFile($fileId, 'id,thumbnailLink,hasThumbnail');
        $link = $file['thumbnailLink'] ?? null;

        if (empty($file['hasThumbnail']) || empty($link)) {
            return null;
        }

        $link = preg_replace('/=s\d+$/', "=s{$size}", $link) ?? $link;
        $response = Http::withToken($this->accessToken())->timeout(30)->get($link);

        if (! $response->successful()) {
            return null;
        }

        return [
            'content' => $response->body(),
            'mime' => $response->header('Content-Type') ?: 'image/jpeg',
        ];
    }

    public function rename(string $fileId, string $name): void
    {
        $response = $this->client()->patch("{$this->apiBase}/files/{$fileId}?supportsAllDrives=true&fields=id", [
            'name' => $this->sanitizeName($name),
        ]);

        $this->ensureSuccessful($response, __('renombrar el archivo'));
    }

    /**
     * Move a file to the shared drive's trash (recoverable from Drive for 30 days).
     */
    public function trash(string $fileId): void
    {
        $response = $this->client()->patch("{$this->apiBase}/files/{$fileId}?supportsAllDrives=true&fields=id", [
            'trashed' => true,
        ]);

        if ($response->status() === 404) {
            return;
        }

        $this->ensureSuccessful($response, __('enviar el archivo a la papelera'));
    }

    protected function client(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(60)
            ->retry(3, 1000, function (\Throwable $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                return $exception instanceof RequestException
                    && in_array($exception->response->status(), [429, 500, 502, 503, 504], true);
            }, throw: false);
    }

    /**
     * @return array{0: string, 1: int} The access token and its lifetime in seconds.
     */
    protected function requestAccessToken(): array
    {
        $refreshToken = $this->refreshToken();
        if (! $this->hasOAuthClient() || $refreshToken === null) {
            throw new RuntimeException(__('Google Drive no está conectado. Conéctalo en Ajustes → Google Drive.'));
        }

        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URI, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        $token = $response->json('access_token');
        if ($response->json('error') === 'invalid_grant') {
            throw new RuntimeException(__('La conexión con Google Drive se revocó o expiró. Vuelve a conectarla en Ajustes → Google Drive.'));
        }
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException(__('Google rechazó la autenticación de Google Drive: :reason', ['reason' => $response->body()]));
        }

        return [$token, (int) $response->json('expires_in', 3600)];
    }

    protected function clientId(): string
    {
        return trim((string) config('services.google_drive.client_id'));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('services.google_drive.client_secret'));
    }

    /**
     * The stored refresh token, or null when none is stored or it cannot be decrypted (e.g. a database copied from another APP_KEY).
     */
    protected function refreshToken(): ?string
    {
        $stored = Setting::get(self::REFRESH_TOKEN_SETTING);
        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            $token = Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }

        return $token !== '' ? $token : null;
    }

    protected function ensureSuccessful(Response $response, string $action): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error.message') ?? $response->body();

        throw new RuntimeException(__('Google Drive: no se pudo :action (HTTP :status): :message', ['action' => $action, 'status' => $response->status(), 'message' => $message]));
    }

    protected function escapeQueryValue(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    /**
     * Drive accepts almost any name, but strip control characters and path separators to keep folders tidy.
     */
    protected function sanitizeName(string $name): string
    {
        $clean = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $name) ?? $name);

        return Str::limit($clean !== '' ? $clean : 'sin-nombre', 200, '');
    }
}
