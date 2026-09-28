<?php

namespace App\Livewire\Settings;

use App\Enums\UserRole;
use App\Models\Designer;
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

    public string $role = 'designer';

    public ?int $designer_id = null;

    public bool $active = true;

    public string $password = '';

    public string $search = '';

    public function mount()
    {
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, __('No tiene permisos para acceder a esta sección.'));
        }
    }

    public function openCreateModal()
    {
        $this->reset(['editingUserId', 'name', 'email', 'role', 'designer_id', 'password', 'active']);
        $this->role = UserRole::DESIGNER->value;
        $this->active = true;
        $this->showModal = true;
    }

    public function openEditModal(int $id)
    {
        $user = User::findOrFail($id);
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->active = $user->active;
        $this->designer_id = $user->designer?->id;
        $this->password = '';
        $this->showModal = true;
    }

    public function saveUser()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$this->editingUserId,
            'role' => 'required|string',
            'active' => 'boolean',
            'designer_id' => 'nullable|integer|exists:designers,id',
        ];

        if (! $this->editingUserId) {
            $rules['password'] = 'required|string|min:8';
        }

        $this->validate($rules);

        if ($this->editingUserId) {
            $user = User::findOrFail($this->editingUserId);
            $user->update([
                'name' => $this->name,
                'email' => $this->email,
                'role' => $this->role,
                'active' => $this->active,
            ]);

            if (! empty($this->password)) {
                $user->update(['password' => Hash::make($this->password)]);
            }
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role' => $this->role,
                'active' => $this->active,
            ]);
        }

        // Handle Designer linking
        Designer::where('user_id', $user->id)->update(['user_id' => null]);
        if ($this->designer_id) {
            $designer = Designer::find($this->designer_id);
            if ($designer) {
                $designer->update(['user_id' => $user->id]);
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
        ])->layout('components.layouts.app', ['title' => __('Gestión de Usuarios - Kudos Design Ops')]);
    }
}
