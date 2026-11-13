# Onda

Reproductor web de música en streaming construido con **Laravel 13**, **Livewire 4** y **Tailwind CSS 4**.

## Características principales

- **Reproductor persistente de doble plato (`<audio>`):** navegación SPA sin cortes (`@persist`), precarga anticipada de la siguiente canción e iluminación ambiental dinámica calculada a partir de los colores de la portada.
- **Modos Aleatorio (Shuffle) y DJ:** mezcla suave (*crossfade* de igual potencia de 7 segundos) entre pistas y recarga automática de canciones del mismo artista y éxitos del día cuando la fila está por terminar.
- **Fila de reproducción y vista de portada en alta resolución:** gestión en vivo de la cola, control de velocidad (`0.5×` a `2×`) y zoom de carátula.
- **Exploración por géneros, novedades y sugerencias:** listas diarias y lanzamientos recientes por región (`Apple Music` / `iTunes RSS`) y páginas dedicadas por género (`/genres/{genre}` y `/new`).
- **Ficha de artista (`/artist`):** foto en alta resolución, seguidores, álbumes y artistas similares (`Deezer`) junto con biografía en español (`Wikipedia`) y catálogo completo reproducible.
- **Modo sin conexión en la app + descarga en dispositivo:** almacenamiento de audio en `IndexedDB` para reproducir sin internet dentro de Onda y descarga directa de archivo (`/download/{videoId}`).
- **Biblioteca, playlists, favoritos y resumen mensual (`/recap`):** estadísticas mensuales de tiempo escuchado, canción #1 del mes y artistas más reproducidos.
- **Perfil y apariencia (`/profile`):** subida de foto de perfil con recorte circular, avatares musicales y **13 temas** (oscuros y claros, con «Grafito» como tema oscuro por defecto).

---

## Requisitos

- PHP 8.3+ (con extensiones `pdo_sqlite`, `mbstring`, `curl`, `gd`, `intl`)
- Composer 2+
- Node.js 20+ y npm
- SQLite 3

---

## Instalación en local

```bash
# 1. Instalar dependencias de PHP y frontend
composer install
npm install

# 2. Configurar el entorno y generar la clave de aplicación
cp .env.example .env
php artisan key:generate

# 3. Crear la base de datos SQLite, ejecutar migraciones y enlazar storage
touch database/database.sqlite
php artisan migrate
php artisan storage:link

# 4. Compilar los assets de producción (o usar npm run dev)
npm run build
```

Con **Laravel Herd**, el proyecto queda disponible automáticamente en `http://onda.test`. Sin Herd, puedes arrancarlo con:

```bash
php artisan serve
```

---

## Tests y estilo de código

```bash
php artisan test
./vendor/bin/pint --test
```

---

## Paso a paso para publicar en producción

### 1. Subir el repositorio a GitHub

Si aún no has creado el repositorio remoto en tu cuenta de GitHub (`recolection`):

1. Entra en [https://github.com/new](https://github.com/new) y crea un repositorio vacío llamado **`onda`** (sin marcar *Add a README* ni *.gitignore*).
2. En la carpeta del proyecto, enlaza el remoto y sube la rama `main`:

```bash
git remote add origin https://github.com/recolection/onda.git
git push -u origin main
```

### 2. Despliegue en un servidor VPS (Ubuntu + Nginx + PHP 8.4 + SQLite)

1. **Clonar el repositorio en el servidor:**
   ```bash
   cd /var/www
   git clone https://github.com/recolection/onda.git
   cd onda
   ```
2. **Instalar dependencias y compilar el frontend:**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```
3. **Configurar `.env` de producción:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Ajusta estas variables en `.env`:
   ```ini
   APP_NAME=Onda
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://tu-dominio.com

   DB_CONNECTION=sqlite
   MUSIC_CHARTS_REGION=mx
   MUSIC_CHARTS_TIMEZONE=America/Mexico_City
   MUSIC_INNERTUBE_ENABLED=true
   ```
4. **Inicializar SQLite, enlace público de fotos y caché de Laravel:**
   ```bash
   touch database/database.sqlite
   php artisan migrate --force
   php artisan storage:link
   php artisan optimize
   chown -R www-data:www-data storage bootstrap/cache database
   ```
5. **Configurar Nginx y HTTPS:**
   Apunta el `root` del sitio en Nginx a `/var/www/onda/public` y activa el certificado SSL con Certbot:
   ```bash
   sudo certbot --nginx -d tu-dominio.com
   ```
