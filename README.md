# Transformez l'architecture d'une application existante

> **Cette branche (`main`) est une version antérieure, conservée pour référence (ancien monolithe Blade/Livewire).** La version finale à jour, l'API Laravel consommée par le front React, est sur la branche `feat/implement-routing`.

# Plot

Renote is an application that allows user to take and store notes.
In renote, a user can:
- create notes
- visualize notes
- define relationship between the notes
- define tags
- and associate a tag to a note.

## Install

Two options are available: a local setup (Herd on Windows/macOS, PHP CLI on Linux) or a Docker setup (recommended if port 80 is already used on your machine, e.g. by Traefik).

### Option A — Docker (recommended)

Requires Docker and Docker Compose v2.

1. Clone this project
2. Copy `.env.example` to `.env`
3. Build and start the containers:
   ```
   docker compose up -d --build
   ```
4. Install PHP dependencies and initialize the app:
   ```
   docker compose exec app composer install
   docker compose exec app php artisan key:generate
   docker compose exec app touch database/database.sqlite
   docker compose exec app php artisan migrate
   ```
5. Open http://127.0.0.1:8000

The `node` service runs `npm install` then `npm run dev` (Vite on port 5173) automatically.

To stop everything: `docker compose down`.

### Option B — Local install

1. Install Php, Composer and Laravel:

   - On Windows or macOS, install Laravel's Herd:
   https://laravel.com/docs/12.x/installation#installation-using-herd

   - On Linux, Herd is not available. Install manually instead:
     - Php and required extensions, e.g. on Ubuntu/Debian:
       ```
       sudo apt install php php-cli php-mbstring php-xml php-curl php-sqlite3 php-mysql unzip
       ```
     - Composer: https://getcomposer.org/download/
     - Optionally, [Valet Linux](https://cpriego.github.io/valet-linux/) can reproduce Herd/Valet's experience (`.test` domains, no port to manage).

2. Install node v22

Install node version manager (MVN).
On Windows you can use this distribution:
https://github.com/coreybutler/nvm-windows#readme


3. Clone this project

4. Copy `.env.example` to `.env`

5. Generate new APP_KEY with `php artisan key:generate`

6. Run `npm i` and `npm run dev`

7. Run `php artisan migrate`

8. Start Herd (Windows/macOS), or on Linux run `php artisan serve`

9. Access to Herd link from your browser (Windows/macOS), or `http://127.0.0.1:8000` on Linux

You are setup!
