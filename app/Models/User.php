<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'avatar', 'bio', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Tema con el que arranca una cuenta nueva. */
    public const DEFAULT_THEME = 'grafito';

    /**
     * Temas de interfaz: «Grafito» (oscuro y sobrio) es el de serie; el resto
     * son variantes claras y oscuras que se eligen desde el perfil.
     *
     * @var array<string, array{name: string, subtitle: string, scheme: string, paper: string, ink: string, heart: string, glow1: string, glow2: string, crystal1: string, crystal2: string}>
     */
    public const THEMES = [
        'grafito' => [
            'name' => 'Grafito',
            'subtitle' => 'Oscuro sobrio',
            'scheme' => 'dark',
            'paper' => '#0d0e10',
            'ink' => '#edece8',
            'heart' => '#e2796d',
            'glow1' => '#55606f',
            'glow2' => '#6d6558',
            'crystal1' => '#a9cfe2',
            'crystal2' => '#c3b7e6',
        ],
        'piedra' => [
            'name' => 'Piedra Cálida',
            'subtitle' => 'Sobrio editorial',
            'scheme' => 'light',
            'paper' => '#e9e7e2',
            'ink' => '#1c1917',
            'heart' => '#a3524b',
            'glow1' => '#8b929c',
            'glow2' => '#a9a094',
            'crystal1' => '#bfe0ee',
            'crystal2' => '#d8cdea',
        ],
        'nieve' => [
            'name' => 'Cristal Nieve',
            'subtitle' => 'Blanco luminoso',
            'scheme' => 'light',
            'paper' => '#f4f6fb',
            'ink' => '#0f172a',
            'heart' => '#e11d48',
            'glow1' => '#6366f1',
            'glow2' => '#06b6d4',
            'crystal1' => '#c7d2fe',
            'crystal2' => '#a5f3fc',
        ],
        'arena' => [
            'name' => 'Arena Dorada',
            'subtitle' => 'Crema y ámbar',
            'scheme' => 'light',
            'paper' => '#f5efe6',
            'ink' => '#2c221e',
            'heart' => '#c2410c',
            'glow1' => '#d97706',
            'glow2' => '#ea580c',
            'crystal1' => '#fde68a',
            'crystal2' => '#fed7aa',
        ],
        'rosa' => [
            'name' => 'Cuarzo Rosa',
            'subtitle' => 'Rosa pastel suave',
            'scheme' => 'light',
            'paper' => '#faedf0',
            'ink' => '#3b1922',
            'heart' => '#e11d48',
            'glow1' => '#ec4899',
            'glow2' => '#f43f5e',
            'crystal1' => '#fbcfe8',
            'crystal2' => '#fecdd3',
        ],
        'menta' => [
            'name' => 'Brisa Menta',
            'subtitle' => 'Verde agua fresco',
            'scheme' => 'light',
            'paper' => '#e8f5f1',
            'ink' => '#132a23',
            'heart' => '#0d9488',
            'glow1' => '#10b981',
            'glow2' => '#14b8a6',
            'crystal1' => '#a7f3d0',
            'crystal2' => '#99f6e4',
        ],
        'lavanda' => [
            'name' => 'Lavanda Sueño',
            'subtitle' => 'Lila y violeta',
            'scheme' => 'light',
            'paper' => '#f0ecf9',
            'ink' => '#23193b',
            'heart' => '#9333ea',
            'glow1' => '#8b5cf6',
            'glow2' => '#c084fc',
            'crystal1' => '#ddd6fe',
            'crystal2' => '#e9d5ff',
        ],
        'cielo' => [
            'name' => 'Cielo Ártico',
            'subtitle' => 'Azul hielo claro',
            'scheme' => 'light',
            'paper' => '#eaf2fb',
            'ink' => '#10243a',
            'heart' => '#2563eb',
            'glow1' => '#3b82f6',
            'glow2' => '#38bdf8',
            'crystal1' => '#bfdbfe',
            'crystal2' => '#bae6fd',
        ],
        'durazno' => [
            'name' => 'Sol de Durazno',
            'subtitle' => 'Coral y melocotón',
            'scheme' => 'light',
            'paper' => '#fbf0e8',
            'ink' => '#382014',
            'heart' => '#ea580c',
            'glow1' => '#f97316',
            'glow2' => '#fbbf24',
            'crystal1' => '#fed7aa',
            'crystal2' => '#fde68a',
        ],
        'medianoche' => [
            'name' => 'Medianoche Onda',
            'subtitle' => 'Oscuro profundo',
            'scheme' => 'dark',
            'paper' => '#0b0d14',
            'ink' => '#f1f5f9',
            'heart' => '#fb7185',
            'glow1' => '#6366f1',
            'glow2' => '#22d3ee',
            'crystal1' => '#4338ca',
            'crystal2' => '#0e7490',
        ],
        'esmeralda' => [
            'name' => 'Bosque Nocturno',
            'subtitle' => 'Oscuro esmeralda',
            'scheme' => 'dark',
            'paper' => '#091512',
            'ink' => '#ecfdf5',
            'heart' => '#34d399',
            'glow1' => '#10b981',
            'glow2' => '#059669',
            'crystal1' => '#065f46',
            'crystal2' => '#0f766e',
        ],
        'cyberpunk' => [
            'name' => 'Neón Cyberpunk',
            'subtitle' => 'Oscuro magenta y láser',
            'scheme' => 'dark',
            'paper' => '#12091c',
            'ink' => '#fae8ff',
            'heart' => '#f43f5e',
            'glow1' => '#d946ef',
            'glow2' => '#06b6d4',
            'crystal1' => '#86198f',
            'crystal2' => '#155e75',
        ],
        'oceano' => [
            'name' => 'Océano Abisal',
            'subtitle' => 'Azul marino nocturno',
            'scheme' => 'dark',
            'paper' => '#08131d',
            'ink' => '#e0f2fe',
            'heart' => '#38bdf8',
            'glow1' => '#0284c7',
            'glow2' => '#2dd4bf',
            'crystal1' => '#075985',
            'crystal2' => '#115e59',
        ],
    ];

    /** Carpeta del disco público donde se guardan las fotos de perfil. */
    public const AVATAR_DIRECTORY = 'avatars';

    /**
     * Avatares musicales predefinidos para personalizar el perfil.
     *
     * @var array<string, string>
     */
    public const AVATARS = [
        'vinyl' => '💿',
        'headphones' => '🎧',
        'guitar' => '🎸',
        'piano' => '🎹',
        'saxophone' => '🎷',
        'microphone' => '🎤',
        'sparkles' => '✨',
        'fire' => '🔥',
        'moon' => '🌙',
        'sun' => '☀️',
        'wave' => '🌊',
        'heart' => '💜',
    ];

    /**
     * @return array{name: string, subtitle: string, scheme: string, paper: string, ink: string, heart: string, glow1: string, glow2: string, crystal1: string, crystal2: string}
     */
    public function themeConfig(): array
    {
        return self::THEMES[$this->themeKey()];
    }

    /**
     * Clave del tema activo; si la guardada ya no existe, la de serie.
     */
    public function themeKey(): string
    {
        return array_key_exists((string) $this->theme, self::THEMES) ? (string) $this->theme : self::DEFAULT_THEME;
    }

    /**
     * Devuelve el emoji/símbolo del avatar o la inicial del nombre.
     */
    public function avatarSymbol(): string
    {
        $key = (string) ($this->avatar ?? '');

        if (isset(self::AVATARS[$key])) {
            return self::AVATARS[$key];
        }

        return mb_strtoupper(mb_substr((string) $this->name, 0, 1)) ?: 'U';
    }

    /**
     * ¿El avatar es una foto subida por el usuario (guardada en el disco público)?
     */
    public function hasUploadedAvatar(): bool
    {
        return str_starts_with((string) ($this->avatar ?? ''), self::AVATAR_DIRECTORY.'/');
    }

    public function hasImageAvatar(): bool
    {
        return $this->avatarUrl() !== null;
    }

    /**
     * URL de la foto de perfil, o null si el avatar es un icono o la inicial.
     */
    public function avatarUrl(): ?string
    {
        $avatar = (string) ($this->avatar ?? '');

        return match (true) {
            $this->hasUploadedAvatar() => asset('storage/'.$avatar),
            str_starts_with($avatar, 'https://') => $avatar,
            default => null,
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
