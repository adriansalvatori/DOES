<?php

namespace App\Livewire\Settings;

use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProfileSettings extends Component
{
    use WithFileUploads;

    public string $activeTab = 'general';

    // General Profile Attributes
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $phone_country = '+58';

    public string $phone_number = '';

    public string $avatar_url = '';

    public $avatar_file = null;

    // Notification Preferences
    public bool $notify_order_assigned = true;

    public bool $notify_order_blocked = true;

    public bool $notify_review_feedback = true;

    public bool $notify_overdue = true;

    public bool $notify_sound_enabled = false;

    // Environment Preferences
    public string $locale = 'es';

    public string $date_format = 'd/m/Y';

    public string $default_landing_page = 'dashboard';

    // Password Update
    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public bool $show_passwords = false;

    // Password Recovery State
    public bool $showRecoveryModal = false;

    public string $recovery_new_password = '';

    public string $recovery_new_password_confirmation = '';

    public bool $recovery_show_passwords = false;

    public function mount(): void
    {
        $requestedTab = request()->query('tab');
        if ($requestedTab && in_array($requestedTab, ['general', 'notifications', 'preferences', 'security'])) {
            $this->activeTab = $requestedTab;
        }

        $user = Auth::user();

        if ($user) {
            $this->name = $user->name;
            $this->email = $user->email;
            $this->phone = $user->phone ?? '';
            $this->parsePhone($this->phone);
            $this->avatar_url = $user->avatar_url ?? '';

            // User Preferences
            $prefs = $user->preferences ?? [];
            $this->locale = session('locale', $prefs['locale'] ?? config('app.locale', 'es'));
            $this->date_format = $prefs['date_format'] ?? 'd/m/Y';
            $this->default_landing_page = $prefs['default_landing_page'] ?? 'dashboard';

            $notifications = $prefs['notifications'] ?? [];
            $this->notify_order_assigned = (bool) ($notifications['order_assigned'] ?? true);
            $this->notify_order_blocked = (bool) ($notifications['order_blocked'] ?? true);
            $this->notify_review_feedback = (bool) ($notifications['review_feedback'] ?? true);
            $this->notify_overdue = (bool) ($notifications['overdue'] ?? true);
            $this->notify_sound_enabled = (bool) ($notifications['sound_enabled'] ?? false);
        }
    }

    public function updatedPhoneNumber(): void
    {
        $this->syncPhone();
    }

    public function updatedPhoneCountry(): void
    {
        $this->syncPhone();
    }

    public function updatedPhone(string $value): void
    {
        $this->parsePhone($value);
    }

    private function syncPhone(): void
    {
        if (trim($this->phone_number) !== '') {
            $this->phone = trim($this->phone_country.' '.$this->phone_number);
        } else {
            $this->phone = '';
        }
    }

    private function parsePhone(string $rawPhone): void
    {
        $rawPhone = trim($rawPhone);
        if ($rawPhone === '') {
            $this->phone_country = '+58';
            $this->phone_number = '';

            return;
        }

        $codes = [
            '+593', '+507', '+506', '+598', '+595', '+591', '+502', '+503', '+504', '+505', '+351',
            '+58', '+57', '+34', '+52', '+54', '+56', '+51', '+55', '+44', '+33', '+49', '+39', '+31', '+41', '+61', '+1',
        ];

        foreach ($codes as $code) {
            if (str_starts_with($rawPhone, $code)) {
                $this->phone_country = $code;
                $this->phone_number = trim(substr($rawPhone, strlen($code)));

                return;
            }
        }

        $this->phone_country = '+58';
        $this->phone_number = $rawPhone;
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['general', 'notifications', 'preferences', 'security'])) {
            $this->activeTab = $tab;
        }
    }

    public function updatedAvatarFile(): void
    {
        $this->validateOnly('avatar_file', [
            'avatar_file' => 'image|max:3072',
        ]);
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();
        if ($user) {
            if ($user->avatar_url && str_starts_with($user->avatar_url, '/storage/')) {
                $relative = str_replace('/storage/', '', $user->avatar_url);
                Storage::disk('public')->delete($relative);
            }
            $user->update(['avatar_url' => null]);
        }

        $this->avatar_url = '';
        $this->avatar_file = null;

        session()->flash('success_profile', __('Foto de perfil eliminada correctamente.'));
    }

    public function updateProfile(): void
    {
        $user = Auth::user();

        if (trim($this->phone_number) !== '') {
            $this->phone = trim($this->phone_country.' '.$this->phone_number);
        } elseif (trim($this->phone) !== '') {
            // keep existing phone if directly set
        } else {
            $this->phone = '';
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:50',
            'avatar_file' => 'nullable|image|max:3072',
            'avatar_url' => 'nullable|url|max:500',
        ]);

        if ($this->avatar_file) {
            // Remove previous uploaded avatar if existed
            if ($user->avatar_url && str_starts_with($user->avatar_url, '/storage/')) {
                $relative = str_replace('/storage/', '', $user->avatar_url);
                Storage::disk('public')->delete($relative);
            }

            $path = $this->avatar_file->store('avatars', 'public');
            $this->avatar_url = Storage::url($path);
            $this->avatar_file = null;
        }

        $user->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'avatar_url' => $this->avatar_url ?: null,
        ]);

        session()->flash('success_profile', __('Perfil actualizado correctamente.'));
    }

    public function updateNotificationSettings(): void
    {
        $user = Auth::user();

        $user->setPreference('notifications', [
            'order_assigned' => $this->notify_order_assigned,
            'order_blocked' => $this->notify_order_blocked,
            'review_feedback' => $this->notify_review_feedback,
            'overdue' => $this->notify_overdue,
            'sound_enabled' => $this->notify_sound_enabled,
        ]);

        session()->flash('success_notifications', __('Preferencias de notificaciones guardadas con éxito.'));
    }

    public function setLocale(string $locale): void
    {
        if (! in_array($locale, ['es', 'en'])) {
            return;
        }

        $this->locale = $locale;

        $user = Auth::user();
        if ($user) {
            $user->setPreference('locale', $locale);
        }

        session(['locale' => $locale]);
        App::setLocale($locale);
        cookie()->queue(cookie()->forever('app_locale', $locale));

        $this->dispatch('app-locale-changed', locale: $locale);

        session()->flash('success_preferences', __('Idioma actualizado correctamente.'));
        $this->redirectRoute('settings.profile', ['tab' => 'preferences']);
    }

    public function updatePreferences(): void
    {
        $user = Auth::user();

        $this->validate([
            'locale' => 'required|string|in:es,en',
            'date_format' => 'required|string|in:d/m/Y,m/d/Y',
            'default_landing_page' => 'required|string|in:dashboard,kanban,planner,backlog,resolver',
        ]);

        $user->setPreference('locale', $this->locale);
        $user->setPreference('date_format', $this->date_format);
        $user->setPreference('default_landing_page', $this->default_landing_page);

        // Apply locale changes immediately
        session(['locale' => $this->locale]);
        App::setLocale($this->locale);
        cookie()->queue(cookie()->forever('app_locale', $this->locale));

        session()->flash('success_preferences', __('Preferencias de entorno guardadas correctamente.'));
        $this->redirectRoute('settings.profile', ['tab' => 'preferences']);
    }

    public function updatePassword(): void
    {
        $user = User::findOrFail(Auth::id());

        $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($this->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('La contraseña actual no es correcta.'),
            ]);
        }

        $user->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        session()->flash('success_password', __('Contraseña actualizada con éxito.'));
    }

    public function openRecoveryModal(): void
    {
        $this->resetValidation();
        $this->reset(['recovery_new_password', 'recovery_new_password_confirmation']);
        $this->showRecoveryModal = true;
    }

    public function closeRecoveryModal(): void
    {
        $this->showRecoveryModal = false;
        $this->resetValidation();
    }

    public function resetPasswordWithSession(): void
    {
        $this->validate([
            'recovery_new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::findOrFail(Auth::id());
        $user->update([
            'password' => Hash::make($this->recovery_new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation', 'recovery_new_password', 'recovery_new_password_confirmation']);
        $this->showRecoveryModal = false;

        session()->flash('success_password', __('Tu contraseña ha sido restablecida exitosamente.'));
    }

    public function sendPasswordResetEmail(): void
    {
        $user = Auth::user();
        Password::broker()->sendResetLink(['email' => $user->email]);

        $msg = __('Se ha enviado un enlace de recuperación a: ').$user->email;
        session()->flash('recovery_email_sent', $msg);
        session(['recovery_email_sent' => $msg]);
    }

    public function render()
    {
        return view('livewire.settings.profile-settings', [
            'user' => Auth::user(),
        ])->layout('components.layouts.app', ['title' => __('Mi Perfil - ').config('app.name')]);
    }
}
