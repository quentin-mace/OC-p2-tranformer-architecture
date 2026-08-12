# Spécification de l'API REST — Renote

Alimente la section **Architecture cible > API REST** du modèle de documentation (contrat d'échange entre le front React et le back Laravel, réutilisable par un futur client mobile).

Périmètre : cette spec couvre exactement les fonctionnalités déjà présentes dans l'application (cf. `architecture-analyse.md`), ni plus ni moins — pas d'endpoints "au cas où" (pas d'édition de note/tag, pas de suppression de tag : ces actions n'existent pas dans l'UI actuelle).

---

## 1. Conventions générales

| | |
|---|---|
| Base URL | `/api` |
| Format | JSON (`Content-Type: application/json`) |
| Authentification | Bearer token (Laravel Sanctum, *personal access token* — pas de session/cookie, pour rester consommable par un client mobile) |
| En-tête auth | `Authorization: Bearer {token}` |

### Enveloppe de réponse standard

Toute réponse (succès **et** erreur) respecte le contrat imposé par les specs : `{status, message, data}`.

**Succès**
```json
{
  "status": "success",
  "message": "Note créée.",
  "data": { "id": 12, "text": "...", "tag_id": 3 }
}
```

**Erreur** — pour rester strictement à 3 clés, les erreurs de validation champ par champ sont nichées dans `data.errors` plutôt que d'ajouter une clé `errors` au niveau racine :
```json
{
  "status": "error",
  "message": "Les données fournies sont invalides.",
  "data": {
    "errors": {
      "email": ["Ce champ est obligatoire."]
    }
  }
}
```
Quand il n'y a pas de détail à donner (401, 404, 429...), `data` vaut `null`.

### Codes HTTP utilisés

| Code | Usage |
|---|---|
| 200 OK | Lecture, ou action réussie qui ne crée rien (login, logout, update, delete) |
| 201 Created | Création d'une ressource (register, note, tag) |
| 401 Unauthorized | Token absent/invalide/expiré, ou identifiants de connexion incorrects |
| 404 Not Found | Ressource inexistante — **ou n'appartenant pas à l'utilisateur courant** (cf. §4, on ne confirme jamais l'existence d'une ressource d'un autre user) |
| 422 Unprocessable Entity | Erreur de validation (champ manquant/invalide, mot de passe actuel incorrect, token de reset invalide/expiré, email déjà pris...) |
| 429 Too Many Requests | Rate limiting (tentatives de connexion, renvoi d'email de vérification) |

---

## 2. Authentification

### `POST /api/register`
Crée un compte et connecte immédiatement l'utilisateur (émission d'un token). Un email de vérification est envoyé (comportement actuel conservé : `event(new Registered($user))`).

Auth requise : non

**Requête**
```json
{
  "name": "Ada Lovelace",
  "email": "ada@example.com",
  "password": "correct-horse-battery-staple",
  "password_confirmation": "correct-horse-battery-staple"
}
```

**Réponse 201**
```json
{
  "status": "success",
  "message": "Compte créé avec succès.",
  "data": {
    "user": { "id": 1, "name": "Ada Lovelace", "email": "ada@example.com", "email_verified_at": null },
    "token": "1|abcdef123456..."
  }
}
```

**Erreurs** : `422` (nom requis, email requis/format/unique, mot de passe requis/confirmé/règles de complexité)

---

### `POST /api/login`
Auth requise : non

**Requête**
```json
{ "email": "ada@example.com", "password": "correct-horse-battery-staple" }
```

**Réponse 200**
```json
{
  "status": "success",
  "message": "Connexion réussie.",
  "data": {
    "user": { "id": 1, "name": "Ada Lovelace", "email": "ada@example.com", "email_verified_at": "2026-08-01T10:00:00Z" },
    "token": "2|ghijkl789..."
  }
}
```

**Erreurs** :
- `422` — champ manquant/mal formé
- `401` — identifiants incorrects
- `429` — trop de tentatives (comportement actuel : verrouillage après 5 essais, cf. `RateLimiter`)

---

### `POST /api/logout`
Révoque le token utilisé pour l'appel (`currentAccessToken()->delete()`).

Auth requise : oui

**Réponse 200**
```json
{ "status": "success", "message": "Déconnexion réussie.", "data": null }
```

**Erreurs** : `401` — token absent/invalide

---

### `POST /api/forgot-password`
Envoie un lien de réinitialisation. Réponse volontairement identique que le compte existe ou non (anti-énumération — comportement actuel conservé).

Auth requise : non

**Requête**
```json
{ "email": "ada@example.com" }
```

**Réponse 200**
```json
{ "status": "success", "message": "Un lien de réinitialisation sera envoyé si le compte existe.", "data": null }
```

**Erreurs** : `422` — email manquant/mal formé

---

### `POST /api/reset-password`
Auth requise : non

**Requête**
```json
{
  "token": "9f8e7d...",
  "email": "ada@example.com",
  "password": "new-correct-horse-battery",
  "password_confirmation": "new-correct-horse-battery"
}
```

**Réponse 200**
```json
{ "status": "success", "message": "Mot de passe réinitialisé avec succès.", "data": null }
```

**Erreurs** : `422` — validation, ou token invalide/expiré

---

### `POST /api/email/verification-notification`
Renvoie l'email de vérification. Idempotent si déjà vérifié.

Auth requise : oui

**Réponse 200**
```json
{ "status": "success", "message": "Un nouveau lien de vérification a été envoyé.", "data": null }
```

**Erreurs** : `401`, `429` (throttle, comportement actuel : 6 tentatives/minute)

---

### `GET /api/email/verify/{id}/{hash}`
Lien signé cliqué depuis l'email (query params `expires` et `signature` ajoutés automatiquement par Laravel) — pas de header `Authorization`, la signature fait office de preuve.

Auth requise : non (signature à la place)

**Réponse 200**
```json
{ "status": "success", "message": "Adresse email vérifiée.", "data": { "verified": true } }
```

**Erreurs** : `401` — lien invalide, expiré, ou hash ne correspondant pas à l'email de l'utilisateur

---

## 3. Profil utilisateur (Settings)

### `GET /api/user`
Auth requise : oui

**Réponse 200**
```json
{
  "status": "success",
  "message": "Utilisateur courant.",
  "data": { "id": 1, "name": "Ada Lovelace", "email": "ada@example.com", "email_verified_at": "2026-08-01T10:00:00Z" }
}
```

**Erreurs** : `401`

---

### `PUT /api/user/profile`
Met à jour nom/email. Si l'email change, `email_verified_at` repasse à `null` (comportement actuel conservé — il faut re-vérifier la nouvelle adresse).

Auth requise : oui

**Requête**
```json
{ "name": "Ada King", "email": "ada.king@example.com" }
```

**Réponse 200**
```json
{
  "status": "success",
  "message": "Profil mis à jour.",
  "data": { "id": 1, "name": "Ada King", "email": "ada.king@example.com", "email_verified_at": null }
}
```

**Erreurs** : `401`, `422` — nom/email requis, email déjà pris par un autre compte

---

### `PUT /api/user/password`
Auth requise : oui

**Requête**
```json
{ "current_password": "correct-horse-battery-staple", "password": "new-one", "password_confirmation": "new-one" }
```

**Réponse 200**
```json
{ "status": "success", "message": "Mot de passe mis à jour.", "data": null }
```

**Erreurs** : `401`, `422` — mot de passe actuel incorrect, nouveau mot de passe invalide/non confirmé

---

### `DELETE /api/user`
Supprime le compte (et en cascade ses notes, via la contrainte FK `onDelete('cascade')` déjà en place). Révoque tous les tokens.

Auth requise : oui

**Requête**
```json
{ "password": "correct-horse-battery-staple" }
```

**Réponse 200**
```json
{ "status": "success", "message": "Compte supprimé.", "data": null }
```

**Erreurs** : `401`, `422` — mot de passe incorrect

---

## 4. Notes

Toutes les routes sont scopées à l'utilisateur courant (`where('user_id', ...)`) — une note d'un autre utilisateur répond `404`, jamais `403`, pour ne pas confirmer son existence.

### `GET /api/notes`
Auth requise : oui

**Réponse 200**
```json
{
  "status": "success",
  "message": "Liste des notes.",
  "data": [
    { "id": 12, "text": "Acheter du café", "tag": { "id": 3, "name": "courses" }, "created_at": "2026-08-01T09:00:00Z" }
  ]
}
```

**Erreurs** : `401`

---

### `POST /api/notes`
Auth requise : oui

**Requête**
```json
{ "text": "Acheter du café", "tag_id": 3 }
```

**Réponse 201**
```json
{
  "status": "success",
  "message": "Note créée.",
  "data": { "id": 12, "text": "Acheter du café", "tag_id": 3, "created_at": "2026-08-01T09:00:00Z" }
}
```

**Erreurs** : `401`, `422` — `text` requis, `tag_id` requis / inexistant / n'appartenant pas à l'utilisateur

---

### `DELETE /api/notes/{note}`
Auth requise : oui

**Réponse 200**
```json
{ "status": "success", "message": "Note supprimée.", "data": null }
```

**Erreurs** : `401`, `404` — note inexistante ou n'appartenant pas à l'utilisateur

---

## 5. Tags

Les tags sont **personnels** : chaque utilisateur possède ses propres tags. Le schéma initial (`create_tags_table`) ne portait pas de colonne `user_id` ; une migration ajoute `user_id` (FK `onDelete('cascade')`) + un index unique `(user_id, name)`. À la création d'une note, on vérifie que `tag_id` appartient bien à l'utilisateur (sinon `422`). Endpoints ci-dessous scopés par utilisateur, comme pour les notes.

### `GET /api/tags`
Auth requise : oui

**Réponse 200**
```json
{
  "status": "success",
  "message": "Liste des tags.",
  "data": [ { "id": 3, "name": "courses" }, { "id": 4, "name": "travail" } ]
}
```

**Erreurs** : `401`

---

### `POST /api/tags`
Auth requise : oui

**Requête**
```json
{ "name": "courses" }
```

**Réponse 201**
```json
{ "status": "success", "message": "Tag créé.", "data": { "id": 3, "name": "courses" } }
```

**Erreurs** : `401`, `422` — `name` requis, ≤ 50 caractères, déjà utilisé par ce même utilisateur (unicité `(user_id, name)`)

---

## 6. Récapitulatif

| Méthode | URL | Auth | Description |
|---|---|---|---|
| POST | `/api/register` | non | Créer un compte |
| POST | `/api/login` | non | Se connecter |
| POST | `/api/logout` | oui | Se déconnecter |
| POST | `/api/forgot-password` | non | Demander un lien de réinitialisation |
| POST | `/api/reset-password` | non | Réinitialiser le mot de passe |
| POST | `/api/email/verification-notification` | oui | Renvoyer l'email de vérification |
| GET | `/api/email/verify/{id}/{hash}` | signature | Vérifier l'adresse email |
| GET | `/api/user` | oui | Profil courant |
| PUT | `/api/user/profile` | oui | Modifier nom/email |
| PUT | `/api/user/password` | oui | Modifier le mot de passe |
| DELETE | `/api/user` | oui | Supprimer le compte |
| GET | `/api/notes` | oui | Lister mes notes |
| POST | `/api/notes` | oui | Créer une note |
| DELETE | `/api/notes/{note}` | oui | Supprimer une note |
| GET | `/api/tags` | oui | Lister les tags |
| POST | `/api/tags` | oui | Créer un tag |
