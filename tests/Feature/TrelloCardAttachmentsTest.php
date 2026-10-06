<?php

namespace Tests\Feature;

use App\Livewire\Orders\OrderDetailModal;
use App\Models\Order;
use App\Models\User;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class TrelloCardAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_trello_card_attachment_via_service(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/card_test_123/attachments*' => Http::response([
                'id' => 'attach_test_1',
                'name' => 'banner_final.png',
                'bytes' => 10240,
                'url' => 'https://trello.com/1/cards/card_test_123/attachments/attach_test_1/download/banner_final.png',
            ], 200),
        ]);

        Storage::fake('local');
        $tmpFile = UploadedFile::fake()->image('banner_final.png');

        $service = new TrelloSyncService;
        $result = $service->uploadCardAttachment(
            cardId: 'card_test_123',
            filePath: $tmpFile->getRealPath(),
            fileName: 'banner_final.png',
            mimeType: 'image/png',
            apiKey: 'fake_key',
            apiToken: 'fake_token'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('attach_test_1', $result['attachment']['id']);
        $this->assertEquals('banner_final.png', $result['attachment']['name']);

        Http::assertSent(function (Request $request) {
            return str_starts_with($request->url(), 'https://api.trello.com/1/cards/card_test_123/attachments')
                && $request->isMultipart()
                && str_contains($request->url(), 'key=fake_key')
                && str_contains($request->url(), 'token=fake_token');
        });
    }

    public function test_uploading_trello_card_attachment_handles_trello_error(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/card_test_123/attachments*' => Http::response('File too large', 400),
        ]);

        Storage::fake('local');
        $tmpFile = UploadedFile::fake()->create('big_file.zip', 100);

        $service = new TrelloSyncService;
        $result = $service->uploadCardAttachment(
            cardId: 'card_test_123',
            filePath: $tmpFile->getRealPath(),
            fileName: 'big_file.zip',
            apiKey: 'fake_key',
            apiToken: 'fake_token'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('File too large', $result['error']);
    }

    public function test_order_detail_modal_uploads_attachment_and_records_event(): void
    {
        $user = User::factory()->create([
            'name' => 'Adrián Salvatori',
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $order = Order::create([
            'company_name' => 'Empresa Adjuntos',
            'task_name' => 'Diseño de Fachada',
            'trello_card_id' => 'card_attach_modal_1',
            'in_workspace' => true,
        ]);

        Http::fake([
            'https://api.trello.com/1/cards/card_attach_modal_1/attachments*' => Http::sequence()
                // Initial load
                ->push([], 200)
                // Post upload
                ->push([
                    'id' => 'attach_modal_uploaded',
                    'name' => 'plano_fachada.pdf',
                    'bytes' => 54321,
                    'url' => 'https://trello.com/1/cards/card_attach_modal_1/attachments/attach_modal_uploaded/download/plano_fachada.pdf',
                ], 200)
                // Reload list after upload
                ->push([
                    [
                        'id' => 'attach_modal_uploaded',
                        'name' => 'plano_fachada.pdf',
                        'bytes' => 54321,
                        'url' => 'https://trello.com/1/cards/card_attach_modal_1/attachments/attach_modal_uploaded/download/plano_fachada.pdf',
                        'mimeType' => 'application/pdf',
                        'date' => now()->toISOString(),
                    ],
                ], 200),
            'https://api.trello.com/1/cards/card_attach_modal_1*' => Http::response([
                'id' => 'card_attach_modal_1',
                'name' => 'Empresa Adjuntos - Fachada',
                'desc' => 'Descripción de prueba',
            ], 200),
        ]);

        Storage::fake('tmp-for-tests');
        $fakePdf = UploadedFile::fake()->create('plano_fachada.pdf', 500, 'application/pdf');

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->assertSet('trelloAttachments', [])
            ->set('attachmentFile', $fakePdf)
            ->assertDispatched('toast')
            ->assertDispatched('order-updated')
            ->assertSet('attachmentFile', null)
            ->assertCount('trelloAttachments', 1);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'TRELLO_ATTACHMENT_ADDED',
            'new_value' => 'plano_fachada.pdf',
            'actor' => 'Adrián Salvatori',
        ]);
    }

    public function test_deleting_trello_card_attachment_via_service(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/card_del_123/attachments/attach_del_456*' => Http::response([
                '_value' => null,
            ], 200),
        ]);

        $service = new TrelloSyncService;
        $result = $service->deleteCardAttachment(
            cardId: 'card_del_123',
            attachmentId: 'attach_del_456',
            apiKey: 'fake_key',
            apiToken: 'fake_token'
        );

        $this->assertTrue($result['success']);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'DELETE'
                && str_starts_with($request->url(), 'https://api.trello.com/1/cards/card_del_123/attachments/attach_del_456')
                && str_contains($request->url(), 'key=fake_key')
                && str_contains($request->url(), 'token=fake_token');
        });
    }

    public function test_order_detail_modal_can_delete_attachment(): void
    {
        $user = User::factory()->create([
            'name' => 'César Diseñador',
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $order = Order::create([
            'company_name' => 'Empresa Delete Attach',
            'task_name' => 'Diseño',
            'trello_card_id' => 'card_modal_del',
            'in_workspace' => true,
        ]);

        Http::fake([
            'https://api.trello.com/1/cards/card_modal_del/attachments/attach_to_remove*' => Http::response([
                '_value' => null,
            ], 200),
            'https://api.trello.com/1/cards/card_modal_del/attachments*' => Http::sequence()
                // Initial load with 1 attachment
                ->push([
                    [
                        'id' => 'attach_to_remove',
                        'name' => 'logo_antiguo.png',
                        'bytes' => 1234,
                        'url' => 'https://trello.com/download/logo.png',
                    ],
                ], 200)
                // Reload after deletion: empty list
                ->push([], 200),
            'https://api.trello.com/1/cards/card_modal_del*' => Http::response([
                'id' => 'card_modal_del',
                'name' => 'Empresa Delete Attach',
                'desc' => 'Descripción',
            ], 200),
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->assertCount('trelloAttachments', 1)
            ->call('deleteAttachment', 'attach_to_remove', 'logo_antiguo.png')
            ->assertDispatched('toast')
            ->assertDispatched('order-updated')
            ->assertCount('trelloAttachments', 0);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'TRELLO_ATTACHMENT_DELETED',
            'previous_value' => 'logo_antiguo.png',
            'actor' => 'César Diseñador',
        ]);
    }

    public function test_order_detail_modal_can_upload_multiple_attachments_simultaneously(): void
    {
        $user = User::factory()->create([
            'name' => 'Adrián Salvatori',
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $order = Order::create([
            'company_name' => 'Empresa Multi Attach',
            'task_name' => 'Diseño',
            'trello_card_id' => 'card_modal_multi',
            'in_workspace' => true,
        ]);

        Http::fake([
            'https://api.trello.com/1/cards/card_modal_multi/attachments*' => Http::sequence()
                // Initial load: empty
                ->push([], 200)
                // First upload
                ->push([
                    'id' => 'attach_file_1',
                    'name' => 'archivo_1.pdf',
                    'bytes' => 1024,
                    'url' => 'https://trello.com/download/archivo_1.pdf',
                ], 200)
                // Second upload
                ->push([
                    'id' => 'attach_file_2',
                    'name' => 'archivo_2.png',
                    'bytes' => 2048,
                    'url' => 'https://trello.com/download/archivo_2.png',
                ], 200)
                // Reload after all uploads
                ->push([
                    [
                        'id' => 'attach_file_1',
                        'name' => 'archivo_1.pdf',
                        'bytes' => 1024,
                        'url' => 'https://trello.com/download/archivo_1.pdf',
                    ],
                    [
                        'id' => 'attach_file_2',
                        'name' => 'archivo_2.png',
                        'bytes' => 2048,
                        'url' => 'https://trello.com/download/archivo_2.png',
                    ],
                ], 200),
            'https://api.trello.com/1/cards/card_modal_multi*' => Http::response([
                'id' => 'card_modal_multi',
                'name' => 'Empresa Multi Attach',
                'desc' => 'Descripción de prueba',
            ], 200),
        ]);

        Storage::fake('tmp-for-tests');
        $fakePdf = UploadedFile::fake()->create('archivo_1.pdf', 500, 'application/pdf');
        $fakeImage = UploadedFile::fake()->image('archivo_2.png', 200, 200);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->assertSet('trelloAttachments', [])
            ->set('attachmentFiles', [$fakePdf, $fakeImage])
            ->assertDispatched('toast')
            ->assertDispatched('order-updated')
            ->assertSet('attachmentFiles', [])
            ->assertCount('trelloAttachments', 2);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'TRELLO_ATTACHMENT_ADDED',
            'new_value' => 'archivo_1.pdf',
            'actor' => 'Adrián Salvatori',
        ]);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'TRELLO_ATTACHMENT_ADDED',
            'new_value' => 'archivo_2.png',
            'actor' => 'Adrián Salvatori',
        ]);
    }

    public function test_order_detail_modal_can_download_all_attachments_as_zip(): void
    {
        $user = User::factory()->create([
            'name' => 'Adrián Salvatori',
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $order = Order::create([
            'company_name' => 'Empresa Zip Test',
            'wo_number' => 'WO-9988',
            'task_name' => 'Diseño',
            'trello_card_id' => 'card_zip_test',
            'in_workspace' => true,
        ]);

        Http::fake([
            'https://api.trello.com/1/cards/card_zip_test/attachments*' => Http::response([
                [
                    'id' => 'attach_1',
                    'name' => 'plano_1.pdf',
                    'bytes' => 100,
                    'url' => 'https://trello.com/download/plano_1.pdf',
                ],
                [
                    'id' => 'attach_2',
                    'name' => 'foto_1.png',
                    'bytes' => 200,
                    'url' => 'https://trello.com/download/foto_1.png',
                ],
            ], 200),
            'https://api.trello.com/1/cards/card_zip_test*' => Http::response([
                'id' => 'card_zip_test',
                'name' => 'Empresa Zip Test',
                'desc' => 'Descripción',
            ], 200),
            'https://trello.com/download/plano_1.pdf*' => Http::response('FAKE PDF CONTENT', 200, ['Content-Type' => 'application/pdf']),
            'https://trello.com/download/foto_1.png*' => Http::response('FAKE PNG CONTENT', 200, ['Content-Type' => 'image/png']),
        ]);

        $test = Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->assertCount('trelloAttachments', 2)
            ->call('downloadAllAttachments');

        $response = $test->instance()->downloadAllAttachments();
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('WO-9988_archivos.zip', $response->headers->get('content-disposition'));
    }

    public function test_attachment_errors_are_cleared_on_close_and_reopen_and_clear_action(): void
    {
        $user = User::factory()->create([
            'name' => 'Adrián Salvatori',
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $order = Order::create([
            'company_name' => 'Empresa Test Errores',
            'wo_number' => 'WO-1122',
            'task_name' => 'Diseño',
            'trello_card_id' => 'card_errors_test',
            'in_workspace' => true,
        ]);

        Http::fake([
            'https://api.trello.com/1/cards/card_errors_test/attachments*' => Http::response([], 200),
            'https://api.trello.com/1/cards/card_errors_test*' => Http::response([
                'id' => 'card_errors_test',
                'name' => 'Empresa Test Errores',
            ], 200),
        ]);

        $component = Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->set('attachmentUploadError', 'Error al subir archivo')
            ->assertSet('attachmentUploadError', 'Error al subir archivo');

        // Test clearAttachmentErrors method
        $component->call('clearAttachmentErrors')
            ->assertSet('attachmentUploadError', null)
            ->assertHasNoErrors(['attachmentFiles', 'attachmentFiles.*', 'attachmentFile']);

        // Set error and close modal -> errors must be cleared
        $component->set('attachmentUploadError', 'Error de subida persistente')
            ->call('closeModal')
            ->assertSet('attachmentUploadError', null)
            ->assertHasNoErrors();

        // Reopen modal -> errors must be cleared
        $component->set('attachmentUploadError', 'Error previo')
            ->call('openModal', $order->id)
            ->assertSet('attachmentUploadError', null)
            ->assertHasNoErrors();
    }
}
