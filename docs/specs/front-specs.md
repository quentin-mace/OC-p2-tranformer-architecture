# Spécification front-end — Renote

Consolide l'ensemble des décisions d'architecture front prises au fil du projet (analyse de l'écart, pattern de state management, justifications). Alimente les sections **Architecture cible**, **Analyse de l'écart** et **Justification de l'approche architecturale** du modèle de documentation. Pour le contrat consommé, voir [`api-specs.md`](./api-specs.md).

## 1. Objectif et périmètre

Remplacer intégralement les vues Blade + composants Livewire/Volt par une **application React autonome**, qui ne connaît le back que via l'API HTTP décrite dans `api-specs.md`. Contrairement au back (où les Models sont conservés), le front change entièrement de framework : aucun composant existant n'est adapté, tous sont réécrits.

Stack : **React** (JSX), **Zustand** pour la gestion d'état, un routeur client (React Router) pour la navigation, **axios** pour les appels HTTP.

## 2. Principe d'architecture : séparation UI / Store / Services

Trois couches, une seule responsabilité chacune :

- **Composants (UI)** : affichage uniquement, déclenchent des actions du store, ne contiennent ni logique métier ni appel réseau.
- **Store (Zustand)** : détient l'état (données + statut `idle/loading/error`) et orchestre la séquence ; c'est le seul niveau qui appelle les services.
- **Services (effets)** : seul point d'accès réseau de l'application, un module par ressource (`notesApi`, `tagsApi`, `authApi`...) ; aucun composant n'appelle `fetch`/`axios` directement.

Un changement de librairie de state management ou de mode d'appel API n'affecte donc qu'une seule couche, sans répercussion sur les composants — et la logique du store est testable indépendamment d'un appel réseau réel.

## 3. Flux de données (data flow)

```
Événement UI                    Store (Zustand)                  Effet (service/API)
─────────────                   ────────────────                 ────────────────────
composant déclenche    ──────▶  action du store
une action                      met status: 'loading'
(ex: onClick, onSubmit)              │
                                      ▼
                                 appelle le service   ──────▶    appel HTTP (axios)
                                 correspondant                   vers l'API REST Laravel
                                      │                                │
                                      ◀────────────────────────────────┘
                                 reçoit la réponse
                                 (JSON) ou l'erreur
                                      │
                                      ▼
                                 met à jour l'état
                                 (data + status: 'idle'/'error')
                                      │
                                      ▼
                                 composants abonnés
                                 (via selector) se
                                 re-rendent automatiquement
```

`UI ← Store` : la mise à jour de l'état déclenche automatiquement le re-rendu des composants abonnés via un *selector* (fonction qui lit une portion précise de l'état) — pas de rafraîchissement manuel. Ce pattern est identique pour toutes les ressources (Notes, Tags, Auth, Settings).

## 4. Organisation des dossiers

```
src/
├── app/
│   ├── App.jsx                    // composant racine, monte le routeur et les providers globaux
│   └── router.jsx                 // déclaration des routes client (/login, /dashboard, /settings/...)
│
├── features/
│   ├── notes/
│   │   ├── components/
│   │   │   ├── NoteForm.jsx
│   │   │   ├── NoteList.jsx
│   │   │   └── NoteItem.jsx
│   │   ├── store/
│   │   │   └── notesStore.js
│   │   └── api/
│   │       └── notesApi.js
│   │
│   ├── tags/
│   │   ├── components/
│   │   │   ├── TagForm.jsx
│   │   │   ├── TagList.jsx
│   │   │   └── TagBadge.jsx
│   │   ├── store/
│   │   │   └── tagsStore.js
│   │   └── api/
│   │       └── tagsApi.js
│   │
│   ├── auth/
│   │   ├── components/
│   │   │   ├── LoginForm.jsx
│   │   │   ├── RegisterForm.jsx
│   │   │   ├── ForgotPasswordForm.jsx
│   │   │   ├── ResetPasswordForm.jsx
│   │   │   └── ConfirmPasswordForm.jsx
│   │   ├── store/
│   │   │   └── authStore.js       // utilisateur courant, token, statut de connexion
│   │   └── api/
│   │       └── authApi.js
│   │
│   └── settings/
│       ├── components/
│       │   ├── ProfileForm.jsx
│       │   ├── PasswordForm.jsx
│       │   ├── AppearanceForm.jsx
│       │   └── DeleteAccountForm.jsx
│       ├── store/
│       │   └── settingsStore.js   // ou réutilisation d'authStore pour le profil
│       └── api/
│           └── settingsApi.js
│
├── shared/
│   ├── components/
│   │   ├── Layout.jsx             // équivalent des layouts Blade actuels
│   │   ├── Sidebar.jsx
│   │   ├── Header.jsx
│   │   └── AppLogo.jsx
│   └── lib/
│       └── httpClient.js          // instance axios : base URL, intercepteur d'authentification, gestion centralisée des erreurs 401/422
│
└── main.jsx                       // point d'entrée de l'application
```

Regroupement par domaine métier (`features/<domaine>/{components,store,api}`) plutôt que par nature technique : l'ensemble du code relatif à une fonctionnalité se trouve à un seul endroit, aussi bien côté front que côté back (cf. `back-specs.md` §3).

## 5. Sécurité côté front

- Le **token** d'authentification (émis par `/api/login` ou `/api/register`, cf. `api-specs.md`) est porté par `authStore` et injecté automatiquement dans l'en-tête `Authorization` par l'intercepteur d'`httpClient.js` — aucun appel `authApi`/`notesApi`/... n'a besoin de le gérer manuellement.
- L'intercepteur centralise également la gestion des réponses `401` (token expiré/invalide → déconnexion et redirection vers `/login`) et `422` (erreurs de validation → transmises au store appelant pour affichage dans le formulaire).
- Aucune validation métier n'est considérée fiable côté front seul : les règles de validation y sont dupliquées pour l'UX (retour immédiat), mais l'API revalide systématiquement (cf. `back-specs.md` §6).

## 6. Analyse de l'écart (front)

| | Actuel | Cible |
|---|---|---|
| UI | Vues Blade + composants Livewire/Volt | Composants React (`features/<domaine>/components`) |
| État | Propriétés publiques des composants Livewire | Stores Zustand (`features/<domaine>/store`) |
| Appel réseau | Invisible (protocole interne Livewire) | Explicite, isolé dans `features/<domaine>/api` |
| Routage | Géré par Laravel (`routes/web.php`) | Routeur client (`app/router.jsx`) |

**À ajouter** : l'intégralité de l'arborescence `src/` ci-dessus (aucun composant existant n'est réutilisable, cf. §1).
**À supprimer** : l'ensemble des vues Blade et composants Livewire/Volt actuels (Notes, TagForm, Auth, Settings, layout — cf. `architecture-analyse.md` §2 pour l'inventaire), à l'exception de `welcome.blade.php` (page vitrine statique, hors périmètre applicatif, peut rester en Blade classique).

## 7. Justification des choix

**Zustand plutôt que Redux ou Context API** — Redux impose une structure lourde (reducers, actions, dispatch) disproportionnée pour la complexité d'état du projet. Le Context API présente une limite de performance : toute modification d'une valeur du contexte re-rend tous les composants qui le consomment, y compris ceux qui ne dépendent que d'une partie non modifiée de l'état. Zustand permet d'écrire des actions sous forme de fonctions simples tout en ne déclenchant le re-rendu que des composants réellement concernés par la portion d'état modifiée.

**Séparation UI / Store / Services** et **organisation par domaine** — voir §2 et §4 ci-dessus pour la justification détaillée.

**Contrat d'API stable** — l'uniformisation du format de réponse (`status`, `message`, `data`) et des codes HTTP côté back garantit que le front peut consommer l'API sans connaître les détails de son implémentation ; c'est ce découplage qui permettra, à terme, à un client mobile de réutiliser la même API sans duplication de logique.