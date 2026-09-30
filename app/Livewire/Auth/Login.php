<?php

namespace App\Livewire\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\DemoEnvironmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

    public bool $show_password = false;

    public bool $remember = false;

    public bool $isDemo = false;

    public function mount(DemoEnvironmentService $demoService): void
    {
        if (! Auth::check()) {
            $demoService->disableDemo();
            $this->isDemo = false;
        }
    }

    public function updatedEmail(string $value, DemoEnvironmentService $demoService): void
    {
        $demoEmails = ['admin@kudos.com', 'camila@kudos.com', 'adrian@kudos.com', 'ventas@kudos.com'];
        if (! in_array(strtolower(trim($value)), $demoEmails)) {
            $this->isDemo = false;
            $demoService->disableDemo();
        }
    }

    public function fillDemoCredentials(string $email, DemoEnvironmentService $demoService): void
    {
        $this->isDemo = true;
        $demoService->enableDemo();

        $this->email = $email;
        $user = ($this->isDemo && config('database.connections.demo') ? User::on('demo') : User::query())
            ->where('email', $email)
            ->first();

        if (! $user && $this->isDemo) {
            $user = User::where('email', $email)->first();
        }

        if (! $user) {
            $fallbackRole = match ($email) {
                'admin@kudos.com', 'admin' => UserRole::ADMIN,
                'camila@kudos.com', 'manager@kudos.com', 'manager', 'pm' => UserRole::COORDINATOR,
                'adrian@kudos.com', 'designer@kudos.com', 'designer' => UserRole::DESIGNER,
                'ventas@kudos.com', 'sales@kudos.com', 'comercial' => UserRole::SALES,
                default => null,
            };

            if ($fallbackRole) {
                $user = ($this->isDemo && config('database.connections.demo') ? User::on('demo') : User::query())
                    ->where('role', $fallbackRole)
                    ->where('active', true)
                    ->first()
                    ?? User::where('role', $fallbackRole)->where('active', true)->first();

                if ($user) {
                    $this->email = $user->email;
                }
            }
        }

        if ($user && Hash::check('password', $user->password)) {
            $this->password = 'password';
        } else {
            $this->password = '';
        }

        $this->resetValidation();
    }

    public function loginAsDemo(string $email, DemoEnvironmentService $demoService)
    {
        $this->fillDemoCredentials($email, $demoService);

        $demoService->enableDemo();

        $demoUser = User::on('demo')->where('email', $this->email)->first()
            ?? User::where('email', $this->email)->first();

        if ($demoUser) {
            Auth::login($demoUser, $this->remember);
        } else {
            if (! Auth::attempt(['email' => $this->email, 'password' => 'password'], $this->remember)) {
                throw ValidationException::withMessages([
                    'email' => __('No se pudo autenticar la cuenta de demostración.'),
                ]);
            }
        }

        $user = Auth::user();
        if ($user) {
            $user->update(['last_login_at' => now()]);
        }

        session()->regenerate();
        session()->put(DemoEnvironmentService::DEMO_SESSION_KEY, true);
        session()->save();

        return $this->redirect(route('dashboard'), navigate: false);
    }

    public function login(DemoEnvironmentService $demoService)
    {
        if ($this->isDemo) {
            $demoService->enableDemo();
        } else {
            $demoService->disableDemo();
        }

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

        if ($this->isDemo) {
            session([DemoEnvironmentService::DEMO_SESSION_KEY => true]);
        } else {
            session()->forget(DemoEnvironmentService::DEMO_SESSION_KEY);
        }
        session()->save();

        return $this->redirect(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layout('components.layouts.guest', ['title' => __('Iniciar Sesión - ').config('app.name')]);
    }
}
