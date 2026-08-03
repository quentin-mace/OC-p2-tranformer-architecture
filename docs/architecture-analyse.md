# Analyse de l'architecture — projet Renote

Ce document résume l'analyse de l'architecture existante de l'application **Renote**, dans le cadre du projet OpenClassrooms « Transformez l'architecture d'une application existante ».

---

## 1. Vue d'ensemble du projet

**Renote** est une application de prise de notes permettant à un utilisateur de :
- créer, visualiser et supprimer des notes
- définir des tags
- associer un tag à une note
- (à terme) définir des relations entre les notes

### Stack technique

| Couche | Technologie |
|---|---|
| Backend | Laravel 12 (PHP) |
| Frontend | Livewire + Volt (composants full-stack server-driven) |
| Base de données | SQLite (par défaut) |
| Assets | Vite |
| Auth | Starter kit Laravel (session + cookies) |
| Déploiement dev | Local (Herd/PHP CLI) ou Docker Compose |

### Domaine métier

Trois entités principales :
- **User** — utilisateur authentifié
- **Note** — texte appartenant à un user, associée à un tag (`belongsTo User`, `belongsTo Tag`)
- **Tag** — étiquette regroupant des notes (`hasMany Note`)

---

## 2. Organisation du code

```
app/
├── Http/Controllers/       ← quasi vide (Controller.php abstrait vide)
│   └── Auth/
│       └── VerifyEmailController.php
├── Livewire/               ← cœur de l'application
│   ├── Notes.php
│   ├── TagForm.php
│   └── Actions/
│       └── Logout.php
├── Models/
│   ├── Note.php
│   ├── Tag.php
│   └── User.php
└── Providers/

resources/views/
├── dashboard.blade.php     ← charge <livewire:notes /> et <livewire:tag-form />
└── livewire/
    ├── notes.blade.php
    ├── tag-form.blade.php
    ├── auth/               ← composants Volt (login, register, etc.)
    └── settings/

routes/
├── web.php                 ← routes minimales, majoritairement des vues
├── auth.php                ← routes d'authentification (Volt)
└── console.php
```

---

## 3. Flux de données

### Principe général

**Il n'y a (presque) pas d'endpoints back au sens REST classique.**

- `app/Http/Controllers/Controller.php` est une classe abstraite **vide**.
- Aucun controller REST pour Notes/Tags.
- Les routes `/notes`, `/tags`, `/dashboard` rendent toutes la même vue `dashboard.blade.php`.

### Où la donnée est-elle manipulée ?

Toute la logique passe par **Livewire**. Quand l'utilisateur ouvre `/dashboard` :

1. Laravel instancie les composants `Notes` et `TagForm` côté serveur.
2. `mount()` charge les données via **Eloquent** (`Tag::all()`, `Note::with('tag')->where('user_id', Auth::id())->latest()->get()`).
3. L'état du composant (propriétés publiques) est sérialisé dans le HTML.
4. Chaque interaction utilisateur (`wire:submit`, `wire:click`, `wire:model`) déclenche une **requête AJAX invisible** vers l'endpoint interne `/livewire/update`, qui :
   - ré-hydrate le composant côté serveur,
   - appelle la méthode PHP correspondante (`save()`, `delete($id)`),
   - renvoie le HTML re-rendu que Livewire injecte dans la page.

### Chaîne complète pour afficher les notes

```
SQLite (database/database.sqlite)
   ↑
Eloquent — Note::with('tag')->where('user_id', Auth::id())->latest()->get()
   ↑
Notes::loadNotes()  ← app/Livewire/Notes.php
   ↑
$this->notes (propriété publique du composant)
   ↑
resources/views/livewire/notes.blade.php  (@foreach $notes)
```

### Chaîne complète pour créer une note

```
Formulaire (wire:model="text" + wire:submit="save")
   ↓
POST /livewire/update  (géré par Livewire)
   ↓
Notes::save()  → validate() → Note::create([...])
   ↓
loadNotes() rafraîchit la liste
   ↓
HTML re-rendu envoyé au navigateur
```

### Communication inter-composants

`TagForm::save()` émet un événement Livewire `tagCreated`, écouté par `Notes` via `$listeners`. C'est de la communication événementielle **côté serveur**, au sein du runtime Livewire.

---

## 4. Qualification de l'architecture

### Nature

**Monolithe Laravel/Livewire à composants stateful server-rendered**, ou plus précisément :

- **Monolithique** : un seul processus PHP sert le HTML, gère l'état, écrit en base.
- **MVC dégénéré** : les Controllers sont vides ; Livewire remplace la couche C par des composants.
- **Component-based server-driven UI** : proche du modèle **Hotwire / Phoenix LiveView / Blazor Server** — l'état vit côté serveur, le DOM est patché via AJAX. Pas une SPA, pas un SSR classique.

### Structure en couches

```
┌────────────────────────────────┐
│  Composants Livewire           │  ← UI + validation + logique métier + accès données
│  (Notes, TagForm, ...)         │
├────────────────────────────────┤
│  Eloquent Models               │  ← ORM = seule couche « données »
│  (Note, Tag, User)             │
└────────────────────────────────┘
              ↓
          SQLite
```

**Deux couches** au lieu des 3+ d'une architecture applicative classique. Pas de Service, pas de Repository, pas de DTO, pas de Form Request, pas de Policy.

### Composants « fat »

`Notes.php` (~65 lignes) porte **cinq responsabilités** :

| Responsabilité | Où |
|---|---|
| État UI | `$text`, `$tag_id` |
| Validation | `$rules` |
| Autorisation | `->where('user_id', Auth::id())` inline |
| Accès données | `Note::with(...)->get()`, `Note::create(...)` |
| Rendu | `render()` |

C'est un **anti-pattern « Active UI »** : le composant de présentation parle directement à l'ORM.

---

## 5. Points forts de l'architecture

| Point fort | Bénéfice |
|---|---|
| Peu de code par fonctionnalité | Time-to-market très court |
| État unifié côté serveur | Moins de bugs de synchronisation front/back |
| Pas d'API publique | Surface d'attaque réduite |
| Un seul langage (PHP + Blade) | Onboarding rapide, pas de types dupliqués |
| SSR natif | SEO + first-contentful-paint |
| Conventions strictes Laravel/Livewire | Prévisibilité, courbe d'apprentissage courte |
| Couplage UI ↔ back atomique | Refactor UI dans un seul fichier |
| Livewire réactif | UX SPA sans écrire de JavaScript |
| Écosystème Laravel | Eloquent, migrations, auth, middleware gratuits |
| Mono-artefact | Déploiement trivial (`docker compose up`) |

**Verdict** : cette architecture optimise brutalement le ratio *fonctionnalités livrées / effort investi* pour des apps CRUD à faible complexité métier, mono-client (web only), avec une petite équipe qui maîtrise Laravel.

---

## 6. Points faibles / limites

- **Testabilité faible** : couplage direct à Eloquent, impossible de mocker sans base réelle.
- **Pas de séparation métier / infrastructure** : les règles métier sont disséminées entre validation Livewire, filtres SQL et contraintes de migration.
- **Pas d'ouverture** : aucune API, impossible de brancher un client mobile ou un front séparé.
- **God components** : `Notes.php` mélange 5 responsabilités.
- **Duplication de la logique d'autorisation** (`Auth::id()` répété partout).

Ces défauts ne deviennent des problèmes que si l'app grossit ou doit s'ouvrir à d'autres clients — d'où l'intérêt pédagogique du refactor.

---

## 7. Authentification

### Type

**Auth stateful classique Laravel** basée sur **session + cookies chiffrés**. Pas de JWT, pas de token dans un header.

```
Navigateur                    Serveur Laravel
─────────                     ───────────────
 Cookie laravel_session  ←──  Session ID (chiffré)
      ↕                                ↕
 Login form                    Session store
                                       ↕
                              users table (SQLite)
```

### Configuration (`config/auth.php`)

- **Guard** `web` avec `driver: session`
- **Provider** `users` avec `driver: eloquent`, `model: App\Models\User`

### Modèle `User`

- Étend `Illuminate\Foundation\Auth\User as Authenticatable`
- Cast `'password' => 'hashed'` → hashage bcrypt automatique
- `MustVerifyEmail` **commenté** → vérification email désactivée dans les faits (mais infrastructure présente)

### Routes (`routes/auth.php`)

Deux groupes middleware :

- `middleware('guest')` → `/login`, `/register`, `/forgot-password`, `/reset-password/{token}` — composants **Volt**
- `middleware('auth')` → `/verify-email`, `/confirm-password` — composants **Volt**

Deux exceptions (pas des composants Livewire) :
- `GET /verify-email/{id}/{hash}` → `VerifyEmailController` (invocable classique, appelé depuis un lien signé en email)
- `POST /logout` → `App\Livewire\Actions\Logout` (classe Action invocable)

### Flow de login (composant Volt `login.blade.php`)

```
1. $this->validate()          — attributs #[Validate('...')]
2. ensureIsNotRateLimited()   — RateLimiter clé (email + IP), max 5 tentatives
3. Auth::attempt(...)         — le provider charge le user, compare le hash
4. Session::regenerate()      — nouveau session ID (anti session-fixation)
5. redirectIntended(dashboard)
```

Sécurité intégrée : rate limiting, session regénérée, redirect-intended, URLs signées pour la vérification email.

### Flow de logout (`App\Livewire\Actions\Logout`)

```php
Auth::guard('web')->logout();
Session::invalidate();
Session::regenerateToken();
return redirect('/');
```

### Protection des routes

```php
Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified']);
```

Middleware `auth` (session valide) + `verified` (email vérifié — inactif ici car `MustVerifyEmail` commenté).

### À noter pour le refactor

L'auth est le **seul endroit** du projet qui utilise partiellement un pattern MVC classique (`VerifyEmailController`, classe Action `Logout`). Le reste (login, register, forgot-password) reproduit le pattern « composant fat » du domaine métier — la logique de rate limit, la validation, l'appel au provider et la redirection sont mélangés dans le composant Volt. Un refactor pourrait extraire tout ça dans un `AuthenticationService`.

---

## 8. Synthèse

> Renote est un **monolithe Laravel/Livewire à deux couches** (composants stateful + ORM), sans couche métier ni API. Les composants Livewire fusionnent les responsabilités présentation, contrôleur et service, et manipulent directement Eloquent. L'auth est stateful (session + cookies) et suit le starter kit standard de Laravel 12. L'architecture est **très efficace pour un MVP CRUD web-only**, mais atteint rapidement ses limites en matière de testabilité, découplage et ouverture à d'autres clients — d'où la pertinence du travail de transformation architecturale à venir.