<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Media;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Owns the Drive folder layout and the media records that point into it.
 *
 * Layout inside the configured root folder:
 *   Clientes/{CLIENT NAME}/{WO 16512 · ORDER TITLE}/file.jpg
 *   Clientes/{CLIENT NAME}/_Cliente/file.pdf      (client-level files, e.g. brand kit)
 */
class MediaLibraryService
{
    public const CLIENTS_FOLDER = 'Clientes';

    public const NO_CLIENT_FOLDER = 'SIN CLIENTE';

    public const CLIENT_FILES_FOLDER = '_Cliente';

    protected ?string $clientsRootFolderId = null;

    public function __construct(protected GoogleDriveService $drive) {}

    /**
     * Resolve (and create on first use) the Drive folder for an order, caching its id on the order.
     */
    public function folderForOrder(Order $order): string
    {
        if (! empty($order->drive_folder_id)) {
            return $order->drive_folder_id;
        }

        $parentId = $order->client
            ? $this->folderForClient($order->client)
            : $this->drive->ensureFolder($this->fallbackClientFolderName($order), $this->clientsRootFolder());

        $folderId = $this->drive->ensureFolder($order->drive_folder_name, $parentId);
        $order->forceFill(['drive_folder_id' => $folderId])->saveQuietly();

        return $folderId;
    }

    /**
     * Resolve (and create on first use) the Drive folder for a client, caching its id on the client.
     */
    public function folderForClient(Client $client): string
    {
        if (! empty($client->drive_folder_id)) {
            return $client->drive_folder_id;
        }

        $folderId = $this->drive->ensureFolder((string) $client->name, $this->clientsRootFolder());
        $client->forceFill(['drive_folder_id' => $folderId])->saveQuietly();

        return $folderId;
    }

    /**
     * Upload in-memory file contents into the order's folder and record it.
     *
     * @param  array{source?: string, uploaded_by?: int|null, uploader_name?: string|null, created_at?: Carbon|null, trello_attachment_id?: string|null, order_comment_id?: int|null}  $attributes
     */
    public function storeForOrder(Order $order, string $contents, string $name, string $mimeType, array $attributes = []): Media
    {
        $folderId = $this->folderForOrder($order);
        $createdAt = $attributes['created_at'] ?? null;
        $file = $this->drive->uploadFile($contents, $name, $mimeType, $folderId, $createdAt);

        $media = new Media([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'order_comment_id' => $attributes['order_comment_id'] ?? null,
            'kind' => Media::KIND_FILE,
            'drive_file_id' => $file['id'],
            'drive_folder_id' => $folderId,
            'name' => $name,
            'mime_type' => $mimeType,
            'size' => $file['size'] ?? strlen($contents),
            'source' => $attributes['source'] ?? Media::SOURCE_UPLOAD,
            'trello_attachment_id' => $attributes['trello_attachment_id'] ?? null,
            'uploaded_by' => $attributes['uploaded_by'] ?? null,
            'uploader_name' => $attributes['uploader_name'] ?? null,
        ]);

        if ($createdAt) {
            $media->created_at = $createdAt;
            $media->updated_at = $createdAt;
        }

        $media->save();

        return $media;
    }

    /**
     * Record a link attachment (no bytes stored anywhere).
     */
    public function storeLinkForOrder(Order $order, string $url, string $name, array $attributes = []): Media
    {
        $media = new Media([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'kind' => Media::KIND_LINK,
            'external_url' => $url,
            'name' => $name !== '' ? $name : $url,
            'source' => $attributes['source'] ?? Media::SOURCE_UPLOAD,
            'trello_attachment_id' => $attributes['trello_attachment_id'] ?? null,
            'uploaded_by' => $attributes['uploaded_by'] ?? null,
            'uploader_name' => $attributes['uploader_name'] ?? null,
        ]);

        if (! empty($attributes['created_at'])) {
            $media->created_at = $attributes['created_at'];
            $media->updated_at = $attributes['created_at'];
        }

        $media->save();

        return $media;
    }

    /**
     * Send the file to the shared drive's trash and soft-delete the record.
     */
    public function delete(Media $media): void
    {
        if ($media->drive_file_id) {
            $this->drive->trash($media->drive_file_id);
        }

        $media->delete();
    }

    protected function clientsRootFolder(): string
    {
        return $this->clientsRootFolderId ??= $this->drive->ensureFolder(self::CLIENTS_FOLDER, $this->drive->rootFolderId());
    }

    protected function fallbackClientFolderName(Order $order): string
    {
        $name = trim((string) $order->company_name);

        return $name !== '' ? mb_strtoupper($name, 'UTF-8') : self::NO_CLIENT_FOLDER;
    }
}
