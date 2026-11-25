<?php

namespace Tests\Feature;

use App\Livewire\Settings\LanguageSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LanguageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_settings_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/language');

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
        $this->assertEquals('Weekly Planner', __('Weekly Planner'));
        $this->assertEquals('Order Backlog', __('Order Backlog'));
        $this->assertEquals('Operational Control Center', __('Operational Control Center'));
        $this->assertEquals('Substatus Configuration', __('Substatus Configuration'));

        app()->setLocale('es');
        $this->assertEquals('Planificador semanal', __('Weekly Planner'));
        $this->assertEquals('Backlog de Órdenes', __('Order Backlog'));
        $this->assertEquals('Centro de Control Operativo', __('Operational Control Center'));
        $this->assertEquals('Configuración de Subestatus', __('Substatus Configuration'));
    }

    public function test_pages_render_in_english_when_locale_is_set(): void
    {
        $user = User::factory()->admin()->create();
        session()->flush();
        $this->withSession(['locale' => 'en']);
        app()->setLocale('en');

        $response = $this->actingAs($user)->get('/board');
        $response->assertStatus(200);
        $response->assertSee('Board');
        $response->assertSee(__('New work order'));

        $responseTrash = $this->get('/trash');
        $responseTrash->assertStatus(200);
        $responseTrash->assertSee('Trash');
        $responseTrash->assertSee(__('Search in trash...'));

        $responsePlanner = $this->get('/plan-week');
        $responsePlanner->assertStatus(200);
        $responsePlanner->assertSee('Plan week');

        $responseReasons = $this->get('/settings/reasons');
        $responseReasons->assertStatus(200);
        $responseReasons->assertSee('Reasons &amp; flags', false);

        $responseTemplates = $this->get('/settings/task-templates');
        $responseTemplates->assertStatus(200);
        $responseTemplates->assertSee('Task templates');

        session()->forget('locale');
        session(['locale' => 'es']);
        app()->setLocale('es');
    }
}
