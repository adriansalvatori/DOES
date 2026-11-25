<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\MediaLibraryService;
use Throwable;

/**
 * Second half of an attachment: once the browser finished the Drive upload, records the file on the deliverable.
 * The file is read back from Drive and must sit in the deliverable's own folder, so an id cannot point anywhere else.
 */
class AttachUploadedFile
{
    use AuthorizesActor;

    public function __construct(
        private GoogleDriveService $drive,
        private MediaLibraryService $library,
        private DeliveryLog $log,
    ) {}

    public function __invoke(Deliverable $deliverable, string $driveFileId, ?User $actor = null): Media
    {
        $this->authorizeActor($actor, 'attachFile', $deliverable);

        $existing = Media::query()->where('order_id', $deliverable->getKey())->where('drive_file_id', $driveFileId)->first();
        if ($existing !== null) {
            return $existing;
        }

        if (! $this->drive->isConfigured()) {
            throw WorkflowException::refused('Google Drive is not connected. Connect it in Settings → Google Drive.');
        }

        try {
            $order = Order::query()->findOrFail($deliverable->getKey());
            $folderId = $this->library->folderForOrder($order);
            $file = $this->drive->getFile($driveFileId);
        } catch (Throwable $exception) {
            report($exception);

            throw WorkflowException::refused('The file could not be sent to Google Drive. Try again in a moment.');
        }

        if (($file['trashed'] ?? false) || ! in_array($folderId, (array) ($file['parents'] ?? []), true)) {
            throw WorkflowException::refused('The file could not be sent to Google Drive. Try again in a moment.');
        }

        $media = Media::query()->create([
            'order_id' => $deliverable->getKey(),
            'client_id' => $order->client_id,
            'kind' => Media::KIND_FILE,
            'drive_file_id' => $driveFileId,
            'drive_folder_id' => $folderId,
            'name' => (string) ($file['name'] ?? $driveFileId),
            'mime_type' => $file['mimeType'] ?? null,
            'size' => isset($file['size']) ? (int) $file['size'] : null,
            'source' => Media::SOURCE_UPLOAD,
            'uploaded_by' => $actor?->getKey(),
            'uploader_name' => $actor?->name,
        ]);

        $this->log->record($deliverable, 'file_attached', $actor, null, $media->name, ['media_id' => $media->getKey()]);

        return $media;
    }
}
