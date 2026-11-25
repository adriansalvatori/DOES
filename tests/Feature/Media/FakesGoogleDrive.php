<?php

namespace Tests\Feature\Media;

use App\Models\Setting;
use App\Services\GoogleDriveService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Stateful fake of the Google Drive v3 endpoints DOES uses (folders, multipart uploads,
 * metadata, downloads, thumbnails, trash), plus the OAuth token, revoke and "about" endpoints.
 */
trait FakesGoogleDrive
{
    /**
     * @var array{folders: array<string, array{name: string, parent: string}>, files: array<string, array<string, mixed>>, counter: int}
     */
    protected array $drive = ['folders' => [], 'files' => [], 'counter' => 0];

    /**
     * Set the OAuth client and root folder, and store a connected account's refresh token.
     */
    protected function configureGoogleDrive(string $rootFolderId = 'root-folder', bool $connected = true): void
    {
        config([
            'services.google_drive.client_id' => 'does-client.apps.googleusercontent.com',
            'services.google_drive.client_secret' => 'does-client-secret',
            'services.google_drive.root_folder_id' => $rootFolderId,
        ]);

        if ($connected) {
            Setting::set(GoogleDriveService::REFRESH_TOKEN_SETTING, Crypt::encryptString('stored-refresh-token'));
            Setting::set(GoogleDriveService::ACCOUNT_SETTING, json_encode(['email' => 'drive@kudos.com', 'name' => 'Kudos Drive', 'connected_at' => '2026-11-24T10:00:00-05:00']));
        }
    }

    /**
     * @return array<string, \Closure|PromiseInterface>
     */
    protected function googleDriveFakes(): array
    {
        return [
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'drive-token', 'expires_in' => 3599, 'token_type' => 'Bearer']),
            'oauth2.googleapis.com/revoke*' => Http::response([], 200),
            'www.googleapis.com/drive/v3/about*' => Http::response(['user' => ['displayName' => 'Kudos Drive', 'emailAddress' => 'drive@kudos.com']]),
            'www.googleapis.com/upload/drive/v3/files*' => fn (Request $request) => $this->handleDriveUpload($request),
            'www.googleapis.com/drive/v3/files*' => fn (Request $request) => $this->handleDriveFiles($request),
            'lh3.googleusercontent.com/*' => Http::response('THUMBNAIL-BYTES', 200, ['Content-Type' => 'image/png']),
        ];
    }

    protected function fakeGoogleDrive(): void
    {
        Http::fake($this->googleDriveFakes());
    }

    /**
     * Ids of the folders forming the given path from the root, or null when any segment is missing.
     */
    protected function driveFolderPath(string ...$names): ?string
    {
        $parent = config('services.google_drive.root_folder_id');
        foreach ($names as $name) {
            $match = collect($this->drive['folders'])->search(fn (array $folder) => $folder['name'] === $name && $folder['parent'] === $parent);
            if ($match === false) {
                return null;
            }
            $parent = $match;
        }

        return $parent;
    }

    protected function handleDriveUpload(Request $request): mixed
    {
        $body = $request->body();
        preg_match('/\r\n\r\n(\{.*?\})\r\n--/s', $body, $metaMatch);
        $metadata = json_decode($metaMatch[1] ?? '{}', true);
        preg_match('/Content-Type: (?!application\/json)[^\r\n]+\r\n\r\n(.*)\r\n--[^\r\n]+--$/s', $body, $contentMatch);
        $content = $contentMatch[1] ?? '';

        $id = 'file-'.(++$this->drive['counter']);
        $this->drive['files'][$id] = [
            'name' => $metadata['name'] ?? 'untitled',
            'parent' => $metadata['parents'][0] ?? null,
            'mimeType' => $metadata['mimeType'] ?? 'application/octet-stream',
            'createdTime' => $metadata['createdTime'] ?? null,
            'content' => $content,
            'trashed' => false,
        ];

        return Http::response([
            'id' => $id,
            'name' => $metadata['name'] ?? 'untitled',
            'mimeType' => $metadata['mimeType'] ?? 'application/octet-stream',
            'size' => (string) strlen($content),
        ]);
    }

    protected function handleDriveFiles(Request $request): mixed
    {
        $path = parse_url($request->url(), PHP_URL_PATH);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $fileId = preg_match('#/drive/v3/files/([^/?]+)#', $path, $m) ? $m[1] : null;

        if ($fileId === null && $request->method() === 'GET') {
            preg_match("/^'((?:[^'\\\\]|\\\\.)*)' in parents and name = '((?:[^'\\\\]|\\\\.)*)'/", (string) ($query['q'] ?? ''), $q);
            $parent = stripslashes($q[1] ?? '');
            $name = stripslashes($q[2] ?? '');
            $found = collect($this->drive['folders'])->search(fn (array $folder) => $folder['name'] === $name && $folder['parent'] === $parent);

            return Http::response(['files' => $found === false ? [] : [['id' => $found, 'name' => $name]]]);
        }

        if ($fileId === null && $request->method() === 'POST') {
            $id = 'folder-'.(++$this->drive['counter']);
            $this->drive['folders'][$id] = ['name' => $request['name'], 'parent' => $request['parents'][0]];

            return Http::response(['id' => $id]);
        }

        if (! isset($this->drive['files'][$fileId])) {
            return Http::response(['error' => ['code' => 404, 'message' => "File not found: {$fileId}."]], 404);
        }

        if ($request->method() === 'PATCH') {
            if ($request['trashed'] ?? false) {
                $this->drive['files'][$fileId]['trashed'] = true;
            }
            if (isset($request['name'])) {
                $this->drive['files'][$fileId]['name'] = $request['name'];
            }

            return Http::response(['id' => $fileId]);
        }

        $file = $this->drive['files'][$fileId];

        if (($query['alt'] ?? null) === 'media') {
            return Http::response($file['content'], 200, ['Content-Type' => $file['mimeType'], 'Content-Length' => (string) strlen($file['content'])]);
        }

        $isImage = str_starts_with($file['mimeType'], 'image/');

        return Http::response([
            'id' => $fileId,
            'name' => $file['name'],
            'mimeType' => $file['mimeType'],
            'hasThumbnail' => $isImage,
            'thumbnailLink' => $isImage ? "https://lh3.googleusercontent.com/drive-storage/{$fileId}=s220" : null,
        ]);
    }
}
