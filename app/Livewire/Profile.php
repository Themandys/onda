<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Perfil')]
class Profile extends Component
{
    use WithFileUploads;

    /** Lado máximo (px) de la foto: el navegador ya la recorta y reduce antes de subirla. */
    private const PHOTO_MAX_SIDE = 2048;

    /** Peso máximo de la foto en kilobytes. */
    private const PHOTO_MAX_KB = 2048;

    public string $name = '';

    public string $email = '';

    public string $bio = '';

    public string $avatar = '';

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public string $theme = User::DEFAULT_THEME;

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = $this->user();

        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->bio = (string) ($user->bio ?? '');
        $this->avatar = (string) ($user->avatar ?? '');
        $this->theme = $user->themeKey();
    }

    /**
     * Livewire lo llama en cuanto termina de subirse la foto: se valida,
     * se guarda en el disco público y sustituye a la anterior al momento.
     */
    public function updatedPhoto(): void
    {
        try {
            $this->validate([
                'photo' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:'.self::PHOTO_MAX_KB,
                    Rule::dimensions()->maxWidth(self::PHOTO_MAX_SIDE)->maxHeight(self::PHOTO_MAX_SIDE),
                ],
            ], [
                'photo.image' => 'El archivo tiene que ser una imagen.',
                'photo.mimes' => 'Usa una foto en formato JPG, PNG o WebP.',
                'photo.max' => 'La foto pesa demasiado. Prueba con otra más pequeña.',
                'photo.dimensions' => 'La foto es demasiado grande. Prueba con otra más pequeña.',
            ]);
        } catch (ValidationException $e) {
            // El archivo rechazado no se queda como foto pendiente.
            $this->reset('photo');

            throw $e;
        }

        $user = $this->user();
        $previous = $user->hasUploadedAvatar() ? $user->avatar : null;

        $path = $this->photo->storeAs(
            User::AVATAR_DIRECTORY,
            $user->id.'-'.Str::random(16).'.'.$this->photo->extension(),
            'public',
        );

        $user->update(['avatar' => $path]);
        $this->avatar = $path;
        $this->reset('photo');

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

        $this->announceAvatar();
        $this->dispatch('toast', message: 'Foto de perfil actualizada');
    }

    /**
     * Quita la foto y vuelve a la inicial del nombre.
     */
    public function removePhoto(): void
    {
        $this->setAvatar('');

        $this->dispatch('toast', message: 'Foto de perfil eliminada');
    }

    /**
     * Usa uno de los iconos predefinidos (o la inicial, con '') como avatar.
     */
    public function selectAvatar(string $key): void
    {
        if ($key === '' || array_key_exists($key, User::AVATARS)) {
            $this->setAvatar($key);
        }
    }

    public function selectTheme(string $themeKey): void
    {
        if (! array_key_exists($themeKey, User::THEMES)) {
            return;
        }

        $this->theme = $themeKey;
        $this->user()->update(['theme' => $themeKey]);

        $this->dispatch('theme-changed', theme: $themeKey, config: User::THEMES[$themeKey]);
        $this->dispatch('toast', message: 'Tema «'.User::THEMES[$themeKey]['name'].'» activado');
    }

    public function saveProfile(): void
    {
        $user = $this->user();

        $this->name = trim($this->name);
        $this->email = mb_strtolower(trim($this->email));
        $this->bio = trim($this->bio);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:160'],
        ], [
            'name.required' => 'Escribe tu nombre.',
            'email.required' => 'Escribe tu correo electrónico.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.unique' => 'Ese correo ya está en uso.',
            'bio.max' => 'La biografía puede tener hasta 160 caracteres.',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'bio' => $validated['bio'] ?? '',
        ]);

        $this->announceAvatar();
        $this->dispatch('toast', message: 'Perfil actualizado');
    }

    public function updatePassword(): void
    {
        $user = $this->user();

        $this->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Escribe tu contraseña actual.',
            'new_password.required' => 'Escribe la nueva contraseña.',
            'new_password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'new_password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'La contraseña actual no es correcta.');

            return;
        }

        $user->update([
            'password' => $this->new_password,
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->dispatch('toast', message: 'Contraseña actualizada');
    }

    public function render(): View
    {
        return view('livewire.profile', [
            'user' => $this->user(),
            'themes' => User::THEMES,
            'avatars' => User::AVATARS,
        ]);
    }

    /**
     * Guarda el avatar nuevo y borra del disco la foto que deja de usarse.
     */
    private function setAvatar(string $avatar): void
    {
        $user = $this->user();
        $previous = $user->hasUploadedAvatar() ? $user->avatar : null;

        $user->update(['avatar' => $avatar]);
        $this->avatar = $avatar;

        if ($previous !== null && $previous !== $avatar) {
            Storage::disk('public')->delete($previous);
        }

        $this->announceAvatar();
    }

    /**
     * Avisa a la cabecera (fuera de este componente) de que el avatar cambió.
     */
    private function announceAvatar(): void
    {
        $user = $this->user();

        $this->dispatch('avatar-changed', url: $user->avatarUrl(), symbol: $user->avatarSymbol());
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
