{{--
    Apariencia: colores del tema activo, transparencia de los paneles y el
    script que los cambia sin recargar.

    Variables:
      $theme         array  configuración del tema (User::THEMES)
      $themeKey      ?string clave del tema; con ella se recuerda en este navegador
      $transparency  ?int   0-100; de serie, User::DEFAULT_TRANSPARENCY
      $restore       ?bool  páginas sin sesión (acceso, modo sin conexión): se
                            usa la apariencia que este navegador recuerda

    El tema solo fija papel, tinta, corazón y la luz "de reposo"; cuando
    suena una canción, el reproductor pone encima la luz de su portada.
--}}
@php
    $transparency ??= \App\Models\User::DEFAULT_TRANSPARENCY;
    $themeKey ??= null;
    $restore ??= false;
@endphp
<style id="onda-theme-vars">
    :root {
        --color-paper: {{ $theme['paper'] }};
        --color-ink: {{ $theme['ink'] }};
        --color-heart: {{ $theme['heart'] }};
        --theme-glow-1: {{ $theme['glow1'] }};
        --theme-glow-2: {{ $theme['glow2'] }};
        --theme-crystal-1: {{ $theme['crystal1'] }};
        --theme-crystal-2: {{ $theme['crystal2'] }};
        --translucency: {{ number_format($transparency / 100, 2, '.', '') }};
    }
</style>
<script>
    window.ONDA_THEMES = @js(\App\Models\User::THEMES);

    (() => {
        const root = document.documentElement;
        const APPEARANCE = 'onda:appearance';
        const BACKDROP = 'onda:backdrop';

        const read = (key) => {
            try {
                return JSON.parse(localStorage.getItem(key)) || {};
            } catch {
                return {};
            }
        };

        // El modo sin conexión no puede preguntar al servidor: la apariencia se recuerda aquí.
        const remember = (change) => {
            try {
                localStorage.setItem(APPEARANCE, JSON.stringify({ ...read(APPEARANCE), ...change }));
            } catch {}
        };

        window.applyOndaTheme ??= function (key, config) {
            const theme = config || window.ONDA_THEMES?.[key];
            if (! theme) return;

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
            remember({ theme: key });

            // El reproductor recalcula la luz de la canción para el nuevo esquema.
            if (changed) window.dispatchEvent(new CustomEvent('onda:theme-applied', { detail: { key, scheme: theme.scheme } }));
        };

        // Transparencia de los paneles: 0 = sólidos, 100 = cristal casi transparente.
        window.applyOndaTransparency ??= function (level) {
            const value = Math.max(0, Math.min(100, Math.round(Number(level)) || 0));

            root.style.setProperty('--translucency', (value / 100).toFixed(2));
            remember({ transparency: value });
        };

        @if ($restore)
            const saved = read(APPEARANCE);
            if (saved.theme && window.ONDA_THEMES[saved.theme]) window.applyOndaTheme(saved.theme);
            if (Number.isFinite(saved.transparency)) window.applyOndaTransparency(saved.transparency);
        @elseif ($themeKey)
            remember({ theme: @js($themeKey), transparency: @js((int) $transparency) });
        @endif

        // Fondo personalizado: se reserva su sitio antes del primer pintado; la imagen o el video llegan después.
        const backdrop = read(BACKDROP);
        if (backdrop.kind) {
            root.dataset.backdrop = backdrop.kind;
            if (Number.isFinite(backdrop.dim)) root.style.setProperty('--backdrop-dim', (backdrop.dim / 100).toFixed(2));
        }

        if (window.ondaThemeBound) return;
        window.ondaThemeBound = true;

        // Lo emite el perfil (Livewire) al elegir un tema.
        window.addEventListener('theme-changed', (event) => {
            const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
            if (detail?.theme) window.applyOndaTheme(detail.theme, detail.config);
        });

        // wire:navigate cambia el <body>, no los atributos de <html>: se conserva el tema en uso.
        document.addEventListener('livewire:navigated', () => {
            const key = root.dataset.theme;
            if (key) window.applyOndaTheme(key);
        });
    })();
</script>
