<?php

namespace Tests\Feature;

use App\Livewire\Settings\LanguageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LanguageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_settings_page_can_be_rendered(): void
    {
        $response = $this->get('/settings/language');

        $response->assertStatus(200);
        $response->assertSee('Idioma / Language');
    }

    public function test_locale_can_be_switched_to_english(): void
    {
        Livewire::test(LanguageSettings::class)
            ->call('setLocale', 'en')
            ->assertSet('currentLocale', 'en')
            ->assertDispatched('app-locale-changed', locale: 'en');

        $this->assertEquals('en', app()->getLocale());
        $this->assertEquals('en', session('locale'));

        // Reset locale back to es for clean test isolation
        app()->setLocale('es');
        session(['locale' => 'es']);
    }

    public function test_locale_can_be_switched_to_spanish(): void
    {
        Livewire::test(LanguageSettings::class)
            ->call('setLocale', 'es')
            ->assertSet('currentLocale', 'es')
            ->assertDispatched('app-locale-changed', locale: 'es');

        $this->assertEquals('es', app()->getLocale());
        $this->assertEquals('es', session('locale'));
    }

    public function test_json_translations_resolve_correctly(): void
    {
        app()->setLocale('en');
        $this->assertEquals('Weekly Planner', __('Planificador Semanal'));
        $this->assertEquals('Order Backlog', __('Backlog de Órdenes'));
        $this->assertEquals('Operational Control Center', __('Centro de Control Operativo'));
        $this->assertEquals('Substatus Configuration', __('Configuración de Subestatus'));

        app()->setLocale('es');
        $this->assertEquals('Planificador Semanal', __('Planificador Semanal'));
        $this->assertEquals('Backlog de Órdenes', __('Backlog de Órdenes'));
        $this->assertEquals('Centro de Control Operativo', __('Centro de Control Operativo'));
        $this->assertEquals('Configuración de Subestatus', __('Configuración de Subestatus'));
    }

    public function test_pages_render_in_english_when_locale_is_set(): void
    {
        session()->flush();
        $this->withSession(['locale' => 'en']);
        app()->setLocale('en');

        $response = $this->get('/kanban');
        $response->assertStatus(200);
        $response->assertSee('Kanban Board');
        $response->assertSee('Trash');
        $response->assertSee('Drag and drop cards between lists to update their status in real time.');

        $responseTrash = $this->get('/trash');
        $responseTrash->assertStatus(200);
        $responseTrash->assertSee('Trash');
        $responseTrash->assertSee('Deleted orders — Restore or delete permanently');

        $responsePlanner = $this->get('/planner');
        $responsePlanner->assertStatus(200);
        $responsePlanner->assertSee('Weekly Planner');

        $responseSubstatuses = $this->get('/settings/substatuses');
        $responseSubstatuses->assertStatus(200);
        $responseSubstatuses->assertSee('Substatus Configuration');

        $responsePresets = $this->get('/settings/subtasks');
        $responsePresets->assertStatus(200);
        $responsePresets->assertSee('Subtask &amp; System Task Management', false);

        $responseTrelloMap = $this->get('/settings/trello-mapping');
        $responseTrelloMap->assertStatus(200);
        $responseTrelloMap->assertSee('Trello List Mapping');

        $responseTrelloSync = $this->get('/trello-sync');
        $responseTrelloSync->assertStatus(200);
        $responseTrelloSync->assertSee('Live Trello Sync');

        session()->forget('locale');
        session(['locale' => 'es']);
        app()->setLocale('es');
    }
}
