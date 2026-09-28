<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Login extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required')]
    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => __('Las credenciales proporcionadas no son válidas.'),
            ]);
        }

        $user = Auth::user();

        if (! $user->active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => __('Su cuenta ha sido desactivada. Póngase en contacto con el administrador.'),
            ]);
        }

        $user->update([
            'last_login_at' => now(),
        ]);

        session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layout('components.layouts.guest', ['title' => __('Iniciar Sesión - Kudos Design Ops')]);
    }
}
