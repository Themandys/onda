{{--
    Colores del tema activo y el script que cambia de tema sin recargar.
    Variables: $theme (configuración de User::THEMES).

    El tema solo fija papel, tinta, corazón y la luz "de reposo"; cuando
    suena una canción, el reproductor pone encima la luz de su portada.
--}}
<style id="onda-theme-vars">
    :root {
        --color-paper: {{ $theme['paper'] }};
        --color-ink: {{ $theme['ink'] }};
        --color-heart: {{ $theme['heart'] }};
        --theme-glow-1: {{ $theme['glow1'] }};
        --theme-glow-2: {{ $theme['glow2'] }};
        --theme-crystal-1: {{ $theme['crystal1'] }};
        --theme-crystal-2: {{ $theme['crystal2'] }};
    }
</style>
<script>
    window.ONDA_THEMES = @js(\App\Models\User::THEMES);

    window.applyOndaTheme ??= function (key, config) {
        const theme = config || window.ONDA_THEMES?.[key];
        if (! theme) return;

        const root = document.documentElement;
        const changed = root.dataset.theme !== key;

        root.dataset.theme = key;
        root.dataset.scheme = theme.scheme;
        root.classList.toggle('dark', theme.scheme === 'dark');
        root.classList.toggle('light', theme.scheme !== 'dark');

        const variables = {
            '--color-paper': theme.paper,
            '--color-ink': theme.ink,
            '--color-heart': theme.heart,
            '--theme-glow-1': theme.glow1,
            '--theme-glow-2': theme.glow2,
            '--theme-crystal-1': theme.crystal1,
            '--theme-crystal-2': theme.crystal2,
        };
        for (const [name, value] of Object.entries(variables)) root.style.setProperty(name, value);

        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme.paper);
        document.querySelector('meta[name="color-scheme"]')?.setAttribute('content', theme.scheme);

        // El reproductor recalcula la luz de la canción para el nuevo esquema.
        if (changed) window.dispatchEvent(new CustomEvent('onda:theme-applied', { detail: { key, scheme: theme.scheme } }));
    };

    if (! window.ondaThemeBound) {
        window.ondaThemeBound = true;

        // Lo emite el perfil (Livewire) al elegir un tema.
        window.addEventListener('theme-changed', (event) => {
            const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
            if (detail?.theme) window.applyOndaTheme(detail.theme, detail.config);
        });

        // wire:navigate cambia el <body>, no los atributos de <html>: se conserva el tema en uso.
        document.addEventListener('livewire:navigated', () => {
            const key = document.documentElement.dataset.theme;
            if (key) window.applyOndaTheme(key);
        });
    }
</script>
