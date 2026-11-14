<div class="glass rounded-3xl p-6 sm:p-7">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Inicia sesión</h1>
    <p class="mt-1 text-sm text-ink/60">Tu música, tus listas y tu historial te esperan.</p>

    <form wire:submit="login" class="mt-6 flex flex-col gap-4" novalidate>
        <label class="flex flex-col gap-1.5">
            <span class="text-xs font-medium text-ink/70">Correo</span>
            <input
                type="email"
                wire:model="email"
                autocomplete="username"
                autofocus
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
                autocomplete="current-password"
                required
                class="field"
                @error('password') aria-invalid="true" @enderror
            >
            @error('password')
                <span class="text-xs text-danger" role="alert">{{ $message }}</span>
            @enderror
        </label>

        <label class="flex items-center gap-2 text-sm text-ink/70">
            <input type="checkbox" wire:model="remember" class="size-4 rounded accent-ink">
            Mantener la sesión iniciada
        </label>

        <button type="submit" class="btn-primary mt-1 flex items-center justify-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold transition active:scale-[0.98]">
            <span wire:loading.remove wire:target="login">Entrar</span>
            <span wire:loading wire:target="login">Entrando…</span>
        </button>
    </form>

    <p class="mt-5 text-center text-sm text-ink/60">
        ¿Aún no tienes cuenta?
        <a href="{{ route('register') }}" wire:navigate class="font-medium text-ink underline-offset-4 hover:underline">Regístrate</a>
    </p>
</div>
