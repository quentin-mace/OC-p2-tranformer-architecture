# Transformez l'architecture d'une application existante

# Renote

> **Branche `feat/implement-routing`** : version finale et à jour de l'API. La branche `main` est conservée pour référence de l'ancienne version (monolithe Blade/Livewire), ne pas l'utiliser pour développer.

Renote est une API permettant de prendre et stocker des notes. Un utilisateur peut :
- créer un compte, se connecter, se déconnecter
- créer des tags
- créer des notes et leur associer un tag
- consulter et supprimer ses notes

Ce dépôt contient uniquement le backend (API REST Laravel + Sanctum). Il n'y a pas d'interface web : l'application est consommée par le front React, dépôt [`oc_p3_frontend`](https://github.com/quentin-mace/oc_p3_frontend).

Contrat d'API détaillé : [`docs/specs/api-specs.md`](docs/specs/api-specs.md). Architecture cible du front : [`docs/specs/front-specs.md`](docs/specs/front-specs.md) (note, le front livré a un périmètre réduit par rapport à cette cible, pas de gestion de profil ni de mot de passe oublié, voir le README du dépôt front).

## Installer

Deux options : un setup local (PHP CLI) ou Docker (recommandé si le port 80 est déjà utilisé, par exemple par Traefik).

### Option A — Docker (recommandé)

Nécessite Docker et Docker Compose v2.

1. Cloner ce projet
2. Copier `.env.example` en `.env`
3. Build et démarrer les conteneurs :
   ```
   docker compose up -d --build
   ```
4. Installer les dépendances PHP et initialiser l'app :
   ```
   docker compose exec app composer install
   docker compose exec app php artisan key:generate
   docker compose exec app touch database/database.sqlite
   docker compose exec app php artisan migrate
   ```
5. L'API est disponible sur http://127.0.0.1:8000/api

Pour tout arrêter : `docker compose down`.

### Option B — Local

1. Installer PHP 8.4+, les extensions requises et Composer :
   ```
   sudo apt install php php-cli php-mbstring php-xml php-curl php-sqlite3 unzip
   ```
   Composer : https://getcomposer.org/download/
2. Cloner ce projet
3. Copier `.env.example` en `.env`
4. Installer les dépendances : `composer install`
5. Générer la clé d'application : `php artisan key:generate`
6. Créer la base SQLite et migrer :
   ```
   touch database/database.sqlite
   php artisan migrate
   ```
7. Démarrer le serveur : `php artisan serve`
8. L'API est disponible sur http://127.0.0.1:8000/api

## Tester

```
composer test
```
ou directement :
```
php artisan test
```

## Documentation de l'API

La documentation des endpoints est générée avec Scribe :
```
php artisan scribe:generate
```
Elle est ensuite consultable sur `/docs`.
