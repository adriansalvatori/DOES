<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Order;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TrelloCardDetailsAndAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetching_trello_card_details_and_description(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/card_test_desc*' => Http::response([
                'id' => 'card_test_desc',
                'name' => 'WO 1234 EMPRESA | Tarea',
                'desc' => "## Especificaciones Técnicas\n- Tamaño: 100x200cm\n- Material: Lona Vinílica\n- Ojales en las 4 esquinas",
                'url' => 'https://trello.com/c/card_test_desc',
            ], 200),
        ]);

        $service = new TrelloSyncService;
        $result = $service->getCardDetails('card_test_desc');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['card']);
        $this->assertStringContainsString('Especificaciones Técnicas', $result['card']['desc']);
    }

    public function test_fetching_trello_card_attachments(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/card_test_attach/attachments*' => Http::response([
                [
                    'id' => 'attach_1',
                    'name' => 'medidas_arte.pdf',
                    'url' => 'https://trello-attachments.s3.amazonaws.com/medidas_arte.pdf',
                    'bytes' => 1572864,
                    'date' => '2026-09-20T10:00:00.000Z',
                    'mimeType' => 'application/pdf',
                ],
                [
                    'id' => 'attach_2',
                    'name' => 'logo_cliente.png',
                    'url' => 'https://trello-attachments.s3.amazonaws.com/logo_cliente.png',
                    'bytes' => 524288,
                    'date' => '2026-09-20T11:00:00.000Z',
                    'mimeType' => 'image/png',
                    'previews' => [
                        ['url' => 'https://trello-attachments.s3.amazonaws.com/thumb_150.png', 'width' => 150, 'height' => 150],
                    ],
                ],
            ], 200),
        ]);

        $service = new TrelloSyncService;
        $result = $service->getCardAttachments('card_test_attach');

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['attachments']);
        $this->assertEquals('medidas_arte.pdf', $result['attachments'][0]['name']);
        $this->assertEquals('logo_cliente.png', $result['attachments'][1]['name']);
    }

    public function test_order_detail_modal_loads_trello_description_and_attachments(): void
    {
        $order = Order::create([
            'company_name' => 'CLIENTE PRUEBA ATTACHMENTS',
            'task_name' => 'Impresión de Pendón',
            'trello_card_id' => 'card_full_details',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Http::fake([
            'https://api.trello.com/1/cards/card_full_details/actions*' => Http::response([], 200),
            'https://api.trello.com/1/cards/card_full_details/attachments*' => Http::response([
                [
                    'id' => 'attach_pdf',
                    'name' => 'archivo_plano.pdf',
                    'url' => 'https://trello.com/att/1.pdf',
                    'bytes' => 102400,
                    'date' => '2026-09-25T10:00:00.000Z',
                    'mimeType' => 'application/pdf',
                ],
            ], 200),
            'https://api.trello.com/1/cards/card_full_details*' => Http::response([
                'id' => 'card_full_details',
                'desc' => 'Descripción detallada de la orden en Trello',
            ], 200),
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->assertSet('isLoadingTrelloDetails', false)
            ->assertSet('trelloDescription', 'Descripción detallada de la orden en Trello')
            ->assertCount('trelloAttachments', 1)
            ->assertSee('Descripción de la Tarjeta')
            ->assertSee('Descripción detallada de la orden en Trello')
            ->assertSee('archivo_plano.pdf');
    }

    public function test_open_and_close_in_app_media_preview_modal(): void
    {
        Livewire::test(OrderDetailModal::class)
            ->call('openMediaPreview', 'https://trello-attachments.s3.amazonaws.com/image.png', 'Imagen Arte.png')
            ->assertSet('showMediaPreviewModal', true)
            ->assertSet('previewMediaTitle', 'Imagen Arte.png')
            ->assertSet('previewMediaType', 'image')
            ->call('closeMediaPreview')
            ->assertSet('showMediaPreviewModal', false);
    }
}
