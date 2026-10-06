<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

class FaviconInverterTest extends TestCase
{
    public function test_inverted_favicon_assets_are_rendered_in_local_environment(): void
    {
        App::detectEnvironment(fn () => 'local');

        $view = $this->blade('<x-favicon-inverter />');

        $view->assertSee('favicon-local.ico', false);
        $view->assertSee('favicon-local-32x32.png', false);
    }

    public function test_standard_favicon_assets_are_rendered_in_production_environment(): void
    {
        App::detectEnvironment(fn () => 'production');

        $view = $this->blade('<x-favicon-inverter />');

        $view->assertSee('favicon.ico', false);
        $view->assertSee('favicon-32x32.png', false);
        $view->assertDontSee('favicon-local', false);
    }
}
