@php
    $photoUrl = $user->avatarUrl();
    $schemes = ['dark' => 'Oscuros', 'light' => 'Claros'];
@endphp

<div>
    <header class="mb-7">
        <p class="eyebrow">Cuenta</p>
        <h1 class="page-title mt-1.5 text-ink">Perfil y apariencia</h1>
    </header>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <div class="flex min-w-0 flex-col gap-6">
            {{-- Foto y datos --}}
            <section class="glass rounded-3xl p-5 sm:p-6" aria-labelledby="profile-heading">
                <h2 id="profile-heading" class="section-title text-ink">Tu perfil</h2>

                <div
                    class="mt-5 flex flex-wrap items-center gap-5"
                    x-data="{
                        busy: false,
                        error: '',
                        async pick(event) {
                            const file = event.target.files[0];
                            event.target.value = '';
                            if (! file) return;

                            this.error = '';
                            this.busy = true;

                            try {
                                // Recorta al centro y reduce a 512 px antes de subir: pesa poco y carga al instante.
                                const bitmap = await createImageBitmap(file);
                                const side = Math.min(bitmap.width, bitmap.height);
                                const size = Math.min(512, side);
                                const canvas = document.createElement('canvas');
                                canvas.width = canvas.height = size;
                                canvas.getContext('2d').drawImage(bitmap, (bitmap.width - side) / 2, (bitmap.height - side) / 2, side, side, 0, 0, size, size);

                                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.88));
                                if (! blob) throw new Error('empty');

                                $wire.upload(
                                    'photo',
                                    new File([blob], 'avatar.jpg', { type: 'image/jpeg' }),
                                    () => (this.busy = false),
                                    () => {
                                        this.busy = false;
                                        this.error = 'No se pudo subir la foto. Inténtalo de nuevo.';
                                    },
                                );
                            } catch {
                                this.busy = false;
                                this.error = 'No se pudo leer esa imagen. Prueba con una foto JPG, PNG o WebP.';
                            }
                        },
                    }"
                >
                    <span class="avatar size-24 text-3xl" x-bind:class="busy && 'animate-pulse'">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="Tu foto de perfil" referrerpolicy="no-referrer">
                        @else
                            {{ $user->avatarSymbol() }}
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-ink">Foto de perfil</p>
                        <p class="mt-0.5 text-xs text-ink/60">Elige una imagen de tu dispositivo. Se recorta en círculo.</p>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <label class="btn-primary cursor-pointer rounded-full px-4 py-2 text-xs font-semibold transition focus-within:outline-2 focus-within:outline-offset-2">
                                <span x-text="busy ? 'Subiendo…' : @js($photoUrl ? 'Cambiar foto' : 'Subir foto')">{{ $photoUrl ? 'Cambiar foto' : 'Subir foto' }}</span>
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" x-on:change="pick($event)" x-bind:disabled="busy" aria-label="Subir foto de perfil">
                            </label>

                            @if ($user->hasImageAvatar())
                                <button type="button" wire:click="removePhoto" class="btn-secondary rounded-full px-4 py-2 text-xs font-medium transition">
                                    Quitar foto
                                </button>
                            @endif
                        </div>

                        <p class="field-error" role="alert" x-show="error" x-text="error" x-cloak></p>
                        @error('photo')
                            <p class="field-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-5">
                    <p class="field-label">O usa un icono</p>
                    <div class="avatar-picker-grid">
                        <button
                            type="button"
                            wire:click="selectAvatar('')"
                            class="avatar-option text-xs font-semibold uppercase"
                            aria-pressed="{{ $avatar === '' ? 'true' : 'false' }}"
                            title="Usar tu inicial"
                            aria-label="Usar tu inicial"
                        >{{ mb_substr($name ?: 'U', 0, 1) }}</button>

                        @foreach ($avatars as $key => $emoji)
                            <button
                                type="button"
                                wire:key="avatar-{{ $key }}"
                                wire:click="selectAvatar(@js($key))"
                                class="avatar-option"
                                aria-pressed="{{ $avatar === $key ? 'true' : 'false' }}"
                                aria-label="Icono {{ $key }}"
                            >{{ $emoji }}</button>
                        @endforeach
                    </div>
                </div>

                <form wire:submit="saveProfile" class="mt-6 flex flex-col gap-4 border-t border-ink/10 pt-5">
                    <div>
                        <label for="profile-name" class="field-label">Nombre</label>
                        <input id="profile-name" type="text" wire:model="name" maxlength="80" class="field" required @error('name') aria-invalid="true" @enderror>
                        @error('name')
                            <p class="field-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="profile-email" class="field-label">Correo electrónico</label>
                        <input id="profile-email" type="email" wire:model="email" maxlength="255" class="field" required @error('email') aria-invalid="true" @enderror>
                        @error('email')
                            <p class="field-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="profile-bio" class="field-label">Frase de perfil</label>
                        <input id="profile-bio" type="text" wire:model="bio" maxlength="160" placeholder="Qué estás escuchando últimamente" class="field" @error('bio') aria-invalid="true" @enderror>
                        @error('bio')
                            <p class="field-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <button type="submit" class="btn-primary rounded-full px-5 py-2.5 text-sm font-semibold transition">
                            <span wire:loading.remove wire:target="saveProfile">Guardar cambios</span>
                            <span wire:loading wire:target="saveProfile">Guardando…</span>
                        </button>
                    </div>
                </form>
            </section>

            {{-- Contraseña --}}
            <section class="glass rounded-3xl p-5 sm:p-6" aria-labelledby="password-heading">
                <h2 id="password-heading" class="section-title text-ink">Contraseña</h2>

                <form wire:submit="updatePassword" class="mt-5 flex flex-col gap-4">
                    <div>
                        <label for="current-password" class="field-label">Contraseña actual</label>
                        <input id="current-password" type="password" wire:model="current_password" autocomplete="current-password" class="field" @error('current_password') aria-invalid="true" @enderror>
                        @error('current_password')
                            <p class="field-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="new-password" class="field-label">Nueva contraseña</label>
                            <input id="new-password" type="password" wire:model="new_password" autocomplete="new-password" class="field" @error('new_password') aria-invalid="true" @enderror>
                        </div>

                        <div>
                            <label for="new-password-confirmation" class="field-label">Repite la nueva contraseña</label>
                            <input id="new-password-confirmation" type="password" wire:model="new_password_confirmation" autocomplete="new-password" class="field">
                        </div>
                    </div>
                    @error('new_password')
                        <p class="field-error -mt-2" role="alert">{{ $message }}</p>
                    @enderror

                    <div>
                        <button type="submit" class="btn-secondary rounded-full px-5 py-2.5 text-sm font-semibold transition">
                            Actualizar contraseña
                        </button>
                    </div>
                </form>
            </section>
        </div>

        {{-- Temas --}}
        <section class="glass min-w-0 rounded-3xl p-5 sm:p-6" aria-labelledby="themes-heading">
            <h2 id="themes-heading" class="section-title text-ink">Apariencia</h2>
            <p class="mt-0.5 text-sm text-ink/60">El tema cambia al instante. La luz de cada canción se mantiene en todos.</p>

            @foreach ($schemes as $scheme => $schemeLabel)
                <p class="eyebrow mt-5 mb-2.5">{{ $schemeLabel }}</p>

                <div class="theme-grid">
                    @foreach ($themes as $key => $item)
                        @continue($item['scheme'] !== $scheme)

                        <button
                            type="button"
                            wire:key="theme-{{ $key }}"
                            wire:click="selectTheme(@js($key))"
                            x-data
                            x-on:click="window.applyOndaTheme(@js($key))"
                            class="theme-card"
                            aria-pressed="{{ $theme === $key ? 'true' : 'false' }}"
                        >
                            <span class="theme-swatch" style="background: {{ $item['paper'] }}; color: {{ $item['ink'] }};" aria-hidden="true">
                                <span class="theme-swatch-glow" style="background: linear-gradient(135deg, {{ $item['glow1'] }}, {{ $item['glow2'] }});"></span>
                                <span>Aa</span>
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-ink">{{ $item['name'] }}</span>
                                <span class="block truncate text-xs text-ink/60">{{ $item['subtitle'] }}</span>
                            </span>

                            @if ($theme === $key)
                                <svg viewBox="0 0 24 24" class="size-4 shrink-0 text-ink" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-label="Tema activo"><path d="M5 13l4 4L19 7" /></svg>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endforeach

            {{-- Transparencia y fondo: los maneja el navegador (wire:ignore), el servidor solo guarda el nivel. --}}
            <div wire:ignore>
                <div class="mt-7 border-t border-ink/10 pt-5" x-data="{ level: @js($transparency) }">
                    <div class="flex items-baseline justify-between gap-3">
                        <label for="transparency" class="text-sm font-semibold text-ink">Transparencia</label>
                        <output for="transparency" class="text-xs text-ink/60 tabular-nums" x-text="level + ' %'">{{ $transparency }} %</output>
                    </div>
                    <p class="mt-0.5 text-xs text-ink/60">Cuánto dejan ver los paneles lo que tienen detrás. Arrastra y míralo cambiar.</p>

                    <input
                        id="transparency"
                        type="range"
                        min="0"
                        max="100"
                        step="1"
                        value="{{ $transparency }}"
                        class="range range-control mt-3 w-full"
                        style="--progress: {{ $transparency }}%"
                        x-model.number="level"
                        x-bind:style="{ '--progress': level + '%' }"
                        x-on:input="window.applyOndaTransparency($event.target.value)"
                        x-on:change="$wire.saveTransparency(Number($event.target.value))"
                    >
                    <div class="mt-1 flex justify-between text-[11px] text-ink/45" aria-hidden="true">
                        <span>Sólido</span>
                        <span>Cristal</span>
                    </div>
                </div>

                <div
                    class="mt-7 border-t border-ink/10 pt-5"
                    x-data="{
                        current: null,
                        busy: false,
                        error: '',
                        dim: 55,
                        async init() {
                            this.dim = window.OndaBackdrop?.dim() ?? 55;
                            this.current = (await window.OndaBackdrop?.mount()) ?? null;
                        },
                        async pick(event) {
                            const file = event.target.files[0];
                            event.target.value = '';
                            if (! file) return;

                            this.error = '';
                            this.busy = true;

                            try {
                                this.current = await window.OndaBackdrop.set(file);
                            } catch (problem) {
                                this.error = problem?.message || 'No se pudo usar ese archivo. Prueba con otro.';
                            } finally {
                                this.busy = false;
                            }
                        },
                        async remove() {
                            this.error = '';
                            this.current = await window.OndaBackdrop.clear();
                        },
                    }"
                    x-on:onda:backdrop-changed.window="current = $event.detail"
                >
                    <p class="text-sm font-semibold text-ink">Fondo</p>
                    <p class="mt-0.5 text-xs text-ink/60">Pon una imagen o un video ligero detrás de toda la interfaz. Sube la transparencia para que se vea más.</p>

                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <div class="backdrop-preview" x-bind:class="busy && 'animate-pulse'">
                            <template x-if="current?.kind === 'image'">
                                <img x-bind:src="current.url" alt="Fondo actual">
                            </template>
                            <template x-if="current?.kind === 'video'">
                                <video x-bind:src="current.url" muted loop autoplay playsinline aria-label="Fondo actual"></video>
                            </template>
                            <template x-if="! current">
                                <span>Sin fondo</span>
                            </template>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="btn-primary cursor-pointer rounded-full px-4 py-2 text-xs font-semibold whitespace-nowrap transition focus-within:outline-2 focus-within:outline-offset-2">
                                    <span x-text="busy ? 'Preparando…' : (current ? 'Cambiar fondo' : 'Elegir fondo')">Elegir fondo</span>
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp,image/avif,image/gif,video/mp4,video/webm,video/quicktime"
                                        class="sr-only"
                                        x-on:change="pick($event)"
                                        x-bind:disabled="busy"
                                        aria-label="Elegir imagen o video de fondo"
                                    >
                                </label>

                                <button type="button" x-show="current" x-cloak x-on:click="remove()" class="btn-secondary rounded-full px-4 py-2 text-xs font-medium whitespace-nowrap transition">
                                    Quitar fondo
                                </button>
                            </div>

                            <p class="field-error" role="alert" x-show="error" x-text="error" x-cloak></p>
                            <p class="mt-2 text-[11px] leading-relaxed text-ink/45">Se guarda en este dispositivo, no en tu cuenta. Imágenes JPG, PNG, WebP o GIF; videos MP4 o WebM de hasta 30 MB.</p>
                        </div>
                    </div>

                    <div class="mt-5" x-show="current" x-cloak>
                        <div class="flex items-baseline justify-between gap-3">
                            <label for="backdrop-dim" class="text-sm font-medium text-ink">Atenuar el fondo</label>
                            <output for="backdrop-dim" class="text-xs text-ink/60 tabular-nums" x-text="dim + ' %'"></output>
                        </div>

                        <input
                            id="backdrop-dim"
                            type="range"
                            min="0"
                            max="90"
                            step="1"
                            class="range range-control mt-3 w-full"
                            x-model.number="dim"
                            x-bind:style="{ '--progress': (dim / 90 * 100) + '%' }"
                            x-on:input="window.OndaBackdrop.setDim($event.target.value)"
                        >
                        <div class="mt-1 flex justify-between text-[11px] text-ink/45" aria-hidden="true">
                            <span>Fondo vivo</span>
                            <span>Texto más legible</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
