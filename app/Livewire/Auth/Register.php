<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Alta de una cuenta nueva. Cada cuenta tiene su propia biblioteca,
 * historial, tema y foto de perfil.
 */
#[Layout('layouts::auth')]
#[Title('Crea tu cuenta')]
class Register extends Component
{
    /** Cuentas nuevas permitidas por hora desde una misma IP. */
    private const MAX_ACCOUNTS_PER_HOUR = 5;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(): void
    {
        $this->email = Str::lower(trim($this->email));

        $throttleKey = 'register|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ACCOUNTS_PER_HOUR)) {
            throw ValidationException::withMessages([
                'email' => 'Se han creado demasiadas cuentas desde esta conexión. Inténtalo más tarde.',
            ]);
        }

        $data = $this->validate(
            [
                'name' => ['required', 'string', 'max:60'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
                'password' => ['required', 'string', 'confirmed', Password::min(8)],
            ],
            [
                'name.required' => 'Dinos cómo te llamas.',
                'name.max' => 'Máximo 60 caracteres.',
                'email.required' => 'Escribe tu correo.',
                'email.email' => 'Ese correo no parece válido.',
                'email.unique' => 'Ya hay una cuenta con ese correo. Inicia sesión.',
                'password.required' => 'Elige una contraseña.',
                'password.confirmed' => 'Las contraseñas no coinciden.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            ],
        );

        RateLimiter::hit($throttleKey, 3600);

        Auth::login(User::create($data), remember: true);
        session()->regenerate();

        $this->redirectRoute('home');
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
