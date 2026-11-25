<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\Order;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\MediaLibraryService;
use Throwable;

/**
 * First half of an attachment: opens a resumable Google Drive upload session in the deliverable's folder so the
 * browser can send the bytes straight to Drive. The file never touches this server's disk.
 */
class StartFileUpload
{
    use AuthorizesActor;

    public const MAX_BYTES = 536870912;

    public function __construct(private GoogleDriveService $drive, private MediaLibraryService $library) {}

    /**
     * @return string The session URL the browser uploads to.
     */
    public function __invoke(Deliverable $deliverable, string $name, string $mimeType, int $size, ?User $actor = null, ?string $origin = null): string
    {
        $this->authorizeActor($actor, 'attachFile', $deliverable);

        if (! $this->drive->isConfigured()) {
            throw WorkflowException::refused('Google Drive is not connected. Connect it in Settings → Google Drive.');
        }

        if (trim($name) === '' || $size < 1 || $size > self::MAX_BYTES) {
            throw WorkflowException::refused('Choose a file of up to :max MB.', ['max' => (int) (self::MAX_BYTES / 1048576)]);
        }

        try {
            $folderId = $this->library->folderForOrder(Order::query()->findOrFail($deliverable->getKey()));

            return $this->drive->createResumableUploadSession($name, $mimeType !== '' ? $mimeType : 'application/octet-stream', $folderId, $size, $origin);
        } catch (Throwable $exception) {
            report($exception);

            throw WorkflowException::refused('The file could not be sent to Google Drive. Try again in a moment.');
        }
    }
}
