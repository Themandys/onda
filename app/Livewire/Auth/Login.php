<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::auth')]
#[Title('Iniciar sesión')]
class Login extends Component
{
    /** Intentos fallidos permitidos por minuto para un mismo correo e IP. */
    private const MAX_ATTEMPTS = 5;

    public string $email = '';

    public string $password = '';

    public bool $remember = true;

    public function mount(): void
    {
        // Primera visita: todavía no existe la cuenta, así que toca crearla.
        if (User::doesntExist()) {
            $this->redirectRoute('register');
        }
    }

    public function login(): void
    {
        $credentials = $this->validate(
            [
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ],
            [
                'email.required' => 'Escribe tu correo.',
                'email.email' => 'Ese correo no parece válido.',
                'password.required' => 'Escribe tu contraseña.',
            ],
        );

        $throttleKey = Str::transliterate(Str::lower($this->email)).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Vuelve a probar en {$seconds} segundos.",
            ]);
        }

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($throttleKey);
            $this->reset('password');

            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no son correctos.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirectIntended(route('home'));
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
