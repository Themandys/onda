<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Alta de la única cuenta de la aplicación. Solo está disponible mientras
 * no exista ningún usuario; después, el registro queda cerrado.
 */
#[Layout('layouts::auth')]
#[Title('Crea tu cuenta')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (User::exists()) {
            $this->redirectRoute('login');
        }
    }

    public function register(): void
    {
        abort_if(User::exists(), 403);

        $data = $this->validate(
            [
                'name' => ['required', 'string', 'max:60'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', 'confirmed', Password::min(8)],
            ],
            [
                'name.required' => 'Dinos cómo te llamas.',
                'name.max' => 'Máximo 60 caracteres.',
                'email.required' => 'Escribe tu correo.',
                'email.email' => 'Ese correo no parece válido.',
                'password.required' => 'Elige una contraseña.',
                'password.confirmed' => 'Las contraseñas no coinciden.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            ],
        );

        Auth::login(User::create($data), remember: true);
        session()->regenerate();

        $this->redirectRoute('home');
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
