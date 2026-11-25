<?php

namespace App\Livewire\Settings;

use App\Application\Delivery\Queries\BoardLimits;
use App\Enums\UserRole;
use App\Models\Designer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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

    public bool $is_reviewer = false;

    public bool $is_lead = false;

    public int $wip_limit = 4;

    public string $password = '';

    public bool $changePassword = false;

    public string $search = '';

    public string $cs_whatsapp_phone = '';

    public ?int $manager_id = null;

    public string $manager_name = '';

    public function mount()
    {
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, __('You don\'t have permission to access this section.'));
        }

        $this->cs_whatsapp_phone = Setting::get('cs_whatsapp_phone', '+16783580594');

        $configuredManager = Setting::getManagerUser();
        $this->manager_id = $configuredManager?->id ?? (int) Setting::get('manager_id', 8);
        $this->manager_name = Setting::getManagerName();
    }

    public function updatedManagerId($value): void
    {
        if ($value) {
            $user = User::find($value);
            if ($user) {
                $parts = explode(' ', trim($user->name));
                $this->manager_name = $parts[0] ?: $user->name;
            }
        }
    }

    public function saveCsWhatsapp(): void
    {
        $this->validate([
            'cs_whatsapp_phone' => 'required|string|max:50',
        ]);

        Setting::set('cs_whatsapp_phone', trim($this->cs_whatsapp_phone));
        session()->flash('success_cs', __('Customer service WhatsApp number updated.'));
    }

    public function saveManagerSettings(): void
    {
        $this->validate([
            'manager_id' => 'nullable|integer|exists:users,id',
            'manager_name' => 'required|string|max:100',
        ], [
            'manager_id.exists' => __('The selected manager doesn\'t exist.'),
            'manager_name.required' => __('The manager display name is required.'),
        ]);

        Setting::set('manager_id', $this->manager_id);
        Setting::set('manager_name', trim($this->manager_name));

        session()->flash('success_manager', __('Approvals Manager configuration updated successfully.'));
    }

    public function openCreateModal()
    {
        $this->reset(['editingUserId', 'name', 'email', 'phone', 'role', 'designer_id', 'password', 'active', 'is_reviewer', 'is_lead']);
        $this->wip_limit = app(BoardLimits::class)->defaultWipLimit();
        $this->resetValidation();
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
        $this->is_reviewer = (bool) $user->is_reviewer;
        $this->is_lead = (bool) $user->is_lead;
        $this->wip_limit = (int) ($user->wip_limit ?? app(BoardLimits::class)->defaultWipLimit());
        $this->resetValidation();
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
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_reviewer' => 'boolean',
            'is_lead' => 'boolean',
            'wip_limit' => 'required|integer|min:1|max:50',
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
                'is_reviewer' => $this->is_reviewer,
                'is_lead' => $this->is_lead,
                'wip_limit' => $this->wip_limit,
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
                'is_reviewer' => $this->is_reviewer,
                'is_lead' => $this->is_lead,
                'wip_limit' => $this->wip_limit,
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
        session()->flash('success_user', __('User saved.'));
    }

    public function toggleActive(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->id === Auth::id()) {
            throw ValidationException::withMessages(['user' => __('You can\'t deactivate your own account.')]);
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
            'allUsers' => User::orderBy('name')->get(),
            'designers' => $designers,
            'roles' => UserRole::cases(),
        ])->layout('components.layouts.app', ['title' => __('Users - ').config('app.name')]);
    }
}
