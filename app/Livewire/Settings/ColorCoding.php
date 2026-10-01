<?php

namespace App\Livewire\Settings;

use App\Services\ColorCodingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Personalización de Colores')]
class ColorCoding extends Component
{
    public string $activeCategory = 'all';

    /**
     * Stored hex colors keyed by item key.
     *
     * @var array<string, string>
     */
    public array $colors = [];

    public ?string $savedKey = null;

    public bool $showResetAllModal = false;

    public function mount(ColorCodingService $service): void
    {
        $user = Auth::user();
        if ($user && (! $user->isAdmin() && ! $user->isCoordinator())) {
            abort(403, __('No tiene permisos para acceder a esta sección.'));
        }

        $items = $service->getAll();
        foreach ($items as $k => $item) {
            $this->colors[$k] = $item['hex'];
        }
    }

    public function setCategory(string $category): void
    {
        $this->activeCategory = $category;
    }

    public function updatedColors(string $value, string $key): void
    {
        $this->saveColor($key, $value);
    }

    public function selectPreset(string $key, string $hex): void
    {
        $this->colors[$key] = $hex;
        $this->saveColor($key, $hex);
    }

    public function saveColor(string $key, string $hex): void
    {
        $service = app(ColorCodingService::class);
        $service->updateColor($key, $hex);

        $this->savedKey = $key;
        $this->colors[$key] = $service->getHex($key);

        session()->flash('status_message', __('Color actualizado para :item y sincronizado en todo el sistema.', [
            'item' => $service->getAll()[$key]['name'] ?? $key,
        ]));
    }

    public function resetKey(string $key): void
    {
        $service = app(ColorCodingService::class);
        $service->resetToDefaults($key);
        $this->colors[$key] = $service->getHex($key);
        $this->savedKey = $key;

        session()->flash('status_message', __('Color restablecido al valor predeterminado.'));
    }

    public function confirmResetAll(): void
    {
        $this->showResetAllModal = true;
    }

    public function resetAll(): void
    {
        $service = app(ColorCodingService::class);
        $service->resetToDefaults();

        $items = $service->getAll();
        foreach ($items as $k => $item) {
            $this->colors[$k] = $item['hex'];
        }

        $this->showResetAllModal = false;
        $this->savedKey = null;

        session()->flash('status_message', __('Toda la paleta ha sido restablecida a los valores originales de Kudos.'));
    }

    public function render(ColorCodingService $service)
    {
        $allItems = $service->getAll();

        // Overlay with currently reactive wire:model colors
        foreach ($this->colors as $k => $hex) {
            if (isset($allItems[$k])) {
                $allItems[$k]['hex'] = $hex;
                $allItems[$k]['palette'] = $service->derivePalette($hex);
                $allItems[$k]['is_custom'] = strcasecmp($hex, $allItems[$k]['default_hex']) !== 0;
            }
        }

        $filteredItems = $allItems;
        if ($this->activeCategory !== 'all') {
            $filteredItems = array_filter($allItems, fn ($item) => $item['category'] === $this->activeCategory);
        }

        $categories = [
            'all' => [
                'name' => __('Todos'),
                'count' => count($allItems),
            ],
            'supervision' => [
                'name' => __('Supervisión & QA'),
                'count' => count(array_filter($allItems, fn ($i) => $i['category'] === 'supervision')),
            ],
            'production' => [
                'name' => __('Producción & Taller'),
                'count' => count(array_filter($allItems, fn ($i) => $i['category'] === 'production')),
            ],
            'designers' => [
                'name' => __('Diseñadores'),
                'count' => count(array_filter($allItems, fn ($i) => $i['category'] === 'designers')),
            ],
            'workflow' => [
                'name' => __('Flujo de Trabajo'),
                'count' => count(array_filter($allItems, fn ($i) => $i['category'] === 'workflow')),
            ],
        ];

        return view('livewire.settings.color-coding', [
            'items' => $filteredItems,
            'categories' => $categories,
            'totalItemsCount' => count($allItems),
        ]);
    }
}
