<?php

namespace App\Livewire\Orders;

use App\Contracts\WorkOrderNumberGenerator;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Enums\SubtaskCategory;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Models\Substatus as SubstatusModel;
use App\Models\SubtaskPreset;
use App\Services\AutomationEngine;
use App\Services\ClientMatchingService;
use App\Services\NotificationDispatcher;
use App\Services\OrderTitleParserService;
use App\Services\SlaEngine;
use App\Services\StatusTransitionService;
use App\Services\TrelloSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class OrderDetailModal extends Component
{
    use WithFileUploads;

    public $orderId = null;

    public $showModal = false;

    public $showApprovalModal = false;

    public $pendingProductionStatus = null;

    public $showDelayModal = false;

    public $showUnblockModal = false;

    public $unblockReason = '';

    public $showBlockModal = false;

    public $blockReason = 'FALTAN MEDIDAS';

    public $blockReasonOther = '';

    public $blockComment = '';

    public $requireCustomerService = false;

    public bool $showOnHoldModal = false;

    public string $onHoldReason = '';

    public bool $showResumeModal = false;

    public string $resumeReason = '';

    public ?string $pendingResumeStatus = null;

    public bool $showArchiveModal = false;

    public string $archiveSubstatus = 'FINALIZADA !';

    public bool $archiveConfirmed = false;

    // Edit Mode state
    public $isEditing = false;

    public $editWoNumber = '';

    public $editTrelloCardId = '';

    public $editCompanyName = '';

    public $editLocationName = '';

    public $editResponsiblePerson = '';

    public $editTaskName = '';

    public $editDesignerId = null;

    public $editDesignerIds = [];

    public $editCoreStatus = '';

    public $editSubstatus = '';

    public $editDueDate = '';

    public $editClientRevisionCount = 0;

    public $editInternalRevisionCount = 0;

    // Approval fields
    public $measuresConfirmed = true;

    public $estimateApproved = true;

    public string $approvalType = 'cliente'; // 'camila' or 'cliente'

    public string $approvalComment = '';

    public $approvalImage = null;

    // Delay fields
    public $clientPromisedDate;

    public $delayReason = 'Correo de atraso enviado y nueva fecha acordada con cliente';

    // New task fields
    public $newTaskTitle = '';

    public $newTaskDate = '';

    public $newTaskIsWork = true;

    public $newTaskCategory = '';

    // Trello comments state
    public $trelloComments = [];

    public $newTrelloComment = '';

    public $isLoadingTrelloComments = false;

    public $trelloCommentError = null;

    // Trello description & attachments state
    public $trelloDescription = null;

    public $trelloAttachments = [];

    public $isLoadingTrelloDetails = false;

    public $trelloDetailsError = null;

    public $attachmentFile = null;

    public $attachmentFiles = [];

    public bool $isUploadingAttachment = false;

    public ?string $attachmentUploadError = null;

    // In-App Media Preview Modal State
    public $showMediaPreviewModal = false;

    public $previewMediaUrl = '';

    public $previewMediaTitle = '';

    public $previewMediaType = 'image'; // image, pdf, iframe

    public string $activeOrdersSortField = 'wo';

    public string $activeOrdersSortDirection = 'asc';

    public function sortByActiveOrders(string $field): void
    {
        if ($this->activeOrdersSortField === $field) {
            $this->activeOrdersSortDirection = $this->activeOrdersSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->activeOrdersSortField = $field;
            $this->activeOrdersSortDirection = 'asc';
        }
    }

    public function sortOrdersCollection($orders, string $field, string $direction)
    {
        $desc = $direction === 'desc';

        return $orders->sortBy(function ($order) use ($field) {
            return match ($field) {
                'wo' => (int) preg_replace('/\D/', '', (string) ($order->wo_number ?? 0)),
                'designer' => mb_strtolower($order->designer?->name ?? 'zzz'),
                'location' => mb_strtolower($order->location_name ?: ($order->clientLocation?->name ?? 'zzz')),
                'core_status' => mb_strtolower($order->core_status?->label() ?? 'zzz'),
                'task' => mb_strtolower($order->clean_task_name ?: ($order->task_name ?? 'zzz')),
                'substatus' => mb_strtolower($order->substatus?->label() ?? 'zzz'),
                'due_date' => $order->current_due_date ? $order->current_due_date->timestamp : 0,
                default => $order->id,
            };
        }, SORT_REGULAR, $desc)->values();
    }

    public function mount($orderId = null)
    {
        $this->orderId = $orderId;
        $this->clientPromisedDate = now()->addWeekdays(2)->toDateString();
        $this->newTaskDate = now()->toDateString();
    }

    #[On('open-order-detail')]
    public function openModal($orderId = null, $startEdit = false, $openApproval = false, $targetStatus = null)
    {
        if ($orderId) {
            $this->orderId = $orderId;
        }
        $this->showModal = true;
        $this->newTaskDate = now()->toDateString();
        $this->newTaskIsWork = true;
        $this->newTrelloComment = '';

        if ($startEdit) {
            $this->startEditing();
        } else {
            $this->isEditing = false;
        }

        $this->refreshTrelloData();

        if ($openApproval) {
            $this->openApprovalModal($targetStatus);
        }
    }

    #[On('client-updated')]
    public function onClientUpdated(): void
    {
        // Re-render when client information is updated
    }

    public function openClientDetail(?int $clientId = null): void
    {
        if ($clientId) {
            $this->dispatch('open-client-flyout', clientId: $clientId);

            return;
        }

        $targetClientId = null;
        if ($this->orderId) {
            $order = Order::find($this->orderId);
            if ($order) {
                if ($order->client_id) {
                    $targetClientId = $order->client_id;
                } else {
                    $companyNameToMatch = ! empty($order->company_name)
                        ? $order->company_name
                        : ($this->isEditing && ! empty($this->editCompanyName) ? $this->editCompanyName : null);

                    if ($companyNameToMatch) {
                        $locationNameToMatch = $order->location_name ?: ($this->isEditing ? $this->editLocationName : null);
                        $rawMatch = $companyNameToMatch.($locationNameToMatch ? ' REF '.$locationNameToMatch : '');
                        $matched = app(ClientMatchingService::class)->matchOrCreate(
                            $rawMatch,
                            $order->responsible_person ?: ($this->isEditing ? $this->editResponsiblePerson : null),
                            createIfMissing: true
                        );

                        if (! empty($matched['client'])) {
                            $targetClientId = $matched['client']->id;
                            $updateData = ['client_id' => $targetClientId];
                            if (! empty($matched['location'])) {
                                $updateData['client_location_id'] = $matched['location']->id;
                            }
                            $order->update($updateData);
                        }
                    }
                }
            }
        }

        $this->dispatch('open-client-flyout', clientId: $targetClientId);
    }

    public function refreshTrelloData()
    {
        $this->loadTrelloComments();
        $this->loadTrelloDetails();
    }

    public function loadTrelloDetails()
    {
        if (! $this->orderId) {
            $this->trelloDescription = null;
            $this->trelloAttachments = [];

            return;
        }

        $order = Order::find($this->orderId);
        if (! $order || ! $order->trello_card_id) {
            $this->trelloDescription = null;
            $this->trelloAttachments = [];
            $this->trelloDetailsError = null;

            return;
        }

        $this->isLoadingTrelloDetails = true;
        $this->trelloDetailsError = null;

        $service = app(TrelloSyncService::class);
        $cardRes = $service->getCardDetails($order->trello_card_id);
        $attachRes = $service->getCardAttachments($order->trello_card_id);

        $this->isLoadingTrelloDetails = false;

        if ($cardRes['success']) {
            $this->trelloDescription = $cardRes['card']['desc'] ?? null;
        } else {
            $this->trelloDetailsError = $cardRes['error'] ?? 'Error al obtener detalles de la tarjeta de Trello.';
        }

        if ($attachRes['success']) {
            $this->trelloAttachments = $attachRes['attachments'] ?? [];
        } else {
            $this->trelloAttachments = [];
        }
    }

    public function updatedAttachmentFile(): void
    {
        $this->uploadAttachmentToTrello();
    }

    public function updatedAttachmentFiles(): void
    {
        $this->uploadAttachmentsToTrello();
    }

    public function uploadAttachmentsToTrello(): void
    {
        $files = is_array($this->attachmentFiles) ? $this->attachmentFiles : [$this->attachmentFiles];
        $files = array_values(array_filter($files));

        if (! $this->orderId || empty($files)) {
            return;
        }

        $order = Order::find($this->orderId);
        if (! $order || ! $order->trello_card_id) {
            $this->attachmentUploadError = 'Esta orden no tiene una tarjeta de Trello vinculada.';
            $this->attachmentFiles = [];

            return;
        }

        $this->validate([
            'attachmentFiles.*' => 'file|max:10240',
        ], [
            'attachmentFiles.*.file' => __('Uno o más archivos seleccionados no son válidos.'),
            'attachmentFiles.*.max' => __('Los archivos no deben superar los 10 MB (límite de Trello).'),
        ]);

        $this->isUploadingAttachment = true;
        $this->attachmentUploadError = null;

        $authorName = auth()->user()?->name ?? 'Usuario';
        $service = app(TrelloSyncService::class);
        $successCount = 0;
        $errors = [];
        $lastSuccessFileName = '';

        foreach ($files as $file) {
            try {
                $filePath = $file->getRealPath();
                $fileName = $file->getClientOriginalName();
                $mimeType = $file->getMimeType();

                $res = $service->uploadCardAttachment(
                    cardId: $order->trello_card_id,
                    filePath: $filePath,
                    fileName: $fileName,
                    mimeType: $mimeType,
                );

                if ($res['success']) {
                    $successCount++;
                    $lastSuccessFileName = $fileName;

                    OrderEvent::create([
                        'order_id' => $order->id,
                        'event_type' => 'TRELLO_ATTACHMENT_ADDED',
                        'actor' => $authorName,
                        'previous_value' => null,
                        'new_value' => $fileName,
                        'metadata' => [
                            'file_name' => $fileName,
                            'trello_card_id' => $order->trello_card_id,
                            'attachment_id' => $res['attachment']['id'] ?? null,
                        ],
                    ]);

                    NotificationDispatcher::dispatch(
                        eventType: 'new_attachments',
                        label: 'New File',
                        order: $order,
                        detailText: $fileName
                    );
                } else {
                    $errors[] = "{$fileName}: ".($res['error'] ?? 'Error desconocido');
                }
            } catch (\Throwable $e) {
                $errors[] = "Error al subir {$file->getClientOriginalName()}: ".$e->getMessage();
            }
        }

        if ($successCount > 0) {
            $this->loadTrelloDetails();
            if ($successCount === 1) {
                $this->dispatch('toast', message: "Archivo '{$lastSuccessFileName}' adjuntado a la tarjeta de Trello exitosamente.");
            } else {
                $this->dispatch('toast', message: "Se adjuntaron {$successCount} archivos a la tarjeta de Trello exitosamente.");
            }
            $this->dispatch('order-updated');
        }

        if (! empty($errors)) {
            $this->attachmentUploadError = implode("\n", $errors);
            $this->dispatch('toast', message: 'Hubo problemas al subir algunos archivos: '.implode(', ', $errors));
        }

        $this->attachmentFiles = [];
        $this->attachmentFile = null;
        $this->isUploadingAttachment = false;
    }

    public function uploadAttachmentToTrello(): void
    {
        if (! $this->orderId || ! $this->attachmentFile) {
            return;
        }

        $order = Order::find($this->orderId);
        if (! $order || ! $order->trello_card_id) {
            $this->attachmentUploadError = 'Esta orden no tiene una tarjeta de Trello vinculada.';
            $this->attachmentFile = null;

            return;
        }

        $this->validate([
            'attachmentFile' => 'file|max:10240',
        ], [
            'attachmentFile.file' => __('El archivo seleccionado no es válido.'),
            'attachmentFile.max' => __('El archivo no debe superar los 10 MB (límite de Trello).'),
        ]);

        $this->isUploadingAttachment = true;
        $this->attachmentUploadError = null;

        try {
            $filePath = $this->attachmentFile->getRealPath();
            $fileName = $this->attachmentFile->getClientOriginalName();
            $mimeType = $this->attachmentFile->getMimeType();

            $service = app(TrelloSyncService::class);
            $res = $service->uploadCardAttachment(
                cardId: $order->trello_card_id,
                filePath: $filePath,
                fileName: $fileName,
                mimeType: $mimeType,
            );

            if ($res['success']) {
                $authorName = auth()->user()?->name ?? 'Usuario';

                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'TRELLO_ATTACHMENT_ADDED',
                    'actor' => $authorName,
                    'previous_value' => null,
                    'new_value' => $fileName,
                    'metadata' => [
                        'file_name' => $fileName,
                        'trello_card_id' => $order->trello_card_id,
                        'attachment_id' => $res['attachment']['id'] ?? null,
                    ],
                ]);

                NotificationDispatcher::dispatch(
                    eventType: 'new_attachments',
                    label: 'New File',
                    order: $order,
                    detailText: $fileName
                );

                $this->loadTrelloDetails();
                $this->dispatch('toast', message: "Archivo '{$fileName}' adjuntado a la tarjeta de Trello exitosamente.");
                $this->dispatch('order-updated');
            } else {
                $this->attachmentUploadError = $res['error'] ?? 'Error al subir el archivo a Trello.';
                $this->dispatch('toast', message: 'Error al adjuntar archivo a Trello: '.($res['error'] ?? 'Error desconocido'));
            }
        } catch (\Throwable $e) {
            $this->attachmentUploadError = $e->getMessage();
            $this->dispatch('toast', message: 'Error: '.$e->getMessage());
        } finally {
            $this->attachmentFile = null;
            $this->isUploadingAttachment = false;
        }
    }

    public function deleteAttachment(string $attachmentId, string $fileName = ''): void
    {
        if (! $this->orderId || empty($attachmentId)) {
            return;
        }

        $order = Order::find($this->orderId);
        if (! $order || ! $order->trello_card_id) {
            $this->dispatch('toast', message: 'Esta orden no tiene una tarjeta de Trello vinculada.');

            return;
        }

        try {
            $service = app(TrelloSyncService::class);
            $res = $service->deleteCardAttachment($order->trello_card_id, $attachmentId);

            if ($res['success']) {
                $authorName = auth()->user()?->name ?? 'Usuario';
                $displayName = ! empty($fileName) ? $fileName : 'Archivo adjunto';

                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'TRELLO_ATTACHMENT_DELETED',
                    'actor' => $authorName,
                    'previous_value' => $displayName,
                    'new_value' => null,
                    'metadata' => [
                        'file_name' => $displayName,
                        'trello_card_id' => $order->trello_card_id,
                        'attachment_id' => $attachmentId,
                    ],
                ]);

                $this->loadTrelloDetails();
                $this->dispatch('toast', message: "Archivo '{$displayName}' eliminado de la tarjeta de Trello.");
                $this->dispatch('order-updated');
            } else {
                $this->dispatch('toast', message: 'Error al eliminar archivo de Trello: '.($res['error'] ?? 'Error desconocido'));
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Error: '.$e->getMessage());
        }
    }

    public function downloadAllAttachments()
    {
        if (! $this->orderId || empty($this->trelloAttachments)) {
            $this->dispatch('toast', message: __('No hay archivos adjuntos para descargar.'));

            return null;
        }

        $order = Order::find($this->orderId);
        if (! $order) {
            return null;
        }

        $service = app(TrelloSyncService::class);
        $tempZip = tempnam(sys_get_temp_dir(), 'attachments_').'.zip';
        $zip = new \ZipArchive;

        if ($zip->open($tempZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->dispatch('toast', message: __('No se pudo generar el archivo comprimido.'));

            return null;
        }

        $addedCount = 0;
        $usedNames = [];

        foreach ($this->trelloAttachments as $attachment) {
            if (! is_array($attachment) || empty($attachment['url'])) {
                continue;
            }

            $rawName = $attachment['name'] ?? 'archivo';
            $cleanName = preg_replace('/[^\w\.\-\s]/u', '_', $rawName);
            if (isset($usedNames[$cleanName])) {
                $usedNames[$cleanName]++;
                $info = pathinfo($cleanName);
                $ext = ! empty($info['extension']) ? '.'.$info['extension'] : '';
                $nameInZip = ($info['filename'] ?? 'archivo').'_'.$usedNames[$cleanName].$ext;
            } else {
                $usedNames[$cleanName] = 1;
                $nameInZip = $cleanName;
            }

            $res = $service->proxyAttachment($attachment['url']);
            if ($res['success'] && ! empty($res['content'])) {
                $zip->addFromString($nameInZip, $res['content']);
                $addedCount++;
            }
        }

        $zip->close();

        if ($addedCount === 0) {
            @unlink($tempZip);
            $this->dispatch('toast', message: __('No se pudieron obtener los archivos desde Trello.'));

            return null;
        }

        $wo = ! empty($order->wo_number) ? preg_replace('/[^\w\-]/', '_', $order->wo_number) : "orden_{$order->id}";
        $zipFileName = "{$wo}_archivos.zip";

        return response()->download($tempZip, $zipFileName)->deleteFileAfterSend(true);
    }

    public function loadTrelloComments()
    {
        if (! $this->orderId) {
            $this->trelloComments = [];

            return;
        }

        $order = Order::find($this->orderId);
        if (! $order || ! $order->trello_card_id) {
            $this->trelloComments = [];
            $this->trelloCommentError = null;

            return;
        }

        $this->isLoadingTrelloComments = true;
        $this->trelloCommentError = null;

        $res = app(TrelloSyncService::class)->getCardComments($order->trello_card_id);

        $this->isLoadingTrelloComments = false;
        if ($res['success']) {
            $this->trelloComments = $res['comments'];
        } else {
            $this->trelloCommentError = $res['error'] ?? 'Error al obtener comentarios de Trello.';
        }
    }

    public function createCardOnTrello()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);

        if ($order->trello_card_id) {
            session()->flash('error', __('La orden ya está vinculada a una tarjeta de Trello.'));

            return;
        }

        $res = app(TrelloSyncService::class)->createCardOnTrello($order);

        if ($res['success']) {
            $order->refresh();
            $this->editTrelloCardId = $order->trello_card_id;
            session()->flash('message', __('Tarjeta de Trello creada y vinculada exitosamente.'));
            $this->dispatch('order-updated');
            $this->refreshTrelloData();
        } else {
            session()->flash('error', __('Error al crear la tarjeta en Trello: :error', ['error' => $res['error'] ?? __('Error desconocido')]));
        }
    }

    public function addTrelloComment()
    {
        if (! $this->orderId || empty(trim($this->newTrelloComment))) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        if (! $order->trello_card_id) {
            session()->flash('error', __('La orden no tiene una tarjeta de Trello vinculada.'));

            return;
        }

        $commentText = trim($this->newTrelloComment);
        $authorName = auth()->user()?->name;
        $res = app(TrelloSyncService::class)->addCardComment(
            cardId: $order->trello_card_id,
            text: $commentText,
            authorName: $authorName
        );

        if ($res['success']) {
            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'TRELLO_COMMENT_ADDED',
                'actor' => $authorName ?? __('Usuario'),
                'previous_value' => null,
                'new_value' => null,
                'metadata' => [
                    'comment' => $commentText,
                    'trello_card_id' => $order->trello_card_id,
                ],
            ]);

            NotificationDispatcher::dispatch(
                eventType: 'new_comment',
                label: 'New Comment',
                order: $order
            );

            $this->newTrelloComment = '';
            $this->loadTrelloComments();
            session()->flash('message', __('Comentario publicado en Trello correctamente.'));
            $this->dispatch('order-updated');
        } else {
            $this->trelloCommentError = $res['error'] ?? __('No se pudo publicar el comentario en Trello.');
            session()->flash('error', __('Error al publicar comentario en Trello: :error', ['error' => $res['error'] ?? __('Desconocido')]));
        }
    }

    public function startEditing()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::with('designers')->findOrFail($this->orderId);
        $this->editWoNumber = preg_replace('/^WO\s*/i', '', $order->wo_number ?? '');
        $this->editTrelloCardId = $order->trello_card_id ?? '';
        $this->editCompanyName = $order->company_name ?? '';
        $this->editLocationName = $order->location_name ?? '';
        $this->editResponsiblePerson = $order->responsible_person ?? '';
        $this->editTaskName = $order->task_name ?? '';
        $this->editDesignerId = $order->designer_id;
        $this->editDesignerIds = $order->designers->pluck('id')->toArray();
        if (empty($this->editDesignerIds) && $order->designer_id) {
            $this->editDesignerIds = [$order->designer_id];
        }
        $this->editCoreStatus = $order->core_status ? $order->core_status->value : CoreStatus::ENTRANTE->value;
        $this->editSubstatus = $order->substatus ? $order->substatus->value : '';
        $this->editDueDate = $order->current_due_date ? $order->current_due_date->toDateString() : '';
        $this->editClientRevisionCount = $order->client_revision_count ?? 0;
        $this->editInternalRevisionCount = $order->internal_revision_count ?? 0;

        $this->isEditing = true;
    }

    public function generateWoNumber(?WorkOrderNumberGenerator $generator = null): void
    {
        $generator ??= app(WorkOrderNumberGenerator::class);

        $this->editWoNumber = $generator->generateNextDigits([
            'company_name' => mb_strtoupper(trim($this->editCompanyName ?? ''), 'UTF-8'),
            'task_name' => mb_strtoupper(trim($this->editTaskName ?? ''), 'UTF-8'),
        ]);
    }

    public function changeCoreStatus(string $statusValue): void
    {
        if (! $this->orderId) {
            return;
        }

        $newCoreStatus = CoreStatus::tryFrom($statusValue);
        if (! $newCoreStatus) {
            return;
        }

        $order = Order::findOrFail($this->orderId);

        if ($newCoreStatus === CoreStatus::ENTRANTE) {
            $this->editCoreStatus = CoreStatus::ENTRANTE->value;
            $this->editSubstatus = Substatus::BLOQUEADA->value;
            $this->openBlockModal();

            return;
        }

        if ($newCoreStatus === CoreStatus::ON_HOLD) {
            $this->openOnHoldModal();

            return;
        }

        if ($newCoreStatus === CoreStatus::ARCHIVED) {
            $this->openArchiveModal();

            return;
        }

        $previousStatus = $order->core_status;

        if ($previousStatus === CoreStatus::ON_HOLD && $newCoreStatus !== CoreStatus::ON_HOLD && $newCoreStatus !== CoreStatus::ARCHIVED) {
            $this->openResumeModal($statusValue);

            return;
        }

        if ($newCoreStatus === CoreStatus::EN_PRODUCCION && ! $order->approved) {
            $this->openApprovalModal(CoreStatus::EN_PRODUCCION->value);

            return;
        }

        $isDoneStatus = in_array($newCoreStatus, [
            CoreStatus::ENVIADO_A_CAMILA,
            CoreStatus::ENVIADO_AL_CLIENTE,
            CoreStatus::EN_PRODUCCION,
        ], true);

        $updateData = [
            'core_status' => $newCoreStatus,
            'done_today' => $isDoneStatus ? true : $order->done_today,
        ];

        if ($newCoreStatus === CoreStatus::EN_PRODUCCION && empty($order->substatus)) {
            $updateData['substatus'] = Substatus::ENVIADO_EN_ALTA;
        }

        $order->update($updateData);

        $this->editCoreStatus = $newCoreStatus->value;
        if (isset($updateData['substatus'])) {
            $this->editSubstatus = $updateData['substatus']->value;
        }

        if ($previousStatus !== $newCoreStatus) {
            app(AutomationEngine::class)->handleStatusChanged($order->fresh(), $previousStatus, $newCoreStatus, auth()->user()?->name ?? 'Usuario');
        }

        app(AutomationEngine::class)->checkAndCreateOverdueTask($order->fresh());

        $freshOrder = $order->fresh();
        if ($freshOrder && $freshOrder->trello_card_id) {
            try {
                $pushedTitle = OrderTitleParserService::buildTitle($freshOrder);
                $success = app(TrelloSyncService::class)->updateCardOnTrello($freshOrder);
                if ($success) {
                    $freshOrder->update(['trello_title' => $pushedTitle]);
                }
            } catch (\Throwable $e) {
            }
        }

        $this->dispatch('order-updated');
        session()->flash('message', __("Estado de la orden actualizado a ':status'.", [
            'status' => $newCoreStatus->label(),
        ]));
    }

    public function toggleDesigner($id)
    {
        $id = (int) $id;
        if (in_array($id, $this->editDesignerIds)) {
            $this->editDesignerIds = array_values(array_diff($this->editDesignerIds, [$id]));
        } else {
            $this->editDesignerIds[] = $id;
        }
    }

    public function cancelEditing()
    {
        $this->isEditing = false;
    }

    public function updatedEditSubstatus($value)
    {
        if ($value === Substatus::ENVIADO_EN_ALTA->value || $value === 'ENVIADO EN ALTA') {
            $this->editCoreStatus = CoreStatus::EN_PRODUCCION->value;
        } elseif ($value === Substatus::BLOQUEADA->value || $value === 'BLOQUEADA') {
            $this->editCoreStatus = CoreStatus::ENTRANTE->value;
            $this->openBlockModal();
        } elseif ($value === Substatus::PAUSADO->value || $value === 'PAUSADO') {
            $this->editCoreStatus = CoreStatus::ON_HOLD->value;
            $this->openOnHoldModal();
        }
    }

    public function updatedEditCoreStatus($value)
    {
        $defaultSub = app(StatusTransitionService::class)->getDefaultSubstatus($value);
        if ($defaultSub) {
            $this->editSubstatus = $defaultSub->value;
        }

        if ($value === CoreStatus::ENTRANTE->value || $value === 'ENTRANTE' || $value === 'BLOCKED') {
            $this->openBlockModal();
        } elseif ($value === CoreStatus::ON_HOLD->value || $value === 'ON HOLD' || $value === 'PAUSA') {
            $this->openOnHoldModal();
        } elseif ($value === CoreStatus::ARCHIVED->value || $value === 'ARCHIVED') {
            $this->openArchiveModal();
        } else {
            $order = Order::find($this->orderId);
            if ($order && $order->core_status === CoreStatus::ON_HOLD && $value !== CoreStatus::ARCHIVED->value && $value !== 'ARCHIVED') {
                $this->openResumeModal($value);
            }
        }
    }

    #[On('open-archive-order-modal')]
    public function openArchiveForOrder(int $orderId): void
    {
        $this->orderId = $orderId;
        $this->showModal = true;
        $this->openArchiveModal();
    }

    public function openArchiveModal(): void
    {
        $this->archiveSubstatus = SubstatusModel::getDefaultArchivedSubstatus();
        $this->showArchiveModal = true;
    }

    public function closeArchiveModal(): void
    {
        $this->showArchiveModal = false;
        $this->archiveSubstatus = SubstatusModel::getDefaultArchivedSubstatus();

        if ($this->orderId) {
            $order = Order::find($this->orderId);
            if ($order) {
                $this->editCoreStatus = $order->core_status?->value ?? '';
                $this->editSubstatus = $order->substatus?->value ?? ($order->substatus ?? '');
            }
        }
    }

    public function confirmArchive(): void
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        $previousStatus = $order->core_status;
        $newCoreStatus = CoreStatus::ARCHIVED;
        $subEnum = Substatus::tryFrom($this->archiveSubstatus) ?? $this->archiveSubstatus;

        $this->showArchiveModal = false;

        if ($this->isEditing) {
            $this->archiveConfirmed = true;
            $this->editCoreStatus = CoreStatus::ARCHIVED->value;
            $this->editSubstatus = $subEnum instanceof Substatus ? $subEnum->value : (string) $subEnum;
            $this->saveOrder();
            $this->archiveConfirmed = false;

            return;
        }

        $order->update([
            'core_status' => $newCoreStatus,
            'substatus' => $subEnum,
            'archived_at' => now(),
        ]);

        $this->editCoreStatus = $newCoreStatus->value;
        $this->editSubstatus = $subEnum instanceof Substatus ? $subEnum->value : (string) $subEnum;

        if ($previousStatus !== $newCoreStatus) {
            app(AutomationEngine::class)->handleStatusChanged($order->fresh(), $previousStatus, $newCoreStatus, auth()->user()?->name ?? 'Usuario');
        }

        $freshOrder = $order->fresh();
        if ($freshOrder && $freshOrder->trello_card_id) {
            try {
                $pushedTitle = OrderTitleParserService::buildTitle($freshOrder);
                $success = app(TrelloSyncService::class)->updateCardOnTrello($freshOrder);
                if ($success) {
                    $freshOrder->update(['trello_title' => $pushedTitle]);
                }
            } catch (\Throwable $e) {
            }
        }

        $this->dispatch('order-updated');
        session()->flash('message', __('Orden archivada exitosamente con el subestatus seleccionado.'));
    }

    public function openOnHoldModal(): void
    {
        $this->onHoldReason = '';
        $this->showOnHoldModal = true;
    }

    public function closeOnHoldModal(): void
    {
        $this->showOnHoldModal = false;
        $this->onHoldReason = '';
    }

    public function confirmOnHold(): void
    {
        if (! $this->orderId) {
            return;
        }

        $this->validate([
            'onHoldReason' => 'required|string|min:3',
        ], [
            'onHoldReason.required' => __('Debes ingresar un motivo para poner la orden en On Hold.'),
            'onHoldReason.min' => __('El motivo debe tener al menos 3 caracteres.'),
        ]);

        $this->showOnHoldModal = false;

        if ($this->isEditing) {
            $this->editCoreStatus = CoreStatus::ON_HOLD->value;
            $this->editSubstatus = Substatus::PAUSADO->value;
            $this->saveOrder();

            return;
        }

        $order = Order::findOrFail($this->orderId);
        $previousStatus = $order->core_status;
        $newCoreStatus = CoreStatus::ON_HOLD;

        $order->update([
            'core_status' => $newCoreStatus,
        ]);

        $this->editCoreStatus = $newCoreStatus->value;

        if ($previousStatus !== $newCoreStatus) {
            app(AutomationEngine::class)->handleStatusChanged($order->fresh(), $previousStatus, $newCoreStatus, auth()->user()?->name ?? 'Usuario');
        }

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'MOVED_TO_ON_HOLD',
            'actor' => auth()->user()?->name ?? 'Usuario',
            'previous_value' => $previousStatus ? $previousStatus->value : null,
            'new_value' => $newCoreStatus->value,
            'metadata' => [
                'reason' => $this->onHoldReason,
                'comment' => $this->onHoldReason,
            ],
        ]);

        app(AutomationEngine::class)->checkAndCreateOverdueTask($order->fresh());

        $freshOrder = $order->fresh();
        if ($freshOrder && $freshOrder->trello_card_id) {
            try {
                $pushedTitle = OrderTitleParserService::buildTitle($freshOrder);
                $success = app(TrelloSyncService::class)->updateCardOnTrello($freshOrder);
                if ($success) {
                    $freshOrder->update(['trello_title' => $pushedTitle]);
                }
            } catch (\Throwable $e) {
            }
        }

        $this->onHoldReason = '';
        $this->openModal($this->orderId);
        $this->dispatch('order-updated');
        session()->flash('message', __('Orden :company movida a On Hold.', ['company' => $order->company_name]));
    }

    public function openResumeModal(?string $targetStatus = null): void
    {
        $this->pendingResumeStatus = $targetStatus;
        $this->resumeReason = '';
        $this->showResumeModal = true;
    }

    public function closeResumeModal(): void
    {
        $this->showResumeModal = false;
        $this->pendingResumeStatus = null;
        $this->resumeReason = '';
    }

    public function confirmResume(): void
    {
        if (! $this->orderId) {
            return;
        }

        $this->validate([
            'resumeReason' => 'required|string|min:3',
        ], [
            'resumeReason.required' => __('Debes ingresar un motivo para reanudar la orden.'),
            'resumeReason.min' => __('El motivo debe tener al menos 3 caracteres.'),
        ]);

        $this->showResumeModal = false;

        if ($this->isEditing) {
            if ($this->pendingResumeStatus) {
                $this->editCoreStatus = $this->pendingResumeStatus;
            }
            $this->saveOrder();

            return;
        }

        $order = Order::findOrFail($this->orderId);
        $previousStatus = $order->core_status;
        $newCoreStatus = CoreStatus::tryFrom($this->pendingResumeStatus) ?: CoreStatus::TO_DO_TODAY;

        $isDoneStatus = in_array($newCoreStatus, [
            CoreStatus::ENVIADO_A_CAMILA,
            CoreStatus::ENVIADO_AL_CLIENTE,
            CoreStatus::EN_PRODUCCION,
        ], true);

        $updateData = [
            'core_status' => $newCoreStatus,
            'done_today' => $isDoneStatus ? true : $order->done_today,
        ];

        if ($newCoreStatus === CoreStatus::EN_PRODUCCION && empty($order->substatus)) {
            $updateData['substatus'] = Substatus::ENVIADO_EN_ALTA;
        }

        $order->update($updateData);

        $this->editCoreStatus = $newCoreStatus->value;
        if (isset($updateData['substatus'])) {
            $this->editSubstatus = $updateData['substatus']->value;
        }

        if ($previousStatus !== $newCoreStatus) {
            app(AutomationEngine::class)->handleStatusChanged($order->fresh(), $previousStatus, $newCoreStatus, auth()->user()?->name ?? 'Usuario');
        }

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'RESUMED_FROM_ON_HOLD',
            'actor' => auth()->user()?->name ?? 'Usuario',
            'previous_value' => $previousStatus ? $previousStatus->value : null,
            'new_value' => $newCoreStatus->value,
            'metadata' => [
                'reason' => $this->resumeReason,
                'comment' => $this->resumeReason,
            ],
        ]);

        app(AutomationEngine::class)->checkAndCreateOverdueTask($order->fresh());

        $freshOrder = $order->fresh();
        if ($freshOrder && $freshOrder->trello_card_id) {
            try {
                $pushedTitle = OrderTitleParserService::buildTitle($freshOrder);
                $success = app(TrelloSyncService::class)->updateCardOnTrello($freshOrder);
                if ($success) {
                    $freshOrder->update(['trello_title' => $pushedTitle]);
                }
            } catch (\Throwable $e) {
            }
        }

        $this->resumeReason = '';
        $this->openModal($this->orderId);
        $this->dispatch('order-updated');
        session()->flash('message', __('Orden :company reanudada.', ['company' => $order->company_name]));
    }

    public function openBlockModal()
    {
        $this->blockReason = 'FALTAN MEDIDAS';
        $this->blockReasonOther = '';
        $this->blockComment = '';
        $this->requireCustomerService = false;
        $this->showBlockModal = true;
    }

    public function closeBlockModal()
    {
        $this->showBlockModal = false;
        $this->blockReason = 'FALTAN MEDIDAS';
        $this->blockReasonOther = '';
        $this->blockComment = '';
        $this->requireCustomerService = false;
    }

    public function confirmBlock()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        $order->block(
            reason: $this->blockReason,
            reasonOther: $this->blockReasonOther,
            comment: $this->blockComment,
            requireCS: (bool) $this->requireCustomerService,
            actor: 'Usuario'
        );

        $this->editCoreStatus = CoreStatus::ENTRANTE->value;
        $this->editSubstatus = Substatus::BLOQUEADA->value;
        $this->closeBlockModal();
        $this->dispatch('order-updated');
        session()->flash('message', __('Orden :company marcada como Bloqueada.', ['company' => $order->company_name]));
    }

    public function acceptPendingWo()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        if (! $order->pending_wo_number) {
            return;
        }

        $oldWo = $order->wo_number ?: 'Sin WO / WO 0000';
        $newWo = $order->pending_wo_number;

        $order->update([
            'wo_number' => $newWo,
            'pending_wo_number' => null,
        ]);

        if ($this->isEditing) {
            $this->editWoNumber = preg_replace('/^WO\s*/i', '', $newWo);
        }

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'WO_UPDATED_FROM_TRELLO',
            'actor' => auth()->user()?->name ?? 'Usuario',
            'previous_value' => $oldWo,
            'new_value' => $newWo,
            'metadata' => [
                'source' => 'Aceptado por usuario en Detalle de Tarjeta',
            ],
        ]);

        session()->flash('message', __('Número de WO actualizado a :wo (Trello) correctamente.', ['wo' => $newWo]));
        $this->dispatch('order-updated');
    }

    public function dismissPendingWo()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);

        $order->update([
            'pending_wo_number' => null,
        ]);

        $currentWoLabel = $order->wo_number ?: __('actual');
        session()->flash('message', __('Sugerencia de WO descartada. Se conserva el WO :wo (DOES).', ['wo' => $currentWoLabel]));
        $this->dispatch('order-updated');
    }

    public function openUnblockModal()
    {
        $this->unblockReason = '';
        $this->showUnblockModal = true;
    }

    public function closeUnblockModal()
    {
        $this->showUnblockModal = false;
        $this->unblockReason = '';
    }

    public function selectPresetReason($preset)
    {
        $this->unblockReason = $preset;
    }

    public function confirmUnblock()
    {
        if (! $this->orderId) {
            return;
        }

        $this->validate([
            'unblockReason' => 'required|string|min:3',
        ], [
            'unblockReason.required' => __('Ingresa el motivo o forma en que se resolvió el bloqueo.'),
            'unblockReason.min' => __('El motivo debe contener al menos 3 caracteres.'),
        ]);

        $order = Order::findOrFail($this->orderId);
        $order->unblock($this->unblockReason);

        session()->flash('message', __('Orden :company desbloqueada y devuelta a la lista del diseñador.', ['company' => $order->company_name]));

        $this->closeUnblockModal();
        $this->dispatch('order-updated');
    }

    public function saveOrder($addToWorkspace = false)
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);

        $cleanWo = trim(preg_replace('/^WO\s*/i', '', $this->editWoNumber ?? ''));

        $cleanTrelloId = trim($this->editTrelloCardId ?? '');
        if (preg_match('/trello\.com\/c\/([^\/]+)/i', $cleanTrelloId, $matches)) {
            $cleanTrelloId = $matches[1];
        }

        $this->editCompanyName = mb_strtoupper(trim($this->editCompanyName ?? ''), 'UTF-8');
        $this->editTaskName = mb_strtoupper(trim($this->editTaskName ?? ''), 'UTF-8');

        if ($this->editSubstatus === Substatus::ENVIADO_EN_ALTA->value || $this->editSubstatus === 'ENVIADO EN ALTA') {
            $this->editCoreStatus = CoreStatus::EN_PRODUCCION->value;
        } elseif ($this->editCoreStatus === CoreStatus::EN_PRODUCCION->value || $this->editCoreStatus === 'EN PRODUCCIÓN') {
            if (empty($this->editSubstatus)) {
                $this->editSubstatus = Substatus::ENVIADO_EN_ALTA->value;
            }
        }

        $previousStatus = $order->core_status;
        $newCoreStatus = ! empty($this->editCoreStatus) ? CoreStatus::tryFrom($this->editCoreStatus) : $order->core_status;

        if ($newCoreStatus === CoreStatus::EN_PRODUCCION && ! $order->approved) {
            $this->openApprovalModal(CoreStatus::EN_PRODUCCION->value);

            return;
        }

        if ($newCoreStatus === CoreStatus::ON_HOLD && $previousStatus !== CoreStatus::ON_HOLD && empty($this->onHoldReason)) {
            $this->openOnHoldModal();

            return;
        }

        if ($newCoreStatus === CoreStatus::ARCHIVED && $previousStatus !== CoreStatus::ARCHIVED && ! $this->archiveConfirmed) {
            $this->openArchiveModal();

            return;
        }

        if ($previousStatus === CoreStatus::ON_HOLD && $newCoreStatus !== CoreStatus::ON_HOLD && $newCoreStatus !== CoreStatus::ARCHIVED && empty($this->resumeReason)) {
            $this->openResumeModal($newCoreStatus->value);

            return;
        }

        $cleanLocationName = ! empty($this->editLocationName) ? mb_strtoupper(trim($this->editLocationName), 'UTF-8') : null;

        $newDueDate = ! empty($this->editDueDate) ? $this->editDueDate : null;
        $newSubstatus = ! empty($this->editSubstatus) ? $this->editSubstatus : null;

        if (empty($newDueDate)) {
            if ($newSubstatus === Substatus::OVERDUE->value || $newSubstatus === Substatus::ALMOST_OVERDUE->value) {
                $newSubstatus = null;
                $this->editSubstatus = '';
            }
            app(AutomationEngine::class)->dismissPendingOverdueTasks($order);
        }

        $isDoneStatus = in_array($newCoreStatus, [CoreStatus::ENVIADO_A_CAMILA, CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::EN_PRODUCCION], true);

        $updateData = [
            'wo_number' => ! empty($cleanWo) ? "WO {$cleanWo}" : null,
            'pending_wo_number' => null,
            'trello_card_id' => ! empty($cleanTrelloId) ? $cleanTrelloId : null,
            'company_name' => $this->editCompanyName,
            'location_name' => $cleanLocationName,
            'responsible_person' => ! empty($this->editResponsiblePerson) ? $this->editResponsiblePerson : null,
            'task_name' => $this->editTaskName,
            'designer_id' => ! empty($this->editDesignerIds) ? reset($this->editDesignerIds) : null,
            'core_status' => $newCoreStatus ?: $order->core_status,
            'substatus' => $newSubstatus,
            'current_due_date' => $newDueDate,
            'client_revision_count' => (int) $this->editClientRevisionCount,
            'internal_revision_count' => (int) $this->editInternalRevisionCount,
            'done_today' => $isDoneStatus ? true : $order->done_today,
        ];

        if (! empty($this->editCompanyName)) {
            $rawMatchString = $this->editCompanyName.($cleanLocationName ? ' REF '.$cleanLocationName : '');
            $matched = app(ClientMatchingService::class)->matchOrCreate($rawMatchString, $this->editResponsiblePerson);
            if ($matched['client']) {
                $updateData['client_id'] = $matched['client']->id;
                $updateData['company_name'] = $matched['client']->name;
            }
            if ($matched['location']) {
                $updateData['client_location_id'] = $matched['location']->id;
            }
        }

        if ($addToWorkspace) {
            $updateData['in_workspace'] = true;
        }

        $oldDueDateStr = $order->current_due_date ? $order->current_due_date->toDateString() : null;
        $previousSubstatus = $order->substatus;

        $order->update($updateData);
        $order->syncDesigners($this->editDesignerIds);

        $prevSubVal = $previousSubstatus instanceof Substatus ? $previousSubstatus->value : (string) $previousSubstatus;
        $newSubVal = $newSubstatus instanceof Substatus ? $newSubstatus->value : (string) $newSubstatus;
        if ($prevSubVal !== $newSubVal) {
            $subEnum = Substatus::tryFrom($newSubVal);
            $subLabel = $subEnum ? $subEnum->label() : ($newSubVal ?: __('Ninguno'));
            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'SUBSTATUS_CHANGED',
                'actor' => auth()->user()?->name ?? 'Usuario',
                'previous_value' => $prevSubVal ?: null,
                'new_value' => $newSubVal ?: null,
                'metadata' => [
                    'description' => __('Subestatus actualizado a: :status', ['status' => $subLabel]),
                ],
            ]);
        }

        if ($newDueDate && $newDueDate !== $oldDueDateStr) {
            app(SlaEngine::class)->updateDueDate(
                $order,
                Carbon::parse($newDueDate),
                'Manual due date update in detail modal',
                'MANUAL_UPDATE',
                null,
                auth()->user()?->name ?? 'Usuario'
            );
        }

        if ($newCoreStatus && $previousStatus !== $newCoreStatus) {
            app(AutomationEngine::class)->handleStatusChanged($order->fresh(), $previousStatus, $newCoreStatus, auth()->user()?->name ?? 'Usuario');

            if ($newCoreStatus === CoreStatus::ON_HOLD && ! empty($this->onHoldReason)) {
                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'MOVED_TO_ON_HOLD',
                    'actor' => auth()->user()?->name ?? 'Usuario',
                    'previous_value' => $previousStatus ? $previousStatus->value : null,
                    'new_value' => $newCoreStatus->value,
                    'metadata' => [
                        'reason' => $this->onHoldReason,
                        'comment' => $this->onHoldReason,
                    ],
                ]);
            }

            if ($previousStatus === CoreStatus::ON_HOLD && $newCoreStatus !== CoreStatus::ON_HOLD && ! empty($this->resumeReason)) {
                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'RESUMED_FROM_ON_HOLD',
                    'actor' => auth()->user()?->name ?? 'Usuario',
                    'previous_value' => $previousStatus ? $previousStatus->value : null,
                    'new_value' => $newCoreStatus->value,
                    'metadata' => [
                        'reason' => $this->resumeReason,
                        'comment' => $this->resumeReason,
                    ],
                ]);
            }
        }

        // Trigger overdue task creation immediately if updated date is today or overdue
        app(AutomationEngine::class)->checkAndCreateOverdueTask($order->fresh());

        // Safely sync updated title to Trello if card is linked
        $freshOrder = $order->fresh();
        if ($freshOrder && $freshOrder->trello_card_id) {
            $pushedTitle = OrderTitleParserService::buildTitle($freshOrder);
            $success = app(TrelloSyncService::class)->updateCardOnTrello($freshOrder);
            if ($success) {
                $freshOrder->update(['trello_title' => $pushedTitle]);
            }
        }

        $this->isEditing = false;
        $this->dispatch('order-updated');

        if ($addToWorkspace) {
            session()->flash('message', __('Orden :company actualizada y añadida al Workspace activo.', ['company' => $order->company_name]));
        } else {
            session()->flash('message', __('Orden :company actualizada correctamente.', ['company' => $order->company_name]));
        }
    }

    public function duplicateCurrentOrder()
    {
        if (! $this->orderId) {
            return;
        }

        $original = Order::findOrFail($this->orderId);

        $newOrder = Order::create([
            'wo_number' => $original->wo_number ? "{$original->wo_number} (Copia)" : null,
            'company_name' => $original->company_name,
            'task_name' => "{$original->task_name} (Copia)",
            'responsible_person' => $original->responsible_person,
            'designer_id' => $original->designer_id,
            'core_status' => $original->core_status,
            'substatus' => $original->substatus,
            'current_due_date' => $original->current_due_date,
            'in_workspace' => true,
        ]);

        OrderEvent::create([
            'order_id' => $newOrder->id,
            'event_type' => 'ORDER_DUPLICATED',
            'actor' => auth()->user()?->name ?? __('Usuario'),
            'previous_value' => null,
            'new_value' => $newOrder->core_status?->value,
            'metadata' => [
                'duplicated_from_id' => $original->id,
                'comment' => "Duplicada a partir de la orden #{$original->id} ({$original->company_name})",
            ],
        ]);

        $this->orderId = $newOrder->id;
        $this->dispatch('order-updated');
        session()->flash('message', __('Orden \':company\' duplicada correctamente.', ['company' => $newOrder->company_name]));
    }

    public function toggleDoneToday()
    {
        if (! $this->orderId) {
            return;
        }
        $order = Order::findOrFail($this->orderId);
        $newDoneToday = ! $order->done_today;
        $order->update(['done_today' => $newDoneToday]);
        if ($newDoneToday) {
            app(AutomationEngine::class)->dismissPendingOverdueTasks($order);
        }
        $this->dispatch('order-updated');
    }

    public function clearDueDate()
    {
        if (! $this->orderId) {
            return;
        }
        $order = Order::findOrFail($this->orderId);
        $updateData = ['current_due_date' => null];
        if ($order->substatus === Substatus::OVERDUE || $order->substatus === Substatus::ALMOST_OVERDUE) {
            $updateData['substatus'] = null;
            $this->editSubstatus = '';
        }
        $order->update($updateData);
        $this->editDueDate = '';
        app(AutomationEngine::class)->dismissPendingOverdueTasks($order);
        $this->dispatch('order-updated');
        session()->flash('message', __('Fecha límite de :company establecida a Ninguna (Sin Fecha).', ['company' => $order->company_name]));
    }

    public function openMediaPreview(string $url, string $title = 'Archivo')
    {
        $url = trim($url);
        if (empty($url)) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $isTrelloResource = false;
        if ($host) {
            $allowedHosts = ['trello.com', 'api.trello.com', 'trello-attachments.s3.amazonaws.com', 'trello-members.s3.amazonaws.com'];
            foreach ($allowedHosts as $allowed) {
                if ($host === $allowed || str_ends_with((string) $host, '.'.$allowed)) {
                    $isTrelloResource = true;
                    break;
                }
            }
        }

        if ($isTrelloResource) {
            $finalUrl = route('trello.attachment-proxy', ['url' => $url]);
        } else {
            $finalUrl = $url;
        }

        $cleanPath = parse_url($url, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'])) {
            $type = 'image';
        } elseif ($extension === 'pdf') {
            $type = 'pdf';
        } else {
            $type = 'iframe';
        }

        $this->previewMediaUrl = $finalUrl;
        $this->previewMediaTitle = $title;
        $this->previewMediaType = $type;
        $this->showMediaPreviewModal = true;
    }

    public function previewMedia(string $url, string $title = 'Archivo', ?string $type = null): void
    {
        $this->openMediaPreview($url, $title);
        if ($type) {
            $this->previewMediaType = $type;
        }
    }

    public function closeMediaPreview()
    {
        $this->showMediaPreviewModal = false;
        $this->previewMediaUrl = '';
        $this->previewMediaTitle = '';
        $this->previewMediaType = 'image';
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showApprovalModal = false;
        $this->showDelayModal = false;
        $this->showMediaPreviewModal = false;
        $this->isEditing = false;
        $this->newTrelloComment = '';
        $this->approvalComment = '';
        $this->approvalImage = null;
        $this->attachmentFile = null;
        $this->attachmentFiles = [];
        $this->attachmentUploadError = null;
        $this->isUploadingAttachment = false;
    }

    public function moveToBacklog()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        $order->update(['in_workspace' => false]);
        $this->closeModal();
        $this->dispatch('order-updated');
        session()->flash('message', __('Orden :company movida de regreso al Backlog.', ['company' => $order->company_name]));
    }

    public function deleteOrder()
    {
        if (! $this->orderId) {
            return;
        }

        $user = Auth::user();
        if ($user && ($user->isDesigner() || $user->isSales())) {
            abort(403, __('No tiene permisos para enviar órdenes a la papelera.'));
        }

        $order = Order::findOrFail($this->orderId);
        $name = $order->company_name ?? __('Orden');

        $order->delete();

        $this->closeModal();
        $this->dispatch('order-updated');
        session()->flash('message', __('Orden \':name\' movida a la Papelera de Reciclaje.', ['name' => $name]));
    }

    public function addToWorkspaceDirectly()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        $updateData = ['in_workspace' => true];

        if ($order->company_name) {
            $rawMatch = $order->company_name.($order->location_name ? ' REF '.$order->location_name : '');
            $match = app(ClientMatchingService::class)->matchOrCreate($rawMatch, $order->responsible_person, createIfMissing: true);
            if ($match['client']) {
                $updateData['client_id'] = $match['client']->id;
                $updateData['company_name'] = $match['client']->name;
            }
            if ($match['location']) {
                $updateData['client_location_id'] = $match['location']->id;
            }
        }

        $order->update($updateData);
        $this->dispatch('order-updated');
        session()->flash('message', __('Orden :company añadida al Workspace activo.', ['company' => $order->company_name]));
    }

    public function openApprovalModal(?string $targetStatus = null): void
    {
        $this->resetErrorBag();
        $this->pendingProductionStatus = $targetStatus;
        if ($this->orderId) {
            $order = Order::find($this->orderId);
            $this->approvalType = ($order && $order->core_status === CoreStatus::ENVIADO_A_CAMILA) ? 'camila' : 'cliente';
        } else {
            $this->approvalType = 'cliente';
        }
        $this->approvalComment = '';
        $this->approvalImage = null;
        $this->measuresConfirmed = true;
        $this->estimateApproved = true;
        $this->showApprovalModal = true;
    }

    public function closeApprovalModal(): void
    {
        $this->showApprovalModal = false;
        $this->pendingProductionStatus = null;
        $this->approvalComment = '';
        $this->approvalImage = null;
        $this->resetErrorBag();
    }

    public function removeApprovalImage(): void
    {
        $this->approvalImage = null;
    }

    public function submitApproval()
    {
        if (! $this->orderId) {
            return;
        }

        $this->resetErrorBag();

        $hasComment = ! empty(trim((string) $this->approvalComment));
        $hasImage = (bool) $this->approvalImage;

        if (! $hasComment && ! $hasImage) {
            $this->addError('approvalSupport', __('Debes ingresar un comentario o adjuntar una imagen como soporte de la aprobación.'));

            return;
        }

        $this->validate([
            'approvalType' => 'required|in:camila,cliente',
            'approvalImage' => 'nullable|image|max:12288',
        ], [
            'approvalType.required' => __('Debes seleccionar quién aprobó el diseño.'),
            'approvalImage.image' => __('El archivo adjunto debe ser una imagen válida.'),
            'approvalImage.max' => __('La imagen no debe superar los 12MB.'),
        ]);

        $order = Order::findOrFail($this->orderId);

        $imagePath = null;
        if ($this->approvalImage) {
            $imagePath = $this->approvalImage->store('approvals', 'public');
        }

        $targetStatus = ($this->pendingProductionStatus === CoreStatus::EN_PRODUCCION->value || $this->pendingProductionStatus === 'EN PRODUCCIÓN')
            ? CoreStatus::EN_PRODUCCION
            : null;

        app(AutomationEngine::class)->processApproval(
            $order,
            (bool) $this->measuresConfirmed,
            (bool) $this->estimateApproved,
            $this->approvalType,
            trim((string) $this->approvalComment) ?: null,
            $imagePath,
            $targetStatus
        );

        $this->showApprovalModal = false;
        $this->pendingProductionStatus = null;
        $this->closeModal();
        $this->dispatch('order-updated');
        session()->flash('message', __('Aprobación procesada para :company.', ['company' => $order->company_name]));
    }

    public function submitDelayResolution()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);

        app(AutomationEngine::class)->resolveDelay(
            $order,
            Carbon::parse($this->clientPromisedDate),
            $this->delayReason
        );

        $this->showDelayModal = false;
        $this->dispatch('order-updated');
        session()->flash('message', __('Atraso resuelto y nueva fecha fijada al :date.', ['date' => $this->clientPromisedDate]));
    }

    public function addTask()
    {
        if (empty(trim($this->newTaskTitle)) || ! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        $taskDate = ! empty($this->newTaskDate) ? Carbon::parse($this->newTaskDate) : now();
        $isWork = (bool) $this->newTaskIsWork;
        $rawTitle = trim($this->newTaskTitle);

        $preset = SubtaskPreset::where('title', $rawTitle)->first();

        $category = ! empty($this->newTaskCategory)
            ? SubtaskCategory::tryFrom($this->newTaskCategory) ?? ($preset?->category ?? SubtaskCategory::detectFromContext($rawTitle, $order))
            : ($preset?->category ?? SubtaskCategory::detectFromContext($rawTitle, $order));

        if ($preset && $preset->is_work_task !== null) {
            $isWork = (bool) $preset->is_work_task;
        }

        if ($category === SubtaskCategory::MANAGEMENT || str_contains(strtolower($rawTitle), 'follow up')) {
            $isWork = false;
        }

        $returnStatus = $category->defaultReturnCoreStatus() ?? ($order->core_status !== CoreStatus::TO_DO_TODAY ? $order->core_status : null);

        $subtask = $order->relatedTasks()->create([
            'title' => $rawTitle,
            'type' => RelatedTaskType::RESOLVER,
            'category' => $category,
            'return_core_status' => $returnStatus,
            'status' => 'todo',
            'assignee_id' => $order->getPrimaryDesignerId(),
            'scheduled_date' => $taskDate->toDateString(),
            'due_date' => $taskDate->toDateString(),
            'priority' => 'normal',
            'is_work_task' => $isWork,
        ]);

        if ($isWork && $taskDate->isToday()) {
            if ($order->core_status === CoreStatus::ARCHIVED) {
                $order->update([
                    'scheduled_date' => $taskDate->toDateString(),
                    'core_status' => CoreStatus::TO_DO_TODAY,
                    'substatus' => Substatus::TICKET,
                    'archived_at' => null,
                ]);
            } elseif ($order->core_status !== CoreStatus::ON_HOLD && $order->core_status !== CoreStatus::EN_PRODUCCION) {
                $previousStatus = $order->core_status;
                $updateData = [
                    'scheduled_date' => $taskDate->toDateString(),
                    'core_status' => CoreStatus::TO_DO_TODAY,
                ];
                if (! $order->origin_core_status && $previousStatus !== CoreStatus::TO_DO_TODAY) {
                    $updateData['origin_core_status'] = $previousStatus;
                    $updateData['origin_substatus'] = $order->substatus;
                }
                if ($category->triggerSubstatus()) {
                    $updateData['substatus'] = $category->triggerSubstatus();
                } elseif ($previousStatus === CoreStatus::ENVIADO_A_CAMILA) {
                    $updateData['substatus'] = Substatus::CAMBIOS_CAMILA;
                } elseif ($previousStatus === CoreStatus::ENVIADO_AL_CLIENTE) {
                    $updateData['substatus'] = Substatus::CAMBIOS_CLIENTE;
                }
                $order->update($updateData);
            }
        }

        $this->newTaskTitle = '';
        $this->newTaskCategory = '';
        $this->dispatch('order-updated');
    }

    public function updateTaskCategory(int|string $taskId, string $categoryValue): void
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $cat = SubtaskCategory::tryFrom($categoryValue) ?? SubtaskCategory::NEW_DESIGN;
            $task->update([
                'category' => $cat,
                'return_core_status' => $cat->defaultReturnCoreStatus() ?? $task->return_core_status,
            ]);
            $this->dispatch('order-updated');
            session()->flash('message', __('Categoría de subtarea actualizada a :cat.', ['cat' => $cat->label()]));
        }
    }

    public function updateTaskTitle($taskId, $newTitle)
    {
        $newTitle = trim((string) $newTitle);
        if (empty($newTitle)) {
            return;
        }

        $task = RelatedTask::find($taskId);
        if ($task) {
            $task->update(['title' => $newTitle]);
            $this->dispatch('order-updated');
            session()->flash('message', __('Subtarea actualizada correctamente.'));
        }
    }

    public function updateTaskType(int|string $taskId, bool $isWork): void
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $task->update(['is_work_task' => $isWork]);
            $this->dispatch('order-updated');
            session()->flash('message', __('Tipo de subtarea actualizado a :type.', ['type' => $isWork ? __('Trabajo') : __('Gestión')]));
        }
    }

    public function toggleTaskType(int|string $taskId): void
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $newIsWork = ! ($task->is_work_task ?? true);
            $task->update(['is_work_task' => $newIsWork]);
            $this->dispatch('order-updated');
            session()->flash('message', __('Tipo de subtarea actualizado a :type.', ['type' => $newIsWork ? __('Trabajo') : __('Gestión')]));
        }
    }

    public function updateTaskDate(int|string $taskId, ?string $date = null): void
    {
        $task = RelatedTask::with('order')->find($taskId);
        if ($task) {
            $parsedDate = ! empty($date) ? Carbon::parse($date)->toDateString() : null;
            $task->update([
                'scheduled_date' => $parsedDate,
                'due_date' => $parsedDate ?? $task->due_date,
            ]);

            if ($parsedDate && $task->is_work_task && Carbon::parse($parsedDate)->isToday() && $task->order) {
                $order = $task->order;
                if ($order->core_status !== CoreStatus::ON_HOLD && $order->core_status !== CoreStatus::EN_PRODUCCION && $order->core_status !== CoreStatus::ARCHIVED) {
                    $previousStatus = $order->core_status;
                    $updateData = [
                        'scheduled_date' => $parsedDate,
                        'core_status' => CoreStatus::TO_DO_TODAY,
                    ];
                    if (! $order->origin_core_status && $previousStatus !== CoreStatus::TO_DO_TODAY) {
                        $updateData['origin_core_status'] = $previousStatus;
                        $updateData['origin_substatus'] = $order->substatus;
                    }
                    $category = $task->category;
                    if ($category->triggerSubstatus()) {
                        $updateData['substatus'] = $category->triggerSubstatus();
                    } elseif ($previousStatus === CoreStatus::ENVIADO_A_CAMILA) {
                        $updateData['substatus'] = Substatus::CAMBIOS_CAMILA;
                    } elseif ($previousStatus === CoreStatus::ENVIADO_AL_CLIENTE) {
                        $updateData['substatus'] = Substatus::CAMBIOS_CLIENTE;
                    }
                    $order->update($updateData);
                }
            }

            $this->dispatch('order-updated');
            session()->flash('message', $parsedDate ? __('Fecha de la subtarea actualizada para el :date.', ['date' => Carbon::parse($parsedDate)->format('d M')]) : __('Fecha de la subtarea eliminada.'));
        }
    }

    public function updateTaskAssignee(int|string $taskId, int|string|null $designerId = null): void
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $assigneeId = ! empty($designerId) ? (int) $designerId : null;
            $task->update(['assignee_id' => $assigneeId]);
            $this->dispatch('order-updated');

            $designerName = $assigneeId ? (Designer::find($assigneeId)?->name ?? __('Diseñador')) : __('Sin asignar');
            session()->flash('message', __('Responsable de la subtarea actualizado: :designer.', ['designer' => $designerName]));
        }
    }

    public function deleteTask($taskId)
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $taskName = $task->title;
            $task->delete();
            $this->dispatch('order-updated');
            session()->flash('message', __('Tarea \':task\' eliminada.', ['task' => $taskName]));
        }
    }

    public function dismissTask($taskId)
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $taskName = $task->title;
            $task->forceDelete();
            $this->dispatch('order-updated');
            session()->flash('message', __('Subtarea \':task\' descartada.', ['task' => $taskName]));
        }
    }

    public function toggleTaskStatus($taskId)
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $order = $task->order;
            $willBeDone = ! $task->isDone();

            if ($order && $willBeDone && $task->isPonerEnAlta()) {
                if (! $order->approved) {
                    $this->openApprovalModal(CoreStatus::EN_PRODUCCION->value);
                    session()->flash('warning', __('La orden requiere aprobación antes de ser enviada a producción.'));

                    return;
                }
            }

            if ($task->isDone()) {
                $task->update(['status' => 'todo', 'completed_at' => null]);
                if ($task->order_id) {
                    OrderEvent::where('order_id', $task->order_id)
                        ->where('event_type', 'SUBTASK_COMPLETED')
                        ->where(function ($q) use ($task) {
                            $q->where('metadata->task_id', $task->id)
                                ->orWhere('new_value', $task->title);
                        })
                        ->delete();
                }
            } else {
                $task->update(['status' => 'done', 'completed_at' => now()]);
                if ($task->order_id) {
                    OrderEvent::create([
                        'order_id' => $task->order_id,
                        'event_type' => 'SUBTASK_COMPLETED',
                        'actor' => auth()->user()?->name ?? 'Usuario',
                        'new_value' => $task->title,
                        'metadata' => [
                            'task_id' => $task->id,
                            'task_title' => $task->title,
                            'date' => $task->scheduled_date?->toDateString(),
                        ],
                    ]);

                    if ($task->isPonerEnAlta() && $order && $order->approved && $order->core_status !== CoreStatus::EN_PRODUCCION) {
                        $previousStatus = $order->core_status;
                        app(AutomationEngine::class)->handleStatusChanged(
                            $order,
                            $previousStatus,
                            CoreStatus::EN_PRODUCCION,
                            auth()->user()?->name ?? 'Usuario'
                        );
                        $order->update([
                            'substatus' => Substatus::ENVIADO_EN_ALTA,
                            'done_today' => true,
                            'origin_core_status' => null,
                            'origin_substatus' => null,
                        ]);

                        OrderEvent::create([
                            'order_id' => $order->id,
                            'event_type' => 'MOVED_TO_PRODUCTION',
                            'actor' => auth()->user()?->name ?? 'Usuario',
                            'previous_value' => $previousStatus?->value,
                            'new_value' => CoreStatus::EN_PRODUCCION->value,
                            'metadata' => [
                                'trigger' => 'Subtarea Poner en Alta completada',
                                'task_id' => $task->id,
                                'task_title' => $task->title,
                            ],
                        ]);
                    }
                }
            }
            $this->dispatch('order-updated');
        }
    }

    public function render()
    {
        if (! $this->showModal) {
            return view('livewire.orders.order-detail-modal', [
                'order' => null,
                'clientOtherActiveOrders' => collect(),
                'designers' => collect(),
                'subtaskPresets' => collect(),
                'coreStatuses' => [],
                'substatuses' => [],
                'existingCompanies' => collect(),
                'existingResponsibles' => collect(),
                'existingLocations' => collect(),
                'clientLocations' => [],
                'clientContacts' => [],
                'availableTrelloCards' => collect(),
            ]);
        }

        $order = $this->orderId
            ? Order::with(['designer', 'client.locations', 'client.contacts', 'relatedTasks.assignee', 'events', 'dueDateHistories'])->find($this->orderId)
            : null;

        $client = ! empty($this->editCompanyName)
            ? Client::with(['locations', 'contacts'])->where('name', mb_strtoupper(trim($this->editCompanyName), 'UTF-8'))->first()
            : ($order?->client ?? null);

        $clientLocations = $client ? $client->locations->pluck('name')->filter()->toArray() : [];
        $clientContacts = $client ? $client->contacts->pluck('name')->filter()->toArray() : [];

        $validSubstatuses = app(StatusTransitionService::class)->getValidSubstatuses($this->editCoreStatus ?: $order?->core_status);

        $clientOtherActiveOrders = collect();
        if ($order && $order->client_id) {
            $rawOtherOrders = Order::where('client_id', $order->client_id)
                ->where('id', '!=', $order->id)
                ->where('in_workspace', true)
                ->where('core_status', '!=', CoreStatus::ARCHIVED)
                ->with(['designer', 'clientLocation'])
                ->get();
            $clientOtherActiveOrders = $this->sortOrdersCollection($rawOtherOrders, $this->activeOrdersSortField, $this->activeOrdersSortDirection);
        }

        return view('livewire.orders.order-detail-modal', [
            'order' => $order,
            'clientOtherActiveOrders' => $clientOtherActiveOrders,
            'designers' => Designer::where('active', true)->get(),
            'subtaskPresets' => SubtaskPreset::where('is_active', true)->orderBy('sort_order')->get(),
            'coreStatuses' => CoreStatus::cases(),
            'substatuses' => $validSubstatuses,
            'archivedSubstatuses' => SubstatusModel::getArchivedSubstatuses(),
            'existingCompanies' => Order::inWorkspace()
                ->whereNotNull('company_name')
                ->where('company_name', '!=', '')
                ->distinct()
                ->orderBy('company_name')
                ->pluck('company_name'),
            'existingResponsibles' => Order::inWorkspace()
                ->whereNotNull('responsible_person')
                ->where('responsible_person', '!=', '')
                ->distinct()
                ->orderBy('responsible_person')
                ->pluck('responsible_person'),
            'existingLocations' => Order::whereNotNull('location_name')
                ->where('location_name', '!=', '')
                ->distinct()
                ->orderBy('location_name')
                ->pluck('location_name'),
            'clientLocations' => $clientLocations,
            'clientContacts' => $clientContacts,
            'availableTrelloCards' => Order::whereNotNull('trello_card_id')
                ->where('trello_card_id', '!=', '')
                ->orderBy('trello_created_at', 'desc')
                ->take(100)
                ->get(['id', 'trello_card_id', 'company_name', 'task_name', 'trello_title', 'wo_number']),
        ]);
    }
}
