<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\App;
use Livewire\Component;

class LanguageSettings extends Component
{
    public string $currentLocale = 'es';

    public function mount(): void
    {
        $this->currentLocale = session('locale', config('app.locale', 'es'));
    }

    public function setLocale(string $locale): void
    {
        if (! in_array($locale, ['es', 'en'])) {
            return;
        }

        $this->currentLocale = $locale;
        session(['locale' => $locale]);
        App::setLocale($locale);

        cookie()->queue(cookie()->forever('app_locale', $locale));

        $this->dispatch('app-locale-changed', locale: $locale);

        session()->flash('message', __('Idioma guardado correctamente.'));
    }

    public function render()
    {
        return view('livewire.settings.language')
            ->layout('components.layouts.app', ['title' => __('Idioma / Language')]);
    }
}
