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

        app()->setLocale('es');
        $this->assertEquals('Planificador Semanal', __('Planificador Semanal'));
        $this->assertEquals('Backlog de Órdenes', __('Backlog de Órdenes'));
    }
}
