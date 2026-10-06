<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationSettings extends Component
{
    public array $settings = [];

    public function mount(): void
    {
        if (! Auth::user()?->isAdmin()) {
            abort(403);
        }

        $defaults = [
            'new_order' => true,
            'order_overdue' => true,
            'status_changed' => true,
            'order_blocked' => true,
            'order_unblocked' => true,
            'order_approved_alta' => true,
            'new_attachments' => true,
            'new_comment' => true,
            'order_due_today' => true,
            'overdue_email_sent' => true,
            'welcome_email_sent' => true,
        ];

        $saved = Setting::get('admin_notification_settings', []);
        $this->settings = array_merge($defaults, is_array($saved) ? $saved : json_decode($saved, true) ?? []);
    }

    public function toggleSetting(string $key): void
    {
        if (array_key_exists($key, $this->settings)) {
            $this->settings[$key] = ! $this->settings[$key];
            Setting::set('admin_notification_settings', json_encode($this->settings));
            session()->flash('success', __('Configuración de notificaciones actualizada correctamente.'));
        }
    }

    public function enableAll(): void
    {
        foreach ($this->settings as $key => $val) {
            $this->settings[$key] = true;
        }
        Setting::set('admin_notification_settings', json_encode($this->settings));
        session()->flash('success', __('Todas las notificaciones activadas.'));
    }

    public function render()
    {
        return view('livewire.settings.notification-settings')
            ->layout('components.layouts.app', ['title' => __('Configuración de Notificaciones')]);
    }
}
