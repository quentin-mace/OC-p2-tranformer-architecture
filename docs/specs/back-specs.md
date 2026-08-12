# Spécification back-end — Renote

Consolide l'ensemble des décisions d'architecture back prises au fil du projet (analyse de l'existant, analyse de l'écart, justifications). Alimente les sections **Architecture cible**, **Analyse de l'écart** et **Justification de l'approche architecturale** du modèle de documentation. Pour le détail des endpoints, voir [`api-specs.md`](./api-specs.md).

## 1. Objectif et périmètre

Découpler le back de l'affichage : Laravel n'expose plus de vues, mais une **API REST** consommée par le front React (et potentiellement, à terme, un client mobile). Toutes les fonctionnalités actuelles de l'application sont conservées ; seule leur implémentation change.

Stack conservée : Laravel 12 (PHP ≥ 8.4), Eloquent, SQLite.

## 2. Principe d'architecture : MVC + SOLID

Le constat de départ (cf. `architecture-analyse.md`) est que les composants Livewire actuels cumulent cinq responsabilités dans un même fichier (état UI, validation, autorisation, accès aux données, rendu). La cible sépare ces responsabilités en couches, chacune n'ayant qu'une seule raison de changer (principe de responsabilité unique) :

```
Route  →  Controller  →  Service  →  Model (Eloquent)  →  SQLite
              ↓              ↓
        Form Request   règles métier
        (validation)   (ex: unicité,
                        cohérence des
                        données)
```

- **Models** : conservés tels quels, seule couche à parler à Eloquent/SQLite.
- **Services** *(nouveau)* : portent la logique métier, indépendants du HTTP — appelables aussi bien depuis un contrôleur que depuis une commande Artisan ou un test.
- **Controllers** *(nouveau, actuellement quasi vides)* : un point d'entrée par route, orchestrent l'appel au Form Request (validation), au service (logique métier) puis au model (accès donnée), et retournent une réponse JSON sérialisée. Aucune règle métier n'y est écrite directement.
- **Form Requests** *(nouveau)* : isolent la validation des entrées, hors du contrôleur.

## 3. Organisation des dossiers

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── AuthController.php      // register, login, logout, forgot/reset password, vérification email
│   │       ├── UserController.php      // profil courant, mise à jour profil/mot de passe, suppression de compte
│   │       ├── NoteController.php      // index, store, destroy
│   │       └── TagController.php       // index, store
│   └── Requests/
│       ├── Auth/
│       │   ├── RegisterRequest.php
│       │   ├── LoginRequest.php
│       │   ├── ForgotPasswordRequest.php
│       │   └── ResetPasswordRequest.php
│       ├── User/
│       │   ├── UpdateProfileRequest.php
│       │   ├── UpdatePasswordRequest.php
│       │   └── DeleteAccountRequest.php
│       ├── StoreNoteRequest.php
│       └── StoreTagRequest.php
├── Services/
│   ├── AuthService.php                 // Auth::attempt, gestion du token, reset de mot de passe
│   ├── UserService.php                 // mise à jour profil, changement de mot de passe, suppression de compte
│   ├── NoteService.php                 // règles métier de création/suppression de note
│   └── TagService.php                  // règles métier de création de tag
├── Models/
│   ├── Note.php                        // inchangé
│   ├── Tag.php                         // inchangé (cf. point ouvert §6)
│   └── User.php                        // + trait HasApiTokens (Sanctum)
└── Providers/

routes/
└── api.php                             // toutes les routes listées dans api-specs.md
```

Regroupement par domaine métier (Auth, User, Notes, Tags) à l'intérieur de chaque couche technique, cohérent avec l'organisation adoptée côté front (cf. `front-specs.md`).

## 4. Authentification

Bascule d'une auth **stateful** (session + cookie, `guard: web`) vers une auth **par token** via **Laravel Sanctum** (*personal access tokens*) :

- un client mobile ne peut pas gérer un cookie de session comme un navigateur ;
- un token porté dans l'en-tête `Authorization: Bearer {token}` fonctionne identiquement sur web et mobile ;
- pas de gestion CSRF nécessaire sur une API sans cookie.

Changements requis : installer `laravel/sanctum`, ajouter le trait `HasApiTokens` au modèle `User`, migrer la table `personal_access_tokens`, protéger les routes authentifiées avec le middleware `auth:sanctum`.

Le rate limiting sur la connexion (5 tentatives) et le throttle sur le renvoi du lien de vérification (6/min) sont conservés à l'identique.

## 5. Format de réponse et codes HTTP

Contrat imposé : `{status, message, data}` sur toutes les réponses, y compris les erreurs (détail des erreurs de validation niché dans `data.errors`). Détail complet des codes HTTP utilisés (200/201/401/404/422/429) et de leur usage : voir [`api-specs.md` §1](./api-specs.md#1-conventions-générales).

## 6. Sécurité

- **Scoping par utilisateur** : chaque requête sur une ressource (note) est filtrée par l'utilisateur authentifié ; une ressource d'un autre utilisateur répond **404**, jamais 403, pour ne pas révéler son existence.
- **Validation côté back systématique**, indépendante de celle du front : l'API reste accessible directement (hors interface), le front ne peut donc pas être la seule ligne de défense.
- **Mots de passe hashés** (`Hash::make`, déjà en place), **email unique**, comportements conservés.
- **Tags personnels** : décision prise de rendre les tags par utilisateur, cohérent avec le scoping déjà appliqué aux notes. Ajout d'une colonne `user_id` sur `tags` (FK `onDelete('cascade')`) + index unique `(user_id, name)` (deux utilisateurs peuvent avoir chacun un tag `courses`). `TagService` filtre par `user_id`, `NoteService::createForUser` vérifie que le `tag_id` appartient bien à l'utilisateur (sinon `422`).

## 7. Analyse de l'écart (back)

**À ajouter**
- Contrôleurs API : `AuthController` (register/login/logout/forgot-password/reset-password/vérification email), `UserController` (profil/mot de passe/suppression de compte), `NoteController` et `TagController` (CRUD restreint aux actions existantes)
- Services : externalisation de la logique métier actuellement mélangée dans les composants Livewire/Volt
- Form Requests : une par action d'écriture (cf. arborescence §3)
- Routes API (`routes/api.php`), sur le modèle REST, remplaçant les routes Blade/Volt actuelles
- Sanctum (package + migration + trait `HasApiTokens`)

**À conserver**
- Models (`Note`, `Tag`, `User`) et leurs relations Eloquent
- Migrations existantes (notes, tags, users), complétées par une nouvelle migration ajoutant `user_id` sur `tags` (cf. §6)
- Règles de validation métier déjà identifiées (unicité email/tag, complexité mot de passe, rate limiting login/vérification)

**À supprimer**
- Composants Livewire/Volt (`app/Livewire/**`) — responsabilités reportées sur Controller/Service/Form Request
- Vues Blade servant l'UI actuelle (`resources/views/livewire/**`, `resources/views/dashboard.blade.php`) — le rendu est repris intégralement par le front React
- `app/Providers/VoltServiceProvider.php` et la dépendance `livewire/volt`, une fois la migration terminée
- Routes `routes/web.php` / `routes/auth.php` actuelles, remplacées par `routes/api.php` (`welcome.blade.php` peut rester en l'état, hors périmètre applicatif)

## 8. Justification des choix

Voir `architecture-analyse.md` §5-6 pour le détail des forces/faiblesses de l'existant motivant cette réorganisation. En synthèse : l'architecture MVC + SOLID répond directement aux limites de testabilité et de couplage identifiées (couplage direct à Eloquent, règles métier disséminées, duplication de la logique d'autorisation), et le passage à une API REST avec contrat stable (`status/message/data`) est la condition nécessaire pour qu'un même back serve plusieurs clients (web, mobile) sans duplication de logique.