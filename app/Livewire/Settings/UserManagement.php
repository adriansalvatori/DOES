<?php

namespace App\Livewire\Settings;

use App\Enums\UserRole;
use App\Models\Designer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class UserManagement extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = 'designer';

    public ?int $designer_id = null;

    public bool $active = true;

    public string $password = '';

    public bool $changePassword = false;

    public string $search = '';

    public string $cs_whatsapp_phone = '';

    public function mount()
    {
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, __('No tiene permisos para acceder a esta sección.'));
        }

        $this->cs_whatsapp_phone = Setting::get('cs_whatsapp_phone', '+16783580594');
    }

    public function saveCsWhatsapp(): void
    {
        $this->validate([
            'cs_whatsapp_phone' => 'required|string|max:50',
        ]);

        Setting::set('cs_whatsapp_phone', trim($this->cs_whatsapp_phone));
        session()->flash('success_cs', __('Número de WhatsApp de Atención al Cliente actualizado exitosamente.'));
    }

    public function openCreateModal()
    {
        $this->reset(['editingUserId', 'name', 'email', 'phone', 'role', 'designer_id', 'password', 'active']);
        $this->role = UserRole::DESIGNER->value;
        $this->active = true;
        $this->changePassword = true;
        $this->showModal = true;
    }

    public function openEditModal(int $id)
    {
        $user = User::findOrFail($id);
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? ($user->designer?->phone ?? '');
        $this->role = $user->role->value;
        $this->active = $user->active;
        $this->designer_id = $user->designer?->id;
        $this->password = '';
        $this->changePassword = false;
        $this->showModal = true;
    }

    public function saveUser()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$this->editingUserId,
            'phone' => 'nullable|string|max:50',
            'role' => 'required|string',
            'active' => 'boolean',
            'designer_id' => 'nullable|integer|exists:designers,id',
        ];

        if (! $this->editingUserId || $this->changePassword) {
            $rules['password'] = 'required|string|min:8';
        }

        $this->validate($rules);

        $cleanPhone = $this->phone ? trim($this->phone) : null;

        if ($this->editingUserId) {
            $user = User::findOrFail($this->editingUserId);
            $user->update([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $cleanPhone,
                'role' => $this->role,
                'active' => $this->active,
            ]);

            if ($this->changePassword && ! empty($this->password)) {
                $user->update(['password' => Hash::make($this->password)]);
            }
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $cleanPhone,
                'password' => Hash::make($this->password),
                'role' => $this->role,
                'active' => $this->active,
            ]);
        }

        // Handle Designer linking & phone sync
        Designer::where('user_id', $user->id)->update(['user_id' => null]);
        if ($this->designer_id) {
            $designer = Designer::find($this->designer_id);
            if ($designer) {
                $designer->update([
                    'user_id' => $user->id,
                    'phone' => $cleanPhone ?: $designer->phone,
                ]);
            }
        }

        $this->showModal = false;
        session()->flash('success_user', __('Usuario guardado exitosamente.'));
    }

    public function toggleActive(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->id === Auth::id()) {
            throw ValidationException::withMessages(['user' => __('No puede desactivar su propia cuenta.')]);
        }
        $user->update(['active' => ! $user->active]);
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%'))
            ->orderBy('id', 'asc')
            ->paginate(10);

        $designers = Designer::where('active', true)->orderBy('name')->get();

        return view('livewire.settings.user-management', [
            'users' => $users,
            'designers' => $designers,
            'roles' => UserRole::cases(),
        ])->layout('components.layouts.app', ['title' => __('Gestión de Usuarios - ').config('app.name')]);
    }
}
