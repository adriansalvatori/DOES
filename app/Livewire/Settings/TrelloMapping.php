<?php

namespace App\Livewire\Settings;

use App\Enums\CoreStatus;
use App\Models\TrelloListMapping;
use App\Services\TrelloSyncService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Mapeo de Listas Trello - Kudos Design Ops')]
class TrelloMapping extends Component
{
    public string $boardId = '';

    public string $userToken = '';

    public string $apiKey = '';

    public array $trelloLists = [];

    public array $mappings = []; // core_status_value => trello_list_id

    public function mount(): void
    {
        $this->apiKey = config('services.trello.api_key', env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f'));
        $this->userToken = config('services.trello.token', env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET', '')));
        $this->boardId = config('services.trello.board_id', env('TRELLO_BOARD_ID', '597266b10db2cbf2568cda54'));

        $this->loadTrelloLists();
        $this->loadSavedMappings();
    }

    public function loadTrelloLists(): void
    {
        $boardId = ! empty(trim($this->boardId)) ? $this->boardId : env('TRELLO_BOARD_ID', '597266b10db2cbf2568cda54');
        $apiKey = ! empty(trim($this->apiKey)) ? $this->apiKey : env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f');
        $userToken = ! empty(trim($this->userToken)) ? $this->userToken : env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET', ''));

        $syncService = app(TrelloSyncService::class);
        $res = $syncService->getBoardLists($boardId, $apiKey, $userToken);

        if ($res['success']) {
            $this->trelloLists = $res['data'];
        }
    }

    public function loadSavedMappings(): void
    {
        $saved = TrelloListMapping::all()->keyBy(fn ($m) => $m->core_status->value);
        $syncService = app(TrelloSyncService::class);

        foreach (CoreStatus::cases() as $status) {
            $val = $status->value;

            if (isset($saved[$val]) && ! empty($saved[$val]->trello_list_id)) {
                $this->mappings[$val] = $saved[$val]->trello_list_id;
            } else {
                // Pre-populate dropdown with auto-detected Trello list ID from board
                $matchedId = '';
                foreach ($this->trelloLists as $list) {
                    if ($syncService->mapListToCoreStatus($list['name'] ?? '') === $status) {
                        $matchedId = $list['id'];
                        break;
                    }
                }
                $this->mappings[$val] = $matchedId;
            }
        }
    }

    public function autoDetectMappings(): void
    {
        if (empty($this->trelloLists)) {
            $this->loadTrelloLists();
        }

        $syncService = app(TrelloSyncService::class);

        foreach (CoreStatus::cases() as $status) {
            $val = $status->value;
            $foundId = '';
            foreach ($this->trelloLists as $list) {
                if ($syncService->mapListToCoreStatus($list['name'] ?? '') === $status) {
                    $foundId = $list['id'];
                    break;
                }
            }
            if ($foundId) {
                $this->mappings[$val] = $foundId;
            }
        }

        session()->flash('message', 'Mapeo detectado automáticamente basado en los nombres de las listas de Trello.');
    }

    public function saveMappings(): void
    {
        $listsById = collect($this->trelloLists)->keyBy('id');

        foreach (CoreStatus::cases() as $status) {
            $val = $status->value;
            $listId = $this->mappings[$val] ?? null;
            $listName = $listId && isset($listsById[$listId]) ? $listsById[$listId]['name'] : null;

            TrelloListMapping::updateOrCreate(
                ['core_status' => $val],
                [
                    'trello_list_id' => ! empty($listId) ? $listId : null,
                    'trello_list_name' => $listName,
                ]
            );
        }

        session()->flash('message', 'Mapeo de listas guardado exitosamente.');
        $this->dispatch('mappings-saved');
    }

    public function setMapping(string $coreStatusVal, string $trelloListId): void
    {
        $this->mappings[$coreStatusVal] = $trelloListId;
    }

    public function render()
    {
        return view('livewire.settings.trello-mapping', [
            'statuses' => CoreStatus::cases(),
        ]);
    }
}
