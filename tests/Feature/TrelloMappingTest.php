<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Settings\TrelloMapping;
use App\Models\TrelloListMapping;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrelloMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_trello_mapping_page_renders_successfully(): void
    {
        Livewire::test(TrelloMapping::class)
            ->assertStatus(200);
    }

    public function test_saving_trello_list_mappings_persists_in_database(): void
    {
        Livewire::test(TrelloMapping::class)
            ->set('mappings.'.CoreStatus::ENVIADO_AL_CLIENTE->value, 'trello_list_custom_777')
            ->call('saveMappings')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('trello_list_mappings', [
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE->value,
            'trello_list_id' => 'trello_list_custom_777',
        ]);
    }

    public function test_trello_sync_service_uses_custom_mappings(): void
    {
        TrelloListMapping::create([
            'core_status' => CoreStatus::EN_PRODUCCION,
            'trello_list_id' => 'trello_list_prod_999',
            'trello_list_name' => 'Custom Production List',
        ]);

        $service = new TrelloSyncService;

        $mappedStatus = $service->mapListToCoreStatus('Random Unmatched Name', 'trello_list_prod_999');
        $this->assertEquals(CoreStatus::EN_PRODUCCION, $mappedStatus);

        $resolvedListId = $service->getTrelloListIdForCoreStatus(CoreStatus::EN_PRODUCCION);
        $this->assertEquals('trello_list_prod_999', $resolvedListId);
    }
}
