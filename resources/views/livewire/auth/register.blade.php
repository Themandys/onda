<div class="glass rounded-3xl p-6 sm:p-7">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Crea tu cuenta</h1>
    <p class="mt-1 text-sm text-ink/60">Es la única cuenta de esta aplicación: en cuanto la crees, el registro se cierra.</p>

    <form wire:submit="register" class="mt-6 flex flex-col gap-4" novalidate>
        <label class="flex flex-col gap-1.5">
            <span class="text-xs font-medium text-ink/70">Nombre</span>
            <input
                type="text"
                wire:model="name"
                autocomplete="name"
                autofocus
                required
                maxlength="60"
                class="field"
                @error('name') aria-invalid="true" @enderror
            >
            @error('name')
                <span class="text-xs text-danger" role="alert">{{ $message }}</span>
            @enderror
        </label>

        <label class="flex flex-col gap-1.5">
            <span class="text-xs font-medium text-ink/70">Correo</span>
            <input
                type="email"
                wire:model="email"
                autocomplete="username"
                required
                class="field"
                @error('email') aria-invalid="true" @enderror
            >
            @error('email')
                <span class="text-xs text-danger" role="alert">{{ $message }}</span>
            @enderror
        </label>

        <label class="flex flex-col gap-1.5">
            <span class="text-xs font-medium text-ink/70">Contraseña</span>
            <input
                type="password"
                wire:model="password"
                autocomplete="new-password"
                required
                class="field"
                @error('password') aria-invalid="true" @enderror
            >
            @error('password')
                <span class="text-xs text-danger" role="alert">{{ $message }}</span>
            @else
                <span class="text-xs text-ink/60">Mínimo 8 caracteres.</span>
            @enderror
        </label>

        <label class="flex flex-col gap-1.5">
            <span class="text-xs font-medium text-ink/70">Repite la contraseña</span>
            <input
                type="password"
                wire:model="password_confirmation"
                autocomplete="new-password"
                required
                class="field"
            >
        </label>

        <button type="submit" class="btn-primary mt-1 flex items-center justify-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold transition active:scale-[0.98]">
            <span wire:loading.remove wire:target="register">Crear cuenta y entrar</span>
            <span wire:loading wire:target="register">Creando…</span>
        </button>
    </form>
</div>
