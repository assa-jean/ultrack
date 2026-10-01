# ULTRACK — Spécification technique
## Migration de l'authentification vers Keycloak & du stockage fichiers vers MinIO

| | |
|---|---|
| **Version** | 1.3 |
| **Date** | 2026-09-17 |
| **Périmètre** | `ULTRACK-Frontend` (front-office terrain) + `ULTRACK-Backend` (back-office admin) |
| **Statut** | **Prête pour l'équipe dev.** Les décisions structurantes sont prises ; les points restant à trancher sont en §10, avec le lot qu'ils bloquent |
| **Cible** | Cluster Kubernetes interne, plusieurs réplicas, base MySQL dédiée |

---

## 1. Objet

Ce document spécifie deux chantiers indépendants mais séquencés :

- **Volet A — Authentification** : remplacer l'authentification maison (table `users` + `password_verify`) par une délégation à **Keycloak** via OpenID Connect, pour les deux applications.
- **Volet B — Stockage** : déplacer les photos d'agents et les images de CNI du disque local vers **MinIO** (S3-compatible).

Les deux applications sont par ailleurs **redéployées sur le cluster Kubernetes interne** avec plusieurs réplicas et une base dédiée (§5). Ce contexte rend le volet B non négociable et impose l'externalisation des sessions PHP.

Les deux volets modifient les mêmes fichiers (`save.php`, `droits.php`, `index.php`). Le séquencement est donc contraint — voir §8. La nouvelle plateforme se construit à vide ; **la reprise des données de production est la dernière étape**, juste avant la bascule.

---

## 2. État des lieux

### 2.1 Architecture actuelle

Deux applications PHP procédurales indépendantes (pas de framework, pas de routeur, pas de code partagé), attaquant la **même base MySQL** `c2781394c_ultrackv2` :

| Application | Rôle | Pages |
|---|---|---|
| `ULTRACK-Frontend` | Enrôlement terrain | `index.php`, `home.php`, `save.php`, `statistiques.php`, `logout.php` |
| `ULTRACK-Backend` | Back-office admin | `index.php`, `dashboard.php`, `droits.php`, `save.php`, `logout_bo.php` |

Fichiers **strictement identiques** entre les deux dépôts : `config.php`, `save.php`, `.htaccess`, `composer.json`.

**Tables concernées**

- `users` — `id`, `username`, `password` (hash bcrypt), `enseigne`, `telephone`, `cni_recto`, `cni_verso`, `created_at`
- `agents` — `id`, `user_id` (FK → `users.id`), `nom`, `login`, `famoco_id`, `cni_number`, `region`, `region_admin`, `type_enseigne`, `nom_enseigne`, `photo_path`, `cni_front`, `cni_back`, `acceptation_reglement`, `Etat_traitement`

### 2.2 Authentification actuelle

| Emplacement | Comportement |
|---|---|
| `Frontend/index.php:7-21` | `SELECT * FROM users WHERE username = ?` puis `password_verify()` → `$_SESSION['user_id']`, `$_SESSION['username']` → redirection `home.php` |
| `Backend/index.php:10-29` | Idem, avec `try/catch` → redirection `dashboard.php` |
| `Frontend/home.php:3`, `save.php:5`, `statistiques.php:6` | Garde dupliquée : `if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }` |
| `Backend/dashboard.php`, `droits.php` | Même garde dupliquée |
| `Frontend/logout.php`, `Backend/logout_bo.php` | `session_destroy()` + redirection |
| `Backend/droits.php:64` | `INSERT INTO users (username, enseigne, telephone, password, cni_recto, cni_verso)` — **création des comptes front-office par un admin** |

**Constats :**

1. La garde d'accès est **dupliquée dans 5+ fichiers**, sans fonction commune.
2. **Aucune notion de rôle** : tout utilisateur authentifié sur le Backend accède à `dashboard.php` *et* `droits.php` (création de comptes). Un recruteur terrain dont les identifiants fonctionnent sur le Backend aurait les pleins pouvoirs.
3. Les identifiants MySQL sont **en clair et committés dans git**, dupliqués entre `config.php:3-6` et `index.php:5`.
4. `session_regenerate_id()` n'est jamais appelé après login → exposition à la fixation de session.

### 2.3 Stockage fichiers actuel

**Écriture** — deux chemins incohérents :

| Emplacement | Comportement |
|---|---|
| `save.php:12-43` (2 apps) | `compressAndSave()` : GD, redimensionnement max 800 px, JPEG qualité 65, entrelacé |
| `save.php:78-84` | Nommage `prof_{login}_{timestamp}.jpg` / `recto_…` / `verso_…` → `uploads/` et `cni_pictures/` |
| `Backend/droits.php:34-59` | `move_uploaded_file()` **brut, sans compression ni validation MIME** → `cni_superviseurs/` |

En base, seul le **nom de fichier** est stocké (`agents.photo_path`, `agents.cni_front`, `agents.cni_back`, `users.cni_recto`, `users.cni_verso`).

**Lecture** — trois modes :

| Emplacement | Comportement |
|---|---|
| `Backend/dashboard.php:581-583` | `$getValidFileUrl()` : résolution dynamique du dossier (`uploads/` **ou** `uploads_pocv2/`) par `file_exists()`, puis `<img src="uploads/xxx.jpg">` |
| `Backend/dashboard.php:51-53` | Lecture des octets + encodage base64 pour embarquement dans le PDF dompdf |
| `Backend/droits.php:269-274` | `<a href="cni_superviseurs/xxx.jpg" target="_blank">` — lien direct |

**Suppression** — `Backend/dashboard.php:204-222` : `DELETE FROM agents` + `unlink()` sur les dossiers candidats.

**Inventaire des fichiers**

| Dossier | Fichiers | Taille | Référencé dans le code |
|---|---:|---:|:--:|
| `Frontend/uploads/` | 123 | 5,1 Mo | ✅ |
| `Frontend/uploads_pocv2/` | 441 | 20 Mo | ✅ |
| `Frontend/cni_pictures/` | 243 | 11 Mo | ✅ |
| `Frontend/cni_pictures_pocv2/` | 870 | 48 Mo | ✅ |
| `Backend/cni_superviseurs/` | 4 | — | ✅ |
| `Frontend/photos_final/` | 411 | — | ❌ |
| `Frontend/photos_final_pocv2/` | 183 | — | ❌ |
| `Backend/uploads_test/` | 19 | — | ❌ |
| **Total à migrer** | **~1 681** | **~85 Mo** | |
| **Total à archiver (mort)** | **613** | | |

### 2.4 ⚠️ Vulnérabilité à corriger dans ce chantier

> **Les images de CNI sont accessibles publiquement sans authentification.**
>
> Les dossiers `uploads/`, `cni_pictures/` et `cni_superviseurs/` sont servis directement par Apache. Le `.htaccess` ne pose qu'`Options -Indexes`, ce qui empêche le listage mais **pas l'accès direct à un fichier**.
>
> Or le nom de fichier est **devinable** : `recto_{login}_{timestamp}.jpg`, où `{login}` est le numéro de téléphone Kaabu de l'agent (visible en clair dans `home.php`) et `{timestamp}` un `time()` Unix. Un tiers connaissant un numéro d'agent peut itérer sur une plage de timestamps et récupérer des cartes nationales d'identité.
>
> **Impact** : fuite de données personnelles sensibles (pièces d'identité). La migration MinIO doit clore cette faille — c'est le principal bénéfice sécurité du volet B, au-delà de la centralisation du stockage.

---

## 3. Volet A — Authentification Keycloak

### 3.1 Modèle cible

Le realm **`digital-app` existe déjà** et est mutualisé entre plusieurs applications Orange (`efms-frontend`, `veille-concurrentielle`, `gimac-backoffice`, `shlink-web-client`). ULTRACK s'y ajoute sous forme de **nouveaux clients** — il n'y a pas de realm à créer. **État au 16/09** : `ultrack-backoffice` et `ultrack-frontoffice` sont créés ; les rôles et le compte de service restent à faire.

| Client | Type | Population | Usage |
|---|---|---|---|
| `ultrack-backoffice` | Confidentiel, Standard Flow | Collaborateurs Orange (CUID), **fédérés depuis l'AD** | Connexion à `ULTRACK-Backend` |
| `ultrack-frontoffice` | Confidentiel, Standard Flow | Recruteurs terrain, **utilisateurs natifs Keycloak** | Connexion à `ULTRACK-Frontend` |
| *compte de service* | `client_credentials` | — | Création / désactivation des recruteurs par `droits.php`. **Soit** un 3ᵉ client `ultrack-provisioning`, **soit** *Service accounts roles* activé sur `ultrack-backoffice` — à trancher (Q15) |

**Rôles — rôles de client, pas rôles de realm**

Dans ce realm, `realm_access.roles` ne porte que les rôles techniques Keycloak (`offline_access`, `uma_authorization`, `default-roles-digital-app`). Les rôles applicatifs de chaque application sont déclarés **au niveau de son client** et apparaissent dans `resource_access.{client_id}.roles`.

| Client | Rôle | Accorde |
|---|---|---|
| `ultrack-backoffice` | `ADMIN` | Accès `dashboard.php` **et** `droits.php` (création de comptes) |
| `ultrack-backoffice` | `SUPERVISEUR` | Accès `dashboard.php` en lecture seule (pas de suppression, pas de `droits.php`) |
| `ultrack-frontoffice` | `RECRUTEUR` | Accès `ULTRACK-Frontend` (`home.php`, `save.php`, `statistiques.php`) |

> Nommage en `MAJUSCULES_SNAKE`, conforme à la convention observée dans le realm (`TECHNICAL_AGENT`, `BACKOFFICE`, `LIST_TRANSACTION`).

> Ce découpage introduit le contrôle d'accès **absent aujourd'hui** (§2.2, constat 2). Le rôle `SUPERVISEUR` est optionnel en v1 mais recommandé : la suppression d'agent (`dashboard.php:204`) est irréversible et purge aussi les fichiers.

**Où lire les rôles : dans l'access token, pas dans l'ID token.**

Par défaut Keycloak place `resource_access` dans l'**access token** uniquement — l'ID token n'en contient pas. Plutôt que de dépendre d'un mapper à configurer dans le realm, l'application lit les rôles dans l'access token, reçu directement du *token endpoint* en TLS serveur-à-serveur (jamais passé par le navigateur), et dont elle vérifie la signature avec le même JWKS. **Aucune configuration Keycloak nécessaire.**

```php
$roles = $accessClaims->resource_access->{$clientId}->roles ?? [];
```

> ⚠️ **Ne pas lire `realm_access.roles`** — le contrôle d'accès ne trouverait jamais aucun rôle applicatif.

| Token | On y lit | On y vérifie |
|---|---|---|
| **ID token** | `preferred_username` (CUID), `name`, `email`, `sub` | signature, `iss`, `exp`, `nonce`, **`aud` contient client_id**, **`azp` == client_id** |
| **Access token** | `resource_access.{client_id}.roles` | signature, `iss`, `exp` |

> **Pourquoi ne pas vérifier `aud` sur l'access token.** Dans ce realm, l'`aud` de l'access token est calculé à partir des *scope mappings* de rôles et **ne contient pas nécessairement le client qui l'a demandé** (observé : `azp: shlink-web-client` absent de son propre `aud`). La preuve de destination se fait sur l'ID token, dont l'`aud` est toujours le `client_id`.

**Compte de service pour le provisioning** (§3.8) : rôles `realm-management` → `manage-users`, `view-users`, `query-users`.

> ⚠️ **`manage-users` est realm-wide.** Dans le realm mutualisé `digital-app`, il donne pouvoir sur les utilisateurs de toutes les applications, AD compris. Si l'IAM le refuse, la voie de contournement est *Admin Fine-Grained Permissions* : restreindre le compte de service à un groupe `ultrack-recruteurs`. À trancher **avant** que `droits.php` soit codé (Q15).

### 3.2 Endpoints Keycloak

Base : `{KEYCLOAK_BASE_URL}/auth/realms/digital-app`

> **Attention au segment `/auth/`** dans le chemin. Cette instance l'utilise (`https://keycloak.k8s.adcm.orangecm/auth/realms/digital-app`), contrairement aux Keycloak récents qui l'ont supprimé par défaut. Ne pas l'omettre.

| Usage | Endpoint |
|---|---|
| Découverte | `/.well-known/openid-configuration` |
| Autorisation | `/protocol/openid-connect/auth` |
| Token | `/protocol/openid-connect/token` |
| JWKS (clés de signature) | `/protocol/openid-connect/certs` |
| Déconnexion | `/protocol/openid-connect/logout` |
| Admin API | `{KEYCLOAK_BASE_URL}/auth/admin/realms/digital-app/users` |

**Instance cible** : `https://keycloak.k8s.adcm.orangecm` (cluster interne). Une instance de préproduction existe sur `keycloak-preprod-dev.odr.orange.cm`. L'URL est portée par une variable d'environnement, jamais codée en dur.

> **Ne pas coder ces chemins en dur** : les lire depuis le document de découverte, mis en cache côté application (TTL 24 h).

### 3.3 Variables d'environnement

```ini
# --- Commun ---
KEYCLOAK_BASE_URL=https://keycloak.k8s.adcm.orangecm/auth
KEYCLOAK_REALM=digital-app

# --- ULTRACK-Frontend ---
OIDC_CLIENT_ID=ultrack-frontoffice
OIDC_CLIENT_SECRET=<secret>
OIDC_REDIRECT_URI=https://<host-frontoffice>/callback.php

# --- ULTRACK-Backend ---
OIDC_CLIENT_ID=ultrack-backoffice
OIDC_CLIENT_SECRET=<secret>
OIDC_REDIRECT_URI=https://<host-backoffice>/callback.php

# --- ULTRACK-Backend uniquement : provisioning ---
KC_ADMIN_CLIENT_ID=ultrack-provisioning       # ou ultrack-backoffice si le service account est activé dessus (Q15)
KC_ADMIN_CLIENT_SECRET=<secret>
```

> **Aucun de ces secrets ne doit être committé.** Ils sont chargés depuis l'environnement (ou un `.env` hors dépôt, ajouté au `.gitignore`). Cette règle s'applique rétroactivement aux identifiants MySQL actuellement en clair dans `config.php` et `index.php` — voir §8, lot 0.

### 3.4 Flux de connexion

```
1. Utilisateur non authentifié atteint une page protégée
      │
      └─> index.php génère state + nonce + PKCE (code_verifier/code_challenge S256),
          les stocke en session, puis 302 vers l'endpoint d'autorisation
                scope=openid profile email
                response_type=code
                code_challenge_method=S256
      │
2. Keycloak authentifie
      ├─ Backoffice : fédération AD (CUID / mot de passe du domaine)
      └─ Frontoffice : utilisateur natif Keycloak
      │
3. Redirection vers callback.php?code=…&state=…
      │
      ├─ vérifier que `state` correspond à celui en session (anti-CSRF)
      ├─ échanger le code contre les tokens (+ code_verifier + client_secret)
      ├─ valider l'ID token : signature via JWKS, `iss`, `exp`, `nonce`,
      │     `aud` contient notre client_id, `azp` == notre client_id
      ├─ valider l'access token : signature via JWKS (même clé), `iss`, `exp`
      └─ lire resource_access.{client_id}.roles dans l'ACCESS token → rôle attendu absent : 403
      │
4. Provisioning JIT : UPSERT de la ligne locale `users` sur `user_ref` (= claim `preferred_username`)
      │
5. session_regenerate_id(true)  ← obligatoire (anti-fixation)
   Remplissage de $_SESSION (voir 3.5)
      │
6. Redirection vers home.php (Frontend) / dashboard.php (Backend)
```

**Squelette de `callback.php`** — indicatif, avec `jumbojett/openid-connect-php` ; vérifier l'API de la version épinglée :

```php
<?php
require_once 'config.php';   // env, PDO, handler de session, session_start()

$clientId = getenv('OIDC_CLIENT_ID');
$oidc = new Jumbojett\OpenIDConnectClient(
    getenv('KEYCLOAK_BASE_URL') . '/realms/' . getenv('KEYCLOAK_REALM'),
    $clientId,
    getenv('OIDC_CLIENT_SECRET')
);
$oidc->setRedirectURL(getenv('OIDC_REDIRECT_URI'));
$oidc->addScope(['openid', 'profile', 'email']);
$oidc->setCodeChallengeMethod('S256');                       // PKCE (A1)

// Sans ?code : génère state/nonce/PKCE, les met en session, redirige vers Keycloak.
// Avec ?code  : vérifie state, échange le code, valide l'ID token (signature, iss, aud, nonce, exp).
$oidc->authenticate();

$id = $oidc->getVerifiedClaims();                            // claims de l'ID token
if (($id->azp ?? null) !== $clientId) {                      // la lib ne vérifie PAS azp (A3b)
    http_response_code(403); exit('azp');
}

$accessToken = $oidc->getAccessToken();
if (!$oidc->verifyJWTSignature($accessToken)) {              // même JWKS (A3)
    http_response_code(403); exit('sig');
}
$at = $oidc->getAccessTokenPayload();                        // décodé — fiable après vérification
if ($at->iss !== $oidc->getProviderURL() || $at->exp < time()) {
    http_response_code(403); exit('at');
}

$roles = $at->resource_access->{$clientId}->roles ?? [];    // ACCESS token, jamais realm_access
if (!array_intersect($roles, ROLES_ATTENDUS)) {              // ex. ['ADMIN','SUPERVISEUR']
    http_response_code(403); exit('role');
}

$userId = jit_upsert_user($pdo, $id->preferred_username, $id->sub, $id->email ?? null); // §3.4 étape 4

session_regenerate_id(true);                                 // A4
$_SESSION = [ /* §3.5 */ ];
header('Location: ' . PAGE_ACCUEIL);
exit;
```

`authenticate()` gère les deux phases : `index.php` peut se contenter de rediriger vers `callback.php`, qui redirige à son tour vers Keycloak s'il n'a pas de `code`.

### 3.5 Contenu de session

```php
$_SESSION = [
    'user_ref'     => $claims->preferred_username,   // CUID côté back-office (ex. "fmtc8387")
    'keycloak_sub' => $claims->sub,                  // informatif : diagnostic + appels Admin API
    'user_id'      => $localId,                      // users.id — conservé pour la FK agents.user_id
    'nom_complet'  => $claims->name,
    'email'        => $claims->email ?? null,
    'roles'        => $accessClaims->resource_access->{$clientId}->roles ?? [],  // ACCESS token
    'id_token'     => $idToken,                      // requis pour le logout (id_token_hint)
    'access_token' => $accessToken,
    'refresh_token'=> $refreshToken,
    'expires_at'   => time() + $expiresIn,
];
```

> `$_SESSION['user_id']` **conserve sa sémantique actuelle** (`users.id`, entier). C'est ce qui permet de ne pas toucher à la clé étrangère `agents.user_id` ni aux requêtes existantes de `statistiques.php` et `save.php`.

**Clé de rapprochement : `user_ref`, alimentée par `preferred_username`.**

| Population | Contenu de `user_ref` | Exemple |
|---|---|---|
| Back-office | **CUID** — confirmé : `preferred_username` porte bien le CUID | `fmtc8387` |
| Front-office | Username natif Keycloak du recruteur, préfixé | `rec-<ancien username>` (Q11) |

> **Pourquoi `preferred_username` et non `sub`.** Le `sub` n'est stable que tant que le realm l'est : une reconstruction de realm ou un réimport d'utilisateurs le change, ce qui produirait des lignes locales en double au login suivant et détacherait l'historique d'enrôlement (`agents.user_id`). Le CUID est stable, lisible en base et joignable avec les autres systèmes Orange. Le `sub` est conservé **à titre informatif** — diagnostic et appels Admin API sans recherche par username.

> ⚠️ **Contrainte à faire respecter au provisioning** : les usernames des recruteurs front-office ne doivent jamais pouvoir entrer en collision avec un CUID. Les préfixer (par exemple `rec-<numéro>`) est le moyen le plus simple de le garantir.

### 3.6 Socle commun `auth.php`

À créer dans **chaque** dépôt (ou, mieux, dans la librairie partagée — §7) :

```php
require_auth(array $rolesRequis = []): void
```

Comportement :

1. Session absente ou `user_ref` manquant → redirection vers le flux de connexion.
2. `expires_at` dépassé → rafraîchissement silencieux via `refresh_token`. Échec → reconnexion complète.
3. `$rolesRequis` non vide et aucune intersection avec `$_SESSION['roles']` → **HTTP 403**, pas de redirection (une redirection masquerait une erreur d'habilitation en boucle de login).

**Remplace les gardes dupliquées** :

| Fichier | Appel |
|---|---|
| `Frontend/home.php:3` | `require_auth(['RECRUTEUR']);` |
| `Frontend/save.php:5` | `require_auth(['RECRUTEUR']);` |
| `Frontend/statistiques.php:6` | `require_auth(['RECRUTEUR']);` |
| `Backend/dashboard.php` | `require_auth(['ADMIN', 'SUPERVISEUR']);` |
| `Backend/droits.php` | `require_auth(['ADMIN']);` |
| `Backend/save.php` | `require_auth(['ADMIN']);` |

> La suppression d'agent (`dashboard.php:204-222`) doit en plus vérifier explicitement `ADMIN` : le rôle `SUPERVISEUR` donne accès à la page mais pas à cette action.

### 3.7 Déconnexion

`session_destroy()` **ne suffit pas** : la session SSO reste ouverte côté Keycloak, et un simple retour arrière reconnecte l'utilisateur silencieusement.

```
logout.php / logout_bo.php
  ├─ mémoriser $_SESSION['id_token']
  ├─ $_SESSION = []; session_destroy();
  └─ 302 vers l'endpoint de déconnexion Keycloak
        ?id_token_hint=<id_token>
        &post_logout_redirect_uri=<url-accueil>   ← doit être déclarée dans le client
```

### 3.8 Provisioning des recruteurs (`Backend/droits.php`)

**Aujourd'hui** (`droits.php:355`, `:64`) : l'administrateur **tape lui-même le mot de passe** du partenaire dans le formulaire, l'application l'enregistre hashé, puis l'admin envoie login et mot de passe **par e-mail, à la main, en clair**. Conséquences : l'admin connaît tous les mots de passe ; ils circulent sans expiration dans des boîtes mail ; un mot de passe oublié exige une intervention en base. Aucun champ e-mail n'existe, ni dans le formulaire ni dans `users`.

**Cible : plus aucun mot de passe ne transite.** L'admin crée le compte sans mot de passe ; Keycloak envoie au partenaire un lien à usage unique ; le partenaire définit lui-même son mot de passe.

```
1. Token de service
     POST {token_endpoint}   grant_type=client_credentials
     (mis en cache jusqu'à expiration — pas un par requête)

2. Créer l'utilisateur — SANS credentials
     POST /auth/admin/realms/digital-app/users
     {
       "username": "rec-<…>",                    ← convention anti-collision CUID (Q11)
       "email":    "<saisi>",
       "enabled":  true,
       "attributes": { "enseigne": ["<saisi>"], "telephone": ["<saisi>"] }
     }
     → 201 Created, en-tête Location: …/users/{id}  ← extraire l'id

3. Rôle DE CLIENT (pas de realm)
     GET  /auth/admin/realms/digital-app/clients/{clientUuid}/roles/RECRUTEUR
     POST /auth/admin/realms/digital-app/users/{id}/role-mappings/clients/{clientUuid}

4. Déclencher l'e-mail de définition du mot de passe
     PUT /auth/admin/realms/digital-app/users/{id}/execute-actions-email
         ?client_id=ultrack-frontoffice
         &redirect_uri=https://<host-frontoffice>/
         &lifespan=172800                          ← 48 h ; à ajuster
     Body : ["UPDATE_PASSWORD"]
     → Keycloak envoie « Définissez votre mot de passe » avec un lien à usage unique

5. Uploader les CNI vers MinIO (volet B)

6. INSERT local
     INSERT INTO users (user_ref, keycloak_sub, email, enseigne, telephone, cni_recto, cni_verso)
     (plus de colonne `password`)
```

| | Aujourd'hui | Cible |
|---|---|---|
| Qui choisit le mot de passe | L'admin | Le partenaire, seul |
| Qui le connaît | L'admin, le partenaire, la boîte mail | Le partenaire uniquement |
| Ce qui circule par e-mail | Login + mot de passe en clair, sans expiration | Un lien à usage unique, expirant |
| Mot de passe oublié | L'admin intervient en base | « Mot de passe oublié » sur la page Keycloak, en autonomie |

**Prérequis**

- **Champ e-mail obligatoire** dans le formulaire de `droits.php`, et colonne `email VARCHAR(255) NULL` dans `users` (migration `…0002`). Keycloak porte l'e-mail et envoie ; la copie locale sert à l'affichage de la liste.
- **SMTP du realm `digital-app`** — confirmé configuré (Q6). Vérifier en recette que l'e-mail part bien depuis l'environnement k8s (sortie réseau du cluster vers le relais SMTP).
- Le gabarit de l'e-mail est celui du realm, pas d'ULTRACK : il portera le nom d'affichage de `digital-app`. Acceptable ; un thème par client est possible mais relève de l'IAM.

**Gestion de l'échec — compensation obligatoire.** Si l'étape 6 échoue après création réussie côté Keycloak, **supprimer l'utilisateur Keycloak** (`DELETE …/users/{id}`) pour éviter un compte fantôme, connectable mais sans profil local. Si c'est l'étape 4 (e-mail) qui échoue, le compte existe et est valide : proposer à l'admin un bouton « Renvoyer l'e-mail » plutôt que de tout annuler — c'est le même appel `execute-actions-email`, à exposer aussi dans la liste des utilisateurs pour les liens expirés.

**Désactivation d'un recruteur** : `PUT …/users/{id}` avec `{"enabled": false}` **+** `DELETE FROM sessions WHERE user_ref = ?` (§5.2). Ne jamais supprimer l'utilisateur Keycloak si des agents lui sont rattachés (`agents.user_id`) — la désactivation préserve la traçabilité de qui a enrôlé qui.

### 3.9 Schéma de la table `users`

Le schéma est porté par les migrations Phinx (§5.7) ; la base dédiée est créée à neuf au lot 0. Colonnes ajoutées par `…0002` :

```sql
user_ref      VARCHAR(64)  NULL UNIQUE,   -- CUID (back-office) ou username Keycloak (recruteurs)
keycloak_sub  VARCHAR(36)  NULL,          -- informatif : diagnostic, appels Admin API
email         VARCHAR(255) NULL           -- porté par Keycloak ; copie locale pour l'affichage
-- `password` est CONSERVÉE jusqu'au lot 4 (migration …0005) : l'authentification locale
-- reste active entre le premier déploiement et la bascule Keycloak
```

`user_ref` est `NULL` à la création de colonne — les lignes importées au lot R arrivent sans valeur et sont renseignées par le backfill (§5.6). Le code, lui, l'exige : le JIT ne crée jamais de ligne sans `user_ref`.

> **La clé primaire `users.id` est conservée.** `agents.user_id` n'est pas touchée : aucune migration de données sur la table `agents` (la plus volumineuse), et les requêtes existantes de `statistiques.php:15-22` et `dashboard.php:24` continuent de fonctionner sans modification.

### 3.10 Migration des comptes existants

| Population | Procédure |
|---|---|
| **Back-office (AD)** | Rien à créer : les comptes existent dans l'AD, fédérés par Keycloak. Il faut leur **affecter le rôle de client** `ADMIN` / `SUPERVISEUR` sur `ultrack-backoffice` (via un groupe AD mappé sur le rôle, de préférence). `user_ref` (le CUID) est renseigné au provisioning JIT à la première connexion — **sauf pour les comptes existants importés au lot R**, dont les lignes locales doivent être rapprochées **avant** par le backfill (§5.6, Q16). |
| **Front-office (recruteurs)** | Les hashes bcrypt de `users.password` **ne sont pas réimportables** simplement dans Keycloak (cela exigerait un *User Storage SPI* custom — hors périmètre). Procédure retenue : script de reprise qui crée chaque compte via l'Admin API **sans mot de passe** et déclenche `execute-actions-email` (§3.8, étapes 2 à 4) — chaque partenaire reçoit « Définissez votre mot de passe ». Plus de mot de passe temporaire à communiquer. **Condition : avoir collecté les e-mails des partenaires existants avant le lot R** — ils ne sont pas en base aujourd'hui, mais les admins les ont puisqu'ils leur écrivent (Q7). |

Le script de reprise doit être **idempotent** (rejouable sans créer de doublons : vérifier l'existence par `username` avant création) et disposer d'un mode `--dry-run`.

### 3.11 Exigences de sécurité (non négociables)

| # | Exigence |
|---|---|
| A1 | **PKCE S256** activé, même en client confidentiel |
| A2 | `state` et `nonce` générés par `random_bytes()`, vérifiés au retour, à usage unique |
| A3 | Signature de l'ID token **et** de l'access token validée contre le **JWKS** (cache 24 h, rechargement sur `kid` inconnu). Ne jamais accepter `alg: none` ni un token non vérifié |
| A3b | Sur l'**ID token** : `aud` est un tableau dans ce realm mutualisé → vérifier l'**appartenance** du `client_id`, et contrôler **aussi `azp`**. Ne pas faire ce contrôle sur l'access token, dont l'`aud` est calculé par *scope mapping* et peu fiable (§3.1) |
| A4 | `session_regenerate_id(true)` immédiatement après authentification réussie |
| A5 | Cookie de session : `HttpOnly`, `Secure`, `SameSite=Lax` |
| A6 | Tokens **jamais** exposés au JavaScript ni placés dans une URL |
| A7 | Vérification TLS activée sur tous les appels sortants (`CURLOPT_SSL_VERIFYPEER = true`) |
| A8 | `redirect_uri` et `post_logout_redirect_uri` déclarées en liste blanche stricte côté client Keycloak (pas de wildcard) |

### 3.12 Dépendance

```json
"jumbojett/openid-connect-php": "^1.0"
```

Alternative si l'équipe préfère une brique plus large : `league/oauth2-client` + provider Keycloak.

> **Ne pas implémenter le flux à la main.** La validation de signature JWT, le contrôle du `nonce` et la gestion du `state` sont les points où les implémentations maison échouent le plus souvent. **Épingler une version récente** (cette librairie a eu des CVE sur d'anciennes versions) et suivre ses avis de sécurité.

### 3.13 Fichiers impactés — Volet A

| Projet | Fichier | Action |
|---|---|---|
| Les deux | `auth.php` | **Créer** — `require_auth()`, rafraîchissement, découverte OIDC |
| Les deux | `callback.php` | **Créer** — échange du code, validation, JIT, ouverture de session |
| Les deux | `config.php` | **Modifier** — lecture depuis l'environnement, plus de secrets en dur |
| Frontend | `index.php` | **Réécrire** — suppression du formulaire et de `password_verify`, redirection OIDC |
| Frontend | `home.php`, `save.php`, `statistiques.php` | **Modifier** — garde → `require_auth([...])` |
| Frontend | `logout.php` | **Modifier** — déconnexion RP-initiated |
| Backend | `index.php` | **Réécrire** — idem Frontend |
| Backend | `dashboard.php`, `save.php` | **Modifier** — garde → `require_auth([...])` + contrôle de rôle sur la suppression |
| Backend | `droits.php:28-89`, `:355` | **Réécrire** le bloc de création — Admin API + `execute-actions-email` ; **supprimer le champ mot de passe**, ajouter le champ e-mail obligatoire ; bouton « Renvoyer l'e-mail » dans la liste |
| Backend | `logout_bo.php` | **Modifier** — déconnexion RP-initiated |
| Backend | `bin/migrate-users-to-keycloak.php` | **Créer** — script de reprise idempotent |

---

## 4. Volet B — Stockage MinIO

### 4.1 Endpoints

Le déploiement MinIO expose **deux entrées sur le même backend** :

| Entrée | URL | Usage |
|---|---|---|
| Interne | `http://minio.adcm.orangecm` | **Appels serveur → MinIO** (`putObject`, `getObject`, `deleteObject`). Joignable depuis les pods du cluster interne (§5) |
| Publique | `https://api-s3.orange.cm` | **Génération des URLs présignées** destinées au navigateur — le seul usage de cet endpoint côté application |

> ⚠️ **Point d'attention majeur.** La signature d'une URL présignée **couvre l'en-tête `Host`**. Une URL présignée générée avec l'endpoint interne sera **rejetée** si le navigateur l'ouvre via le domaine public. Il faut donc **deux instances de client S3** : une pour les opérations serveur (`S3_ENDPOINT`), une dédiée à la présignature (`S3_PUBLIC_ENDPOINT`). C'est l'erreur d'intégration la plus fréquente sur ce type de montage.

> L'endpoint interne est en **HTTP non chiffré**. Les identifiants S3 (signature de chaque requête) et les images de CNI circulent donc en clair entre les pods et MinIO, sur le réseau interne. C'est un risque réduit mais réel — Q2 demande à l'infra si un TLS est prévu. En attendant, s'assurer que les `NetworkPolicy` du cluster restreignent l'accès à MinIO aux seuls pods ULTRACK.

### 4.2 Bucket, arborescence et habilitations

**Un bucket par environnement**, tous **privés** (aucune policy anonyme) : `ultrack-dev` pendant les développements (lots 1 à 3), `ultrack` en production. Versioning à l'appréciation de l'infra. La policy ci-dessous est à dupliquer par bucket (ou un utilisateur par environnement).

**Convention de clés :**

```
ultrack/
├── agents/
│   ├── photo/{YYYY}/{MM}/{uuid}.jpg
│   └── cni/{YYYY}/{MM}/{uuid}.jpg
└── superviseurs/
    └── cni/{YYYY}/{MM}/{uuid}.jpg
```

Deux règles :

- **UUID v4 opaque**, jamais le login. Le nommage actuel (`recto_{login}_{timestamp}.jpg`) expose le numéro de téléphone de l'agent dans le nom de l'objet (§2.4).
- **Partitionnement `{YYYY}/{MM}`** pour garder les préfixes listables et permettre des règles de cycle de vie par période.

**Utilisateur MinIO dédié** — ne **pas** utiliser les clés d'administration :

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"],
      "Resource": ["arn:aws:s3:::ultrack/*"]
    },
    {
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": ["arn:aws:s3:::ultrack"]
    }
  ]
}
```

**Règle de cycle de vie** : purge des uploads multipart incomplets après 1 jour.

### 4.3 Variables d'environnement

```ini
S3_ENDPOINT=http://minio.adcm.orangecm        # opérations serveur, depuis les pods
S3_PUBLIC_ENDPOINT=https://api-s3.orange.cm   # génération des URLs présignées
S3_REGION=us-east-1                           # ignoré par MinIO, requis par le SDK
S3_BUCKET=ultrack
S3_KEY=<clé d'accès dédiée>
S3_SECRET=<clé secrète dédiée>
S3_PRESIGN_TTL=600                            # secondes
```

> Les clés d'accès **ne doivent pas figurer dans `config.php`** ni dans le dépôt git.

### 4.4 Configuration du client S3

```php
new Aws\S3\S3Client([
    'version'                 => 'latest',
    'region'                  => getenv('S3_REGION'),
    'endpoint'                => getenv('S3_ENDPOINT'),
    'use_path_style_endpoint' => true,   // obligatoire pour MinIO
    'credentials'             => [
        'key'    => getenv('S3_KEY'),
        'secret' => getenv('S3_SECRET'),
    ],
]);
```

Dépendance : `"aws/aws-sdk-php": "^3"`.

### 4.5 Flux d'écriture — proxy serveur

```
Navigateur ──multipart──> PHP (save.php / droits.php)
                            │
                            │ 1. Valider le type MIME RÉEL (finfo), pas l'extension
                            │    Rejeter hors image/jpeg, image/png. Taille max : 10 Mo
                            │ 2. Compresser via GD (max 800 px, qualité 65) → fichier temporaire
                            │ 3. putObject vers MinIO — clé UUID, ContentType, Metadata
                            │ 4. INSERT/UPDATE en base avec la CLÉ de l'objet
                            │ 5. Supprimer le fichier temporaire
                            └── si 3 réussit et 4 échoue : objet orphelin (inoffensif, balayable)
```

**Ordre imposé : objet d'abord, base ensuite.** Un objet orphelin est sans conséquence et se nettoie par un balayage périodique ; l'inverse (ligne en base pointant vers un objet inexistant) casse l'affichage et le PDF.

**Pourquoi pas un upload direct navigateur → MinIO par URL présignée ?** C'est désormais techniquement possible puisque `https://api-s3.orange.cm` est joignable depuis Internet. Mais cela ferait perdre : la compression GD serveur (à réimplémenter en Canvas côté client), la validation MIME serveur, et imposerait une configuration CORS sur le bucket. Sur 3 images compressées à ~150 Ko, le surcoût du proxy est négligeable. **À reconsidérer en v2** si la bande passante PHP devient un point de contention.

`droits.php` doit **aligner son traitement sur `save.php`** : aujourd'hui il fait un `move_uploaded_file()` brut sans compression ni validation (`droits.php:58`). La fonction `compressAndSave()` devient commune aux deux usages.

### 4.6 Flux de lecture — URLs présignées

| Cas d'usage | Mécanisme |
|---|---|
| Affichage navigateur — `dashboard.php:598,644,653` (liste + détail), `droits.php:269-274` (liens CNI) | **URL présignée GET**, TTL 600 s, générée avec le client `S3_PUBLIC_ENDPOINT` |
| Génération PDF — `dashboard.php:51-53` (base64 dompdf) | **`getObject` serveur direct** : dompdf a besoin des octets, pas d'une URL |

**Pourquoi présigné plutôt qu'un proxy PHP en streaming ?** Le tableau de bord est paginé à 20 agents, soit jusqu'à 60 images par page. Les faire transiter par PHP saturerait le processus applicatif. L'URL présignée laisse le navigateur tirer directement depuis MinIO, tout en gardant le bucket privé.

**Risque résiduel assumé** : une URL présignée reste valable sans authentification pendant sa durée de vie, et peut être copiée hors de l'application. Mitigations retenues :

- TTL court : **600 s** pour les photos, **300 s** pour les CNI ;
- en-tête `Referrer-Policy: no-referrer` sur les pages affichant des CNI, pour éviter la fuite de l'URL signée via le `Referer` ;
- `Cache-Control: no-store` sur ces mêmes pages.

Cela reste **très supérieur à l'existant**, où les CNI sont accessibles sans aucune signature ni expiration (§2.4).

### 4.7 Flux de suppression

`Backend/dashboard.php:204-222` :

```
1. SELECT photo_path, cni_front, cni_back FROM agents WHERE id = ?
2. DELETE FROM agents WHERE id = ?          ← la base d'abord
3. deleteObject × 3                          ← les objets ensuite
```

Si l'étape 3 échoue partiellement, il reste des objets orphelins — sans impact fonctionnel. L'ordre inverse produirait des références mortes.

### 4.8 Représentation en base

**Les colonnes existantes sont conservées** (`agents.photo_path`, `agents.cni_front`, `agents.cni_back`, `users.cni_recto`, `users.cni_verso`). Seul leur **contenu** change :

| Avant | Après |
|---|---|
| `recto_695567756_1726300000.jpg` | `agents/cni/2026/09/6f2a1c84-….jpg` |

> **Stocker la clé, jamais l'URL.** Une URL complète en base impose une migration SQL au moindre changement d'endpoint, de bucket ou de schéma d'accès. La clé est stable ; l'URL est dérivée à l'affichage.

**Pas de « lecture double ».** Les pods k8s n'ont pas les anciens dossiers `uploads/` : un repli sur disque local n'a aucun sens sur la nouvelle plateforme. Après la réécriture des chemins au lot R (§4.9), **toute** valeur en base est une clé MinIO. Le test « contient un `/` » sert uniquement de **garde-fou** : une valeur sans `/` révèle une ligne oubliée par le script de réécriture → journaliser, afficher un visuel « image indisponible », ne jamais construire d'URL avec.

### 4.9 Migration des fichiers existants

**Correspondance dossier → préfixe** (le dossier source est conservé dans la clé) :

| Source | Préfixe cible |
|---|---|
| `Frontend/uploads/` | `agents/photo/legacy/uploads/` |
| `Frontend/uploads_pocv2/` | `agents/photo/legacy/uploads_pocv2/` |
| `Frontend/cni_pictures/` | `agents/cni/legacy/cni_pictures/` |
| `Frontend/cni_pictures_pocv2/` | `agents/cni/legacy/cni_pictures_pocv2/` |
| `Backend/cni_superviseurs/` | `superviseurs/cni/legacy/` |
| `photos_final/`, `photos_final_pocv2/`, `uploads_test/` | **Non migrés** — non référencés dans le code (§2.3). À archiver hors ligne après confirmation métier (§10) |

> **Le dossier source doit être conservé dans la clé.** `dashboard.php:581-583` résout aujourd'hui l'emplacement par essais successifs (`uploads/` puis `uploads_pocv2/`) : rien ne garantit l'unicité des noms entre les deux. Les aplatir dans un préfixe commun risquerait d'écraser des fichiers.

**Procédure** (exécutée au lot R, pendant le gel — §5.6) :

```
Étape 0 — Rapatriement depuis l'hébergement actuel (FTP, cf. §5.6)
    lftp -e "mirror --verbose uploads/ ./uploads/" … pour chacun des 5 dossiers actifs
    Vérifier les comptes (find | wc -l) et un échantillon de sommes de contrôle

Étape 1 — Copie vers MinIO (~1 681 fichiers, ~85 Mo)
    mc alias set ultrack https://api-s3.orange.cm <KEY> <SECRET>
    mc mirror ./uploads/ ultrack/ultrack/agents/photo/legacy/uploads/
    … (un mirror par dossier du tableau ci-dessus)
    mc diff pour confirmer que source et cible coïncident

Étape 2 — Réécriture des chemins en base (bin/migrate-files-to-minio.php)
    --dry-run obligatoire avant exécution réelle.
    Pour chaque ligne agents / users : reproduire la logique de résolution
    actuelle (présence du fichier dans chaque dossier rapatrié) pour déterminer le
    dossier d'origine, puis écrire la clé complète.
    Journaliser toute ligne non résolue — ne jamais écraser par une valeur vide.

Étape 3 — Contrôle
    SELECT COUNT(*) FROM agents WHERE photo_path NOT LIKE '%/%' OR cni_front NOT LIKE '%/%' OR cni_back NOT LIKE '%/%';
    SELECT COUNT(*) FROM users  WHERE cni_recto IS NOT NULL AND cni_recto NOT LIKE '%/%';
    → doivent valoir 0. Puis ouvrir un échantillon d'anciens fichiers via URL présignée.

Étape 4 — L'ancien hébergement reste en lecture seule 2 semaines (§5.6), puis archivage hors ligne.
```

### 4.10 Point de vigilance — disponibilité

MinIO devient un **point de défaillance unique** sur le chemin d'enrôlement : aujourd'hui le disque local est toujours disponible, demain une indisponibilité MinIO bloque tout enrôlement terrain.

Si ce risque est jugé inacceptable par le métier, la parade est un **tampon disque local + synchronisation asynchrone par tâche planifiée**. Cela introduit une complexité réelle (état intermédiaire en base, reprise sur erreur, cohérence) et **n'est pas retenu en v1**. À arbitrer (§10).

### 4.11 Fichiers impactés — Volet B

| Projet | Fichier | Action |
|---|---|---|
| Les deux | `storage.php` | **Créer** — `storage_put()`, `storage_get()`, `storage_delete()`, `storage_presign()`, `storage_exists()` |
| Les deux | `save.php:12-96` | **Modifier** — `compressAndSave()` vers fichier temporaire + `putObject` ; validation MIME réelle ; clés UUID |
| Backend | `dashboard.php:8-53` | **Modifier** — récupération base64 pour dompdf via `getObject` |
| Backend | `dashboard.php:204-222` | **Modifier** — `deleteObject` au lieu d'`unlink`, base d'abord |
| Backend | `dashboard.php:576-660` | **Modifier** — `$getValidFileUrl()` → génération d'URL présignée |
| Backend | `droits.php:28-89` | **Modifier** — compression + `putObject` au lieu de `move_uploaded_file` |
| Backend | `droits.php:262-280` | **Modifier** — liens CNI → URLs présignées |
| Backend | `bin/migrate-files-to-minio.php` | **Créer** — réécriture des chemins, idempotent, `--dry-run` |

---

## 5. Déploiement Kubernetes

Les deux applications sont redéployées sur le cluster Kubernetes interne, avec **plusieurs réplicas** chacune, et une **base MySQL dédiée** provisionnée pour le projet. Ce contexte impose des contraintes que l'hébergement mutualisé actuel ne posait pas.

### 5.1 Ce qui change

| Aspect | Hébergement actuel | Cluster k8s |
|---|---|---|
| Fichiers uploadés | Disque local, persistant | **Système de fichiers éphémère** → MinIO devient un prérequis (§4) |
| Sessions PHP | Fichiers, un seul serveur | **N pods** → sessions en base (§5.2) |
| Secrets | En dur dans le code | Secrets k8s injectés en variables d'environnement (§5.3) |
| Journaux | Fichier `error_log` | `stdout` / `stderr`, collectés par le cluster |
| Dépendances | `vendor/` committé | `composer install` au build de l'image |
| Livraison | FTP | Image + manifests |
| Base de données | MySQL mutualisé externe (`91.234.194.101`) | Base dédiée, dans le réseau interne |
| Endpoint MinIO serveur | — | `http://minio.adcm.orangecm` **joignable depuis les pods** → Q1 résolue |

> **Pourquoi le stockage local ne survit pas.** Un pod est remplacé à chaque déploiement d'image, chaque changement de configuration, chaque maintenance de nœud. Tout fichier écrit dans le conteneur depuis la construction de l'image disparaît alors. Et dès deux réplicas, un fichier écrit par un pod est invisible depuis l'autre. Le lot 1 (MinIO) n'est donc plus un choix d'architecture : **aucun déploiement k8s n'est fonctionnel sans lui.**

### 5.2 Sessions en base

**Pourquoi c'est indispensable avec plusieurs réplicas.** Le flux OIDC écrit `state`, `nonce` et `code_verifier` en session **avant** la redirection vers Keycloak, et les relit **au retour** sur `callback.php`. Si le retour atterrit sur un autre pod que celui qui a initié la connexion, la vérification du `state` échoue et le login est rejeté — une fois sur deux avec deux réplicas, aléatoirement. Redis étant écarté, la base dédiée porte les sessions.

**Coût réel** : un `SELECT` indexé par requête, et une écriture **uniquement** quand `$_SESSION` change (`session.lazy_write`, actif par défaut). Sur une navigation normale, il n'y a aucune écriture. À l'échelle de l'application — quelques dizaines de recruteurs — c'est négligeable ; pour comparaison, un seul affichage du tableau de bord (`dashboard.php:282`, neuf agrégats sans `WHERE` sur `agents`) coûte plus cher que toutes les sessions d'une journée.

**Table**

```sql
CREATE TABLE sessions (
  id            VARBINARY(128) NOT NULL PRIMARY KEY,
  user_ref      VARCHAR(64)    NULL,            -- permet de révoquer toutes les sessions d'un utilisateur
  data          MEDIUMBLOB     NOT NULL,        -- 3 tokens Keycloak ≈ 6-8 Ko : BLOB (64 Ko) suffirait, MEDIUMBLOB par sécurité
  last_activity INT UNSIGNED   NOT NULL,
  INDEX idx_sessions_last_activity (last_activity),
  INDEX idx_sessions_user_ref (user_ref)
);
```

**Handler** — une classe implémentant **deux** interfaces :

| Interface | Méthodes | Rôle |
|---|---|---|
| `SessionHandlerInterface` | `open`, `close`, `read`, `write`, `destroy`, `gc` | Cycle de vie de base |
| `SessionUpdateTimestampHandlerInterface` | `validateId`, `updateTimestamp` | **Indispensable.** Sans `updateTimestamp()`, PHP ne peut pas appliquer `lazy_write` à un handler custom et appelle `write()` à chaque requête — ce qui annulerait l'argument de coût ci-dessus. Sans `validateId()`, `use_strict_mode` est inopérant |

Points d'implémentation :

- `read()` — `SELECT data WHERE id = ? AND last_activity > (now − gc_maxlifetime)` ; retourner `''` si absent.
- `write()` — `INSERT … ON DUPLICATE KEY UPDATE data, last_activity, user_ref`. Renseigner `user_ref` depuis `$_SESSION['user_ref']` si présent.
- `updateTimestamp()` — `UPDATE last_activity` seul, sans toucher `data`.
- `gc()` — `DELETE WHERE last_activity < (now − maxlifetime)`. Probabiliste (`gc_probability / gc_divisor`), n'importe quel pod peut l'exécuter ; pas de CronJob nécessaire à cette échelle.
- Enregistrement via `session_set_save_handler($handler, true)` **avant** `session_start()`, dans `config.php` — inclus par toutes les pages après la refonte. Réutiliser le PDO de `config.php`, ne pas ouvrir une seconde connexion.

**Configuration PHP** (`php.ini` de l'image ou `ini_set()` dans `config.php`) :

```ini
session.use_strict_mode  = 1
session.lazy_write       = 1
session.gc_maxlifetime   = 1800      ; à aligner sur « SSO Session Idle » du realm digital-app (Q13)
session.gc_probability   = 1
session.gc_divisor       = 100
session.cookie_secure    = 1
session.cookie_httponly  = 1
session.cookie_samesite  = Lax
session.name             = ULTRACK_BO_SESS   ; ULTRACK_FO_SESS côté Frontend
```

> **Alignement des durées.** `gc_maxlifetime` doit être proche du *SSO Session Idle* du realm. Si la session PHP vit plus longtemps que la session Keycloak, le rafraîchissement du token échoue et l'utilisateur est renvoyé au login — acceptable. Si elle vit moins longtemps, l'utilisateur est déconnecté côté application mais reconnecté silencieusement par le SSO — acceptable aussi, mais déroutant. L'alignement évite les deux.

> **Vérifier `gc_probability` dans l'image.** Les paquets PHP de Debian/Ubuntu le forcent à `0` et délèguent le nettoyage à un cron système — qui n'existe pas dans un conteneur. Les images officielles `php:*` ne le font pas, mais une image maison dérivée d'une distro pourrait hériter du réglage. Sans GC, la table `sessions` croît indéfiniment.

**Révocation des sessions.** Quand un administrateur désactive un recruteur (§3.8), sa session PHP reste valide jusqu'à expiration du refresh token — le compte est désactivé côté Keycloak, mais l'application ne le sait pas. Ajouter à la désactivation : `DELETE FROM sessions WHERE user_ref = ?`. C'est la raison d'être de la colonne `user_ref`.

### 5.3 Secrets et configuration

Toutes les variables d'environnement définies en §3.3 (Keycloak) et §4.3 (MinIO), plus la connexion à la base, sont portées par des **Secrets Kubernetes** injectés dans les pods :

```ini
DB_HOST=<hôte de la base dédiée>
DB_NAME=ultrack
DB_USER=<utilisateur>
DB_PASS=<mot de passe>
DB_MIGRATE_USER=<le même, tant qu'il n'y a qu'un utilisateur — §5.7>
DB_MIGRATE_PASS=<le même>
```

Un Secret par application (`ultrack-frontoffice-env`, `ultrack-backoffice-env`), injecté par `envFrom.secretRef`. Les valeurs ne figurent **ni dans une ConfigMap, ni dans l'image, ni dans git**. Si le cluster dispose d'un gestionnaire de secrets (Vault, External Secrets Operator, Sealed Secrets), s'y conformer.

`config.php` lit exclusivement l'environnement (`getenv()`) et **échoue explicitement au démarrage** si une variable requise manque — un pod mal configuré doit être visible immédiatement, pas au premier upload.

Les variables de journalisation (§5.9 : `APP_NAME`, `LOG_LEVEL`, `GRAYLOG_*`) ne sont pas secrètes et peuvent aller dans une `ConfigMap`.

### 5.4 Image

**Image de base recommandée : `php:8.x-apache`** (Q12). Le `.htaccess` existant fonctionne sans modification, à condition d'activer `mod_rewrite` (`a2enmod rewrite`) et de passer `AllowOverride All` sur le `DocumentRoot`. L'alternative nginx + PHP-FPM impose de traduire les règles de réécriture (`try_files $uri $uri.php`) — faisable, mais du travail en plus sans bénéfice à cette échelle.

**Extensions PHP requises** : `gd` (avec support JPEG), `pdo_mysql`, `fileinfo`, `curl`, `openssl`, `mbstring`, `zip` (pour Composer au build).

**Construction en deux étapes** :

```
Étape « builder » — composer install --no-dev --optimize-autoloader --no-interaction
Étape « runtime »  — copie du code + de vendor/ depuis le builder
```

`vendor/` sort du dépôt git (§6, T5). `composer.lock` y reste : c'est lui qui garantit un build reproductible.

**Journaux** : `display_errors = Off` ; les erreurs PHP sont routées vers le logger applicatif par `Monolog\ErrorHandler` (§5.9), qui écrit en JSON sur `stderr`. L'image `php:*-apache` envoie déjà les journaux Apache vers `stdout`/`stderr` ; s'assurer que `error_log` de PHP n'est pas redirigé vers un fichier.

**Pod sans état** : rien n'est écrit sous `/var/www` à l'exécution. La compression GD (§4.5) utilise `sys_get_temp_dir()` ; si le cluster impose un système de fichiers racine en lecture seule (Q14), monter un `emptyDir` sur `/tmp` et pointer `upload_tmp_dir` dessus.

> **Exécution non-root** (Q14). Beaucoup de clusters imposent `runAsNonRoot`. Or `php:*-apache` écoute sur le port 80, qui exige root. Si la contrainte s'applique : reconfigurer Apache pour écouter sur `8080` (`sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf` + le `VirtualHost`), exposer `8080` dans le `Service`, et fixer `USER www-data`.

**Fichiers à retirer du dépôt** : `.ftpquota`, `tmp/restart.txt` (conventions cPanel/Passenger, sans objet), `error_log`, `vendor/`, et les dossiers d'upload locaux une fois la reprise validée.

### 5.5 Sondes et ressources

Créer `healthz.php`. Rien de particulier pour la migration à l'entrypoint : Apache n'écoute pas avant qu'elle soit finie, la readiness échoue naturellement jusque-là.

```php
// Vérifie UNIQUEMENT la base. Ne pas appeler Keycloak ni MinIO ici :
// une indisponibilité externe ferait redémarrer les pods en boucle.
try { $pdo->query('SELECT 1'); http_response_code(200); echo 'ok'; }
catch (Throwable $e) { http_response_code(503); echo 'db'; }
```

Extrait illustratif de `Deployment` — à adapter aux conventions du cluster (Helm, Kustomize, namespaces) :

```yaml
spec:
  replicas: 2
  template:
    spec:
      containers:
        - name: app
          image: <registry>/ultrack-backoffice:<tag>
          ports: [{ containerPort: 8080 }]
          envFrom:
            - secretRef: { name: ultrack-backoffice-env }
          readinessProbe:
            httpGet: { path: /healthz.php, port: 8080 }
            periodSeconds: 10
          livenessProbe:
            httpGet: { path: /healthz.php, port: 8080 }
            periodSeconds: 30
            failureThreshold: 3
          resources:
            requests: { cpu: 100m, memory: 128Mi }
            limits:   { cpu: 500m, memory: 384Mi }   # GD sur une image 10 Mo peut monter à ~100 Mo
          volumeMounts:
            - { name: tmp, mountPath: /tmp }
      volumes:
        - { name: tmp, emptyDir: {} }
```

> Pas de `sessionAffinity` sur le `Service` ni d'annotation d'affinité sur l'`Ingress` : les sessions en base rendent l'affinité inutile, et l'affinité masquerait un défaut de configuration du handler au lieu de le révéler.

### 5.6 Reprise des données et bascule (lot R)

Deux jeux de données vivent sur l'hébergement mutualisé et sont rapatriés **en fin de chantier**, une fois les lots 1 à 3 recettés sur la plateforme k8s. Jusque-là, l'hébergement actuel reste la production.

**Base de données** — schéma par Phinx, données par dump. À exécuter **pendant le gel des enrôlements**.

```sh
# 1. Dump des DONNÉES SEULES depuis l'hébergement actuel
mysqldump -h 91.234.194.101 -u c2781394c_ultrackuser -p \
  --single-transaction \
  --no-create-info \          # pas de CREATE TABLE : le schéma appartient à Phinx
  --complete-insert \         # INSERT avec noms de colonnes — indispensable, voir ci-dessous
  --skip-triggers --no-create-db \
  c2781394c_ultrackv2 users agents > ultrack-data.sql

# 2. PURGE DES DONNÉES DE TEST sur la base k8s — indispensable, voir ci-dessous
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE agents;  TRUNCATE TABLE users;  TRUNCATE TABLE sessions;
SET FOREIGN_KEY_CHECKS = 1;
-- phinxlog n'est PAS touchée : le schéma reste à jour

# 3. Import des données
mysql -h <base-dédiée> -u <utilisateur> -p ultrack < ultrack-data.sql

# 4. Vérification
SELECT COUNT(*) FROM users;  SELECT COUNT(*) FROM agents;   -- mêmes chiffres qu'à la source
```

> **Pourquoi `--complete-insert` est indispensable.** Par défaut `mysqldump` écrit `INSERT INTO users VALUES (…)` sans noms de colonnes. Or après `…0002`, `users` a trois colonnes de plus (`user_ref`, `keycloak_sub`, `email`) que la source : huit valeurs pour onze colonnes → `Column count doesn't match value count`, import en échec. Avec les noms explicites, les colonnes absentes du dump prennent `NULL`.

> **Pourquoi `--no-create-info`.** Un dump complet importé *avant* le premier démarrage fait échouer `…0001` (`Table 'users' already exists`) → `CrashLoopBackOff` ; importé *après*, ce sont ses `CREATE TABLE` qui échouent. Données seules, Phinx seul maître du schéma.

> **Pourquoi la purge est indispensable.** Pendant les lots 1 à 3, la base k8s a reçu des données de test (`phinx seed:run`, enrôlements de recette). Le dump insère des `id` explicites (`INSERT INTO users (id, …) VALUES (1, …)`) : sans purge, ils entreraient en collision avec les `id` de test → `Duplicate entry for key PRIMARY`, import en échec à mi-chemin. Les objets de test, eux, sont dans `ultrack-dev` et ne gênent pas.

`sessions` et `phinxlog` ne sont pas importées : `sessions` repart vide, `phinxlog` reste tel quel.

**Fichiers** — l'hébergement est accessible en FTP (présence de `.ftpquota`). Ajout d'une étape 0 à la procédure du §4.9 :

```
0. Rapatriement : lftp -e "mirror --verbose uploads/ ./uploads/" (ou rsync si SSH disponible)
   pour chacun des 5 dossiers actifs — ~1 681 fichiers, ~85 Mo
   Vérifier les comptes (find | wc -l) et un échantillon de sommes de contrôle
1. mc mirror … (suite inchangée)
```

**Backfill de `user_ref` — étape obligatoire entre l'import et l'ouverture de l'accès**

Les lignes `users` importées arrivent avec `user_ref = NULL` : la colonne n'existe pas à la source. Or le provisioning JIT (§3.4) cherche une ligne par `user_ref` ; ne trouvant rien, il **créerait une nouvelle ligne** et les anciens enregistrements — vers lesquels pointent tous les `agents.user_id` — deviendraient orphelins. Le choix de conserver `users.id` (§3.9) ne tient que si `user_ref` est renseigné **avant** la première connexion.

```sql
-- Recruteurs : déterministe, si le script de reprise Keycloak (§3.10) a créé
-- chaque compte avec l'ancien username préfixé (convention Q11)
UPDATE users SET user_ref = CONCAT('rec-', username)
 WHERE user_ref IS NULL AND enseigne IS NOT NULL AND enseigne <> '';

-- Back-office : dépend du contenu des anciens username.
--   • s'ils sont déjà des CUID : UPDATE users SET user_ref = username WHERE … ;
--   • sinon : table de correspondance fournie par le métier (Q16), une ligne par compte.

-- E-mails des partenaires existants : fichier username → email livré par le métier (Q7)
-- (chargé dans une table temporaire, puis)
UPDATE users u JOIN tmp_emails t ON t.username = u.username SET u.email = t.email;

-- Contrôle : chaque personne appelée à se connecter doit avoir un user_ref ET un email
SELECT id, username, enseigne FROM users WHERE user_ref IS NULL OR email IS NULL;
```

> Le critère `enseigne` pour distinguer recruteurs et back-office est une **hypothèse à vérifier sur le dump** (Q16). Les comptes qu'on ne sait pas rapprocher restent à `NULL` : ils ne se connecteront jamais, mais l'historique d'enrôlement qui pointe vers eux est préservé. Si un tel compte se connecte malgré tout, le JIT crée une ligne neuve — visible, et réparable à la main (`UPDATE` du `user_ref` sur l'ancienne ligne, suppression de la neuve).

**Séquence de bascule**

| # | Étape | Remarque |
|---|---|---|
| 1 | Gel des enrôlements | Bandeau sur `home.php` de l'hébergement actuel ; durée cible : une demi-journée. **Prévenir les partenaires à l'avance** : ils recevront un e-mail et l'ancien login cessera de fonctionner |
| 2 | Dump des données + rapatriement FTP des fichiers | `--complete-insert --no-create-info` |
| 3 | **Purge des données de test** sur la base k8s | `TRUNCATE users, agents, sessions` — `phinxlog` intact |
| 4 | Import dans la base k8s | |
| 5 | **Backfill de `user_ref`** + import du fichier `username → email` + contrôle | Ci-dessus ; fichier livré par le métier (Q7) |
| 6 | `mc mirror` vers `ultrack` + script de réécriture des chemins + contrôle « 0 ligne sans `/` » | `--dry-run` puis réel — §4.9 |
| 7 | Recette sur données réelles | §9 — un back-office AD **existant** se connecte, ne crée pas de ligne, retrouve ses enrôlements. Pour les recruteurs : passer le script de reprise sur **un seul** compte de test (`--only <username>`), vérifier l'e-mail et la connexion |
| 8 | Bascule DNS / Ingress vers k8s | Les `redirect_uri` de production doivent avoir été ajoutés aux clients Keycloak **avant** |
| 9 | **Script de reprise Keycloak** (§3.10) sur tous les recruteurs | Crée les comptes, envoie « Définissez votre mot de passe ». **Après** la bascule DNS : le lien de l'e-mail redirige vers l'URL de production, qui doit répondre |
| 10 | Ancien hébergement **en lecture seule** pendant 2 semaines | Filet de sécurité |
| 11 | Décommissionnement : suppression de l'ancienne base et de ses identifiants | Ils sont dans l'historique git des deux dépôts (T1) |

> **Environnements Keycloak.** Les clients `ultrack-*` servent au développement puis à la production : ajouter dès maintenant les `redirect_uri` des deux noms d'hôte dans *Valid redirect URIs* (Keycloak en accepte plusieurs), sans wildcard.

> **Bucket par environnement.** `ultrack-dev` pendant les lots 1 à 3, `ultrack` en production. Le `mc mirror` de l'étape 5 cible `ultrack` ; `ultrack-dev` est purgé après la bascule.

### 5.7 Migrations de schéma

**État actuel : aucun mécanisme.** Aucun fichier `.sql` dans les dépôts, aucun `CREATE TABLE` dans le code. Le schéma de `users` et `agents` n'existe que dans la base vivante ; un développeur qui clone le projet ne peut pas le démarrer en local. Ce chantier introduit le schéma en git et un outil pour l'appliquer.

**Outil retenu : Phinx** (`robmorgan/phinx`). Standard des projets PHP sans framework ; même modèle qu'Entity Framework Core côté exécution — table de suivi, application ordonnée de ce qui manque, idempotence — sans `diff` automatique (les migrations s'écrivent à la main, ce qui pour trois tables est sans importance).

| EF Core | Phinx |
|---|---|
| `Migrations/*.cs` | `db/migrations/*.php` |
| `__EFMigrationsHistory` | `phinxlog` |
| `dotnet ef database update` | `phinx migrate` |
| `Add-Migration Nom` | `phinx create Nom` |
| `Remove-Migration` / `Update-Database <prev>` | `phinx rollback` |

> **Pourquoi pas Doctrine ORM + `doctrine/migrations`**, qui offrent le `diff` ? Le `diff` ne vaut que si le code passe par l'ORM ; sinon entités et SQL brut dérivent et les migrations générées ne reflètent plus ce que fait l'application. Or l'application compte ~25 requêtes PDO brutes, dont `dashboard.php` (700 lignes, filtres dynamiques, agrégats, exports). Les réécrire en DQL est un chantier de plusieurs jours avec risque de régression, pour économiser cinq minutes par migration. Hors de proportion.

**Structure**

```
phinx.php                              config : lit DB_HOST / DB_NAME / DB_MIGRATE_USER / DB_MIGRATE_PASS
db/migrations/
├── 20260915000001_schema_initial.php          users, agents — schéma propre, extrait du dump et nettoyé
├── 20260915000002_users_keycloak.php          user_ref, keycloak_sub, email en NULL — password conservé
├── 20260915000003_sessions.php                CREATE TABLE sessions (§5.2)
└── 20260915000005_drop_password.php           lot 4 uniquement — après bascule Keycloak complète
db/seeds/                                       données de développement local (optionnel)
```

Exemple — DSL Phinx, `change()` réversible automatiquement :

```php
public function change(): void
{
    $this->table('users')
        ->addColumn('user_ref', 'string', ['limit' => 64, 'null' => true])
        ->addColumn('keycloak_sub', 'string', ['limit' => 36, 'null' => true])
        ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
        ->addIndex(['user_ref'], ['unique' => true])
        ->update();
}
```

Le SQL brut reste possible (`$this->execute('ALTER TABLE …')`) quand la DSL ne suffit pas — il faut alors écrire `up()` et `down()` explicitement.

> **MySQL ne rend pas le DDL transactionnel** : un `ALTER TABLE` provoque un commit implicite. Une migration qui échoue à mi-chemin laisse la base dans un état intermédiaire à corriger à la main. Garder chaque migration **petite et mono-objet** limite le dégât.

> `…0002` ajoute `user_ref`, `keycloak_sub` et `email` en `NULL` et **conserve `password`** : au premier déploiement k8s (après le lot 1, avant le lot 2), l'authentification est encore locale. `password` ne tombe qu'au lot 4, par `…0005`.

**Exécution : au démarrage du conteneur, dans l'entrypoint**

```sh
#!/bin/sh
# docker-entrypoint.sh
set -e
php db/with-lock.php -- vendor/bin/phinx migrate -e "${APP_ENV:-production}"   # verrou GET_LOCK, voir ci-dessous
exec apache2-foreground
```

La migration tourne **avant** qu'Apache démarre. Si elle échoue, le conteneur sort en erreur, le pod passe en `CrashLoopBackOff` et le rollout s'arrête de lui-même — visible immédiatement. La sonde de readiness n'a rien de particulier à faire : Apache n'écoute pas tant que la migration n'est pas terminée.

> **Verrou obligatoire, même avec une seule réplique.** L'hypothèse « un seul pod au démarrage » est fragile : un rolling update crée un second pod pendant que le premier tourne, et un `replicas: 2` posé plus tard ne préviendra personne. `db/with-lock.php` ouvre sa propre connexion PDO, exécute `SELECT GET_LOCK('ultrack_migrate', 60)`, lance Phinx (`passthru`), puis `RELEASE_LOCK`. Le verrou étant lié à la connexion, il tient pendant toute la durée de la migration et tombe de lui-même si le processus meurt. Le premier pod migre, les autres attendent puis constatent qu'il n'y a rien à faire, quel que soit leur nombre. L'entrypoint appelle `php db/with-lock.php` au lieu de `phinx migrate` directement.

**Deux dépôts, une base : qui migre ?**

Les deux applications attaquent la même base et la même table `phinxlog`. **Les deux entrypoints exécutent `phinx migrate`**, avec **les mêmes fichiers de migration** dans les deux dépôts. Le verrou `GET_LOCK` rend la concurrence inoffensive : le premier pod — Backend ou Frontend — migre, l'autre constate qu'il n'y a rien à faire. Un fichier de migration présent dans un seul dépôt est une erreur : l'autre application démarrerait sur un schéma qu'elle ne connaît pas. C'est un argument de plus pour le paquet commun (§7), où `db/migrations/` n'existerait qu'une fois.

**Utilisateur MySQL**

Le DBA fournit **un utilisateur unique avec droits DDL** (`CREATE`, `ALTER`, `DROP`, `INDEX` en plus du DML). `DB_MIGRATE_*` et `DB_*` pointent donc sur les mêmes identifiants ; `phinx.php` et `config.php` lisent chacun leur jeu de variables, ce qui permettra de séparer plus tard sans toucher au code.

> **Choix assumé.** Deux utilisateurs (l'un sans DDL pour l'application) empêcheraient qu'une injection SQL ou un bug altère le schéma. Toutes les requêtes de l'application étant déjà préparées, et l'outil étant interne, le risque est jugé acceptable au regard de la simplicité de provisionnement. La table `sessions` reste créée par la migration `…0003`, pas par le handler à la volée — question de traçabilité du schéma, plus de droits.

**Alternative** — un `Job` Kubernetes lancé par le pipeline avant le rollout (hook Helm `pre-upgrade`, ou `kubectl wait --for=condition=complete`). Une seule exécution garantie sans verrou, et le Deployment ne roule que si le Job a réussi. Plus rigoureux, mais une pièce de plus dans le pipeline ; l'entrypoint verrouillé est retenu par simplicité.

**Bootstrap de la base dédiée (lot 0)** — le schéma en git fait autorité dès le premier jour : le premier déploiement applique `…0001`, `…0002`, `…0003` sur la base vide. Les données réelles n'arrivent qu'au lot R (§5.6), par import données seules dans ce schéma. Si `…0001` et le schéma réel de la source divergent, l'import échoue **immédiatement** et de façon lisible — c'est voulu.

**En local** : `docker compose up` (MySQL) + `phinx migrate` + `phinx seed:run` suffisent à démarrer le projet de zéro.

**Répartition des responsabilités**

| | Fait par | Quand |
|---|---|---|
| Créer la base `ultrack` | DBA | Une fois |
| Créer l'utilisateur avec droits DDL | DBA | Une fois |
| Créer et faire évoluer les tables | Phinx, à l'entrypoint | À chaque démarrage |
| Dumper et importer les données existantes | Équipe projet, pendant le gel | Une fois (§5.6) |

> **Pourquoi les tables ne sont pas créées par le DBA.** Chaque changement de schéma deviendrait un ticket — ce chantier en compte déjà quatre — et le déploiement qui a besoin de la colonne attendrait le ticket. Surtout, le schéma resterait hors de git : on retomberait dans la situation actuelle. Le DBA qui « crée le schéma » (au sens MySQL : `CREATE SCHEMA` = `CREATE DATABASE`) fait exactement ce qui est attendu de lui, et rien de plus — comme pour les projets .NET de l'organisation.

**Demande de base à formuler**

```
Base         ultrack — MySQL 8.x (vérifier la compatibilité avec la version source du dump)
Charset      utf8mb4 / utf8mb4_unicode_ci

Utilisateur
  ultrack_app   SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES  sur ultrack.*
                (droits DDL requis : les tables sont créées par l'application au démarrage)

Accès        depuis les pods du namespace ULTRACK uniquement (CIDR ou IP de sortie du cluster)
Volumétrie   < 1 Go — ~500 agents, quelques dizaines d'utilisateurs, sessions courtes
Sauvegarde   quotidienne ; rétention alignée sur la conservation légale des CNI (Q9)
```

> **Charset.** La base actuelle est en `utf8` (DSN PDO : `charset=utf8`). Passer en `utf8mb4` est le bon choix, mais impose de mettre à jour le DSN dans `config.php` (`charset=utf8mb4`).

### 5.8 Environnement de développement local

Un développeur doit pouvoir démarrer les deux applications **sans accès au cluster ni à la base de production**.

```yaml
# docker-compose.yml (racine de chaque dépôt) — indicatif
services:
  app:
    build: .
    ports: ["8080:8080"]
    env_file: .env                 # copié depuis .env.example, jamais committé
    depends_on: [db, minio]
  db:
    image: mysql:8
    environment: { MYSQL_DATABASE: ultrack, MYSQL_USER: ultrack, MYSQL_PASSWORD: ultrack, MYSQL_ROOT_PASSWORD: root }
    ports: ["3306:3306"]
  minio:
    image: minio/minio
    command: server /data --console-address ":9001"
    environment: { MINIO_ROOT_USER: minio, MINIO_ROOT_PASSWORD: minio12345 }
    ports: ["9000:9000", "9001:9001"]
  minio-init:                      # crée le bucket au démarrage
    image: minio/mc
    depends_on: [minio]
    entrypoint: sh -c "mc alias set local http://minio:9000 minio minio12345 && mc mb -p local/ultrack-dev"
```

| Composant | En local |
|---|---|
| Base | MySQL du compose ; `phinx migrate` s'exécute à l'entrypoint comme partout ; `phinx seed:run` pour des données de test |
| MinIO | Conteneur local ; `S3_ENDPOINT=http://minio:9000`, `S3_PUBLIC_ENDPOINT=http://localhost:9000` (le navigateur du dev est hors du réseau compose) |
| Keycloak | **Pas de Keycloak local** — trop lourd, et la fédération AD ne se reproduit pas. Utiliser le realm `digital-app` de **préproduction** (`keycloak-preprod-dev.odr.orange.cm`), en y ajoutant `http://localhost:8080/callback.php` aux *Valid redirect URIs* des deux clients (Q4). Les comptes AD réels y fonctionnent |

Un `.env.example` **committé** liste toutes les variables (§3.3, §4.3, §5.3, §5.9) avec des valeurs locales ou des `<placeholder>` ; `.env` est dans `.gitignore`. `GRAYLOG_ENABLED=false` en local : les logs JSON sur `stderr` suffisent (`docker compose logs -f app`).

### 5.9 Journalisation

**État actuel : aucune journalisation applicative.** Pas de logger, pas de Monolog. Seul le `error_log` du moteur PHP existe — et il est committé dans les deux dépôts (T6). Les erreurs fonctionnelles sont affichées à l'utilisateur (`Swal.fire`) puis perdues ; un échec d'upload, un refus d'accès, une création de compte ne laissent aucune trace.

**Cible : un logger Monolog unique** (`logger.php`, dans le socle, inclus par `config.php`), avec deux destinations :

| Destination | Statut | Format | Rôle |
|---|---|---|---|
| `php://stderr` | **Toujours actif** | JSON (`JsonFormatter`) | Collecté par k8s — la source de vérité |
| Graylog | Optionnel (`GRAYLOG_ENABLED`) | GELF / **UDP** | Réplication vers la plateforme de logs centralisée ; **jamais bloquant** |

**Contraintes non négociables**

| # | Contrainte |
|---|---|
| L1 | Graylog est **ajouté** au logger (`pushHandler`), jamais un remplacement. `stderr` fonctionne à l'identique avec ou sans Graylog |
| L2 | Toute erreur liée à Graylog — hôte injoignable, configuration invalide, exception du transport — est **silencieusement avalée**. Jamais d'exception qui remonte, casse une requête ou empêche le démarrage |
| L3 | Désactivable par `GRAYLOG_ENABLED` ; cible par `GRAYLOG_HOST` / `GRAYLOG_PORT` (défaut `12201`, port GELF standard). **Désactivé par défaut** |
| L4 | Protocole **UDP** (*fire-and-forget*), pas TCP/HTTP. On accepte la perte de logs si Graylog tombe, contre la garantie qu'un envoi ne bloque **jamais** une requête |
| L5 | Ce qui ne doit **jamais** apparaître dans un log : tokens (access, refresh, ID), `code` OIDC, mots de passe, URLs présignées complètes, numéros de CNI, contenu d'images, `$_SESSION` brute, secrets d'environnement. Les logs partent vers Graylog, consulté par des personnes sans accès à l'application |

**Implémentation** (`logger.php`, indicatif) :

```php
$logger = new Monolog\Logger(getenv('APP_NAME'));   // ultrack-backoffice | ultrack-frontoffice

$stderr = new Monolog\Handler\StreamHandler('php://stderr', Monolog\Level::fromName(getenv('LOG_LEVEL') ?: 'info'));
$stderr->setFormatter(new Monolog\Formatter\JsonFormatter());
$logger->pushHandler($stderr);

// Contexte de requête : ip, méthode, CHEMIN SANS query string — le `code` OIDC transite en URL sur callback.php
$logger->pushProcessor(fn ($r) => $r->with(extra: $r->extra + [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null, 'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'path' => strtok($_SERVER['REQUEST_URI'] ?? '', '?'), 'user_ref' => $_SESSION['user_ref'] ?? null ]));

if (filter_var(getenv('GRAYLOG_ENABLED'), FILTER_VALIDATE_BOOL)) {
    try {
        $transport = new Gelf\Transport\UdpTransport(getenv('GRAYLOG_HOST'), (int) (getenv('GRAYLOG_PORT') ?: 12201));
        $logger->pushHandler(new SafeGelfHandler(new Gelf\Publisher($transport)));   // L1, L4
    } catch (Throwable $e) {                                                           // L2 : construction en échec → logger inchangé
        $logger->warning('Graylog désactivé : configuration invalide', ['error' => $e->getMessage()]);
    }
}
Monolog\ErrorHandler::register($logger);   // erreurs PHP et exceptions non attrapées → logger, en JSON
```

`SafeGelfHandler` hérite de `Monolog\Handler\GelfHandler` et surcharge `write()` pour attraper tout `Throwable` et l'ignorer (L2). UDP est déjà *fire-and-forget* ; c'est un filet supplémentaire contre toute exception inattendue du transport.

> **Ne pas utiliser `Monolog\Processor\WebProcessor` tel quel** : il journalise l'URL complète avec sa query string, donc le `code` d'autorisation sur `callback.php` (L5). Le processeur ci-dessus ne garde que le chemin.

**Ce qu'on journalise** — chaque lot ajoute ses événements :

| Lot | Événement | Niveau | Champs | Jamais |
|---|---|---|---|---|
| 2, 3 | Connexion réussie | `info` | `user_ref`, `client_id`, `roles` | tokens |
| 2, 3 | Rôle requis absent (403) | `warning` | `user_ref`, `roles` reçus, rôle attendu, `path` | — |
| 2, 3 | Échec de validation d'un token (`state`, `nonce`, signature, `azp`) | `warning` | motif, `ip` | le token, le `code` |
| 2, 3 | Déconnexion | `info` | `user_ref` | — |
| 3 | Recruteur créé / e-mail envoyé / e-mail renvoyé / désactivé | `info` | `user_ref` du recruteur, `keycloak_sub`, `user_ref` de l'admin | e-mail en clair |
| 3 | Compensation exécutée (suppression Keycloak après échec local) | `error` | `keycloak_sub`, cause | — |
| 1 | `putObject` / `deleteObject` | `info` | clé de l'objet, `agents.id`, `user_ref` | contenu, URL présignée |
| 1 | Garde-fou : valeur sans `/` en base (§4.7) | `warning` | `agents.id`, colonne, valeur | — |
| 1 | MinIO injoignable | `error` | endpoint, opération | clés d'accès |
| 0 | Démarrage : variable d'environnement manquante | `critical` | nom de la variable | sa valeur |
| 0 | Sessions révoquées (`DELETE FROM sessions`) | `info` | `user_ref`, nombre | — |
| tous | Exception non attrapée | `error` | via `ErrorHandler` | — |

**Variables d'environnement** (non secrètes — une `ConfigMap` convient, contrairement à §5.3) :

```ini
APP_NAME=ultrack-backoffice        # ou ultrack-frontoffice
LOG_LEVEL=info                     # jamais debug en production
GRAYLOG_ENABLED=false
GRAYLOG_HOST=<à fournir — Q18>
GRAYLOG_PORT=12201
```

**Réseau k8s** : l'envoi UDP sort du pod vers Graylog — vérifier que les `NetworkPolicy` d'egress l'autorisent (Q18).

**Validation** (recette §9.3) :

1. Sur un Graylog de développement (instance de l'organisation, ou `docker compose -f compose.graylog.yml` séparé — Graylog exige MongoDB + OpenSearch, trop lourd pour le compose par défaut), créer un input **GELF UDP** sur le port configuré. Ne pas le confondre avec un input **CEF UDP** (mauvais format) ni réutiliser un port déjà pris par un autre input.
2. `GRAYLOG_ENABLED=true`, déclencher une connexion → l'événement apparaît dans Graylog avec `user_ref`, sans token.
3. Couper Graylog, ou mettre un `GRAYLOG_HOST` injoignable → **aucune erreur visible** ; `stderr` continue normalement.
4. `grep` sur les logs d'une session complète : **zéro** occurrence d'un token, d'un `code=`, d'une URL présignée, d'un numéro de CNI.

> **Implémentation de référence** en production dans l'organisation : projet Shlink (`module/Core/src/Logger/SafeGelfHandler.php`, `GraylogLoggerDelegatorFactory.php`). Le *delegator factory* Laminas y joue le rôle que joue ici le simple `pushHandler()` conditionnel de `logger.php`.

### 5.10 Fichiers impactés — Déploiement

| Projet | Fichier | Action |
|---|---|---|
| Les deux | `config.php` | **Modifier** — lecture stricte de l'environnement, enregistrement du handler de session avant `session_start()` |
| Les deux | `session_db.php` | **Créer** — handler `SessionHandlerInterface` + `SessionUpdateTimestampHandlerInterface` |
| Les deux | `logger.php`, `SafeGelfHandler.php` | **Créer** — Monolog vers `stderr` en JSON + Graylog GELF/UDP optionnel et non bloquant (§5.9) |
| Les deux | `healthz.php` | **Créer** |
| Les deux | `phinx.php`, `db/migrations/*.php`, `db/with-lock.php` | **Créer** — schéma en git, config Phinx, verrou `GET_LOCK` (§5.7) |
| Les deux | `docker-entrypoint.sh` | **Créer** — `phinx migrate` puis `exec apache2-foreground` |
| Les deux | `Dockerfile`, `.dockerignore` | **Créer** — build en deux étapes |
| Les deux | Manifests k8s (`Deployment`, `Service`, `Ingress`, `Secret`) | **Créer** — selon les conventions du cluster |
| Les deux | `.gitignore` | **Créer** — `vendor/`, `.env`, `error_log`, `tmp/` |
| Les deux | `docker-compose.yml`, `.env.example`, `db/seeds/` | **Créer** — environnement de développement local (§5.8) |
| Les deux | `.ftpquota`, `tmp/restart.txt`, `error_log`, `vendor/` | **Supprimer du dépôt** |
| Backend | `droits.php` (désactivation) | **Modifier** — `DELETE FROM sessions WHERE user_ref = ?` |

---

## 6. Sécurisation transverse

Ces points sont **dans le périmètre** du chantier, car les fichiers concernés sont de toute façon réécrits.

| # | Sujet | Action |
|---|---|---|
| T1 | Identifiants MySQL en clair et committés (`config.php:3-6`, `index.php:5`, **dupliqués dans les deux dépôts**) | Passage en variables d'environnement. La **nouvelle** base n'a jamais ses identifiants dans le code. L'**ancienne** est décommissionnée au lot 4 (§5.6, étape 11) : ses identifiants, présents dans l'historique git, meurent avec elle. |
| T2 | CNI accessibles publiquement (§2.4) | Résolu par le volet B. Vérifier en recette qu'aucun accès direct ne subsiste. |
| T3 | Absence de contrôle de rôle | Résolu par le volet A (§3.6). |
| T4 | `droits.php:47-58` — type de fichier déterminé par l'extension | Validation par type MIME réel (`finfo`). |
| T5 | `.gitignore` absent | Créer, et y placer `.env`, `error_log`, `tmp/`, `vendor/`. |
| T6 | `error_log` (28 Ko) committé dans les deux dépôts ; aucune journalisation applicative | Retirer du suivi git, vérifier qu'il ne contient pas de données personnelles. Remplacé par le logger de §5.9. |

---

## 7. Socle commun

Les deux dépôts dupliquent déjà `config.php` et `save.php` à l'identique. Ce chantier ajoute `auth.php`, `callback.php`, `storage.php`, `session_db.php`, `logger.php` et `SafeGelfHandler.php` — soit **huit fichiers à maintenir en double**.

**Recommandation** : extraire un paquet `ultrack/common` (dépôt git dédié, tiré par Composer) contenant configuration, authentification et stockage.

Le passage à une image construite par pipeline (§5.4) rend cette extraction **simple** : le paquet est résolu par `composer install` au build, sans contrainte d'hébergement. Il n'y a plus de bonne raison de ne pas le faire.

**Repli**, à n'envisager que si le dépôt commun ne peut pas être créé dans les délais : dupliquer les fichiers, avec un en-tête explicite :

```php
// ⚠️ Fichier partagé avec ULTRACK-Backend. Toute modification doit être reportée
// à l'identique dans l'autre dépôt.
```

C'est un pis-aller : l'expérience montre que ces copies divergent. `index.php` a déjà divergé entre les deux projets (gestion d'erreur présente côté Backend, absente côté Frontend).

---

## 8. Séquencement

Les deux volets touchent `save.php` et `droits.php`. **Les mener en parallèle produira des conflits.**

**Principe : la nouvelle plateforme se construit et se recette à vide ; la production actuelle n'est touchée qu'à la fin.** Pendant les lots 1 à 3, l'hébergement mutualisé reste la production — les recruteurs continuent d'enrôler. Le cluster k8s tourne sur des données de test (`phinx seed:run`) et un bucket dédié (`ultrack-dev`). La reprise des données réelles est la dernière étape avant la bascule.

| Lot | Contenu | Dépendances |
|---|---|---|
| **0 — Prérequis** | `.gitignore`, sortie des secrets du code, création buckets + utilisateur MinIO, rôles + compte de service sur les clients Keycloak (Q15), provisionnement de la base dédiée et de son utilisateur DDL, `Dockerfile` + manifests k8s, handler de session, logger Monolog + Graylog optionnel (§5.9), migrations Phinx + entrypoint (§5). **Premier déploiement k8s, base vide.** | Infra + IAM |
| **1 — MinIO** | `storage.php`, réécriture des flux fichiers, garde-fou sur les clés (§4.8) | Lot 0 |
| **2 — Keycloak Back-office** | `auth.php`, `callback.php`, `index.php`, gardes, rôles, logout. **AD déjà fédéré → le plus simple** | Lot 0 |
| **3 — Keycloak Front-office** | Même socle + provisioning `droits.php` + script de reprise des comptes | Lot 2 |
| **R — Reprise et bascule** | Gel, dump / import, **purge des données de test**, **backfill de `user_ref`**, rapatriement FTP + `mc mirror` + réécriture des chemins, recette sur données réelles, bascule DNS, **script de reprise Keycloak** (§5.6) | Lots 1 à 3 recettés |
| **4 — Clôture** | Suppression de `users.password` (`…0005`), décommissionnement de l'ancien hébergement et de ses identifiants | Lot R + 2 semaines d'observation |

> **Le lot 1 conditionne tout déploiement k8s utile** : sans MinIO, les photos disparaissent au premier redéploiement (§5.1). Le premier déploiement du lot 0 sert à valider la plateforme (démarrage, migrations, sondes), pas à enrôler.

> ⚠️ **Coût de ce séquencement, à assumer.** La fuite de CNI du §2.4 reste **active en production** pendant toute la durée des lots 1 à 3. Si cette durée se compte en mois, un correctif minimal sur l'hébergement actuel se justifie : un `.htaccess` interdisant l'accès direct à `uploads/`, `cni_pictures/`, `cni_superviseurs/`, et un `media.php` d'une vingtaine de lignes qui vérifie `$_SESSION['user_id']` puis `readfile()`. `dashboard.php` et `droits.php` remplacent leurs `src=` / `href=` directs par `media.php?f=…`. C'est du travail sur du code destiné à disparaître, mais c'est le seul moyen de fermer la faille avant la bascule (Q17).

---

## 9. Recette

### Volet A — Authentification

- [ ] Accès à une page protégée sans session → redirection Keycloak
- [ ] Connexion back-office avec un CUID AD → accès `dashboard.php`
- [ ] Connexion front-office avec un compte natif → accès `home.php`
- [ ] Recruteur tentant `dashboard.php` → **403**, pas de boucle de redirection
- [ ] `SUPERVISEUR` : la suppression d'agent est refusée
- [ ] Rappel du `callback.php` avec un `state` invalide → rejet
- [ ] Rappel avec un code déjà consommé → rejet
- [ ] Token expiré → rafraîchissement silencieux, aucune interruption visible
- [ ] Après déconnexion, retour arrière navigateur → **pas de reconnexion automatique**
- [ ] Identifiant de session modifié après connexion (anti-fixation)
- [ ] Création d'un recruteur → compte Keycloak **sans credentials**, rôle affecté, ligne locale avec `user_ref` et `email`, **e-mail reçu** avec un lien
- [ ] Le lien définit le mot de passe puis connecte au front-office ; réutilisé ensuite → **refusé** (usage unique)
- [ ] Lien expiré (`lifespan` dépassé) → message clair, bouton « Renvoyer l'e-mail » fonctionnel
- [ ] « Mot de passe oublié » sur la page Keycloak → e-mail reçu, réinitialisation sans intervention admin
- [ ] Création avec échec simulé de l'INSERT local → **l'utilisateur Keycloak est supprimé**
- [ ] `agents.user_id` des enregistrements existants toujours résolu correctement
- [ ] **Lot R** : un back-office AD *existant* se connecte → **aucune ligne créée** dans `users`, ses anciens enrôlements apparaissent
- [ ] **Lot R** : un recruteur *existant* reçoit l'e-mail du script de reprise, définit son mot de passe, se connecte → **aucune ligne créée**, ses anciens enrôlements apparaissent dans ses statistiques

### Volet B — Stockage

- [ ] Enrôlement complet → 3 objets dans MinIO, clés UUID, 3 chemins en base
- [ ] Objets de type `image/jpeg`, redimensionnés à 800 px max
- [ ] Fichier non-image renommé en `.jpg` → **rejeté**
- [ ] Affichage tableau de bord → images visibles via URL présignée
- [ ] URL présignée réutilisée après expiration du TTL → **403**
- [ ] Accès direct au bucket sans signature → **403**
- [ ] Export PDF → images correctement embarquées
- [ ] Suppression d'agent → ligne supprimée **et** 3 objets supprimés
- [ ] **Lot R** : après réécriture des chemins, **aucune** ligne `agents` / `users` avec un chemin sans `/` ; un échantillon d'anciens fichiers s'affiche via URL présignée
- [ ] Une valeur sans `/` injectée à la main → visuel « indisponible », **aucune URL construite**, ligne journalisée
- [ ] Script de migration en `--dry-run` → aucune écriture
- [ ] Script rejoué deux fois → résultat identique (idempotence)
- [ ] MinIO injoignable → message d'erreur explicite, **aucune ligne en base sans fichier**

### Déploiement

- [ ] Avec **2 réplicas et sans affinité**, 20 connexions Keycloak successives réussissent toutes
- [ ] Redéploiement (`kubectl rollout restart`) → les utilisateurs connectés **restent connectés**
- [ ] Un pod supprimé en cours de navigation → l'utilisateur ne s'en aperçoit pas
- [ ] Désactivation d'un recruteur dans `droits.php` → sa session en cours est **immédiatement invalide**
- [ ] Table `sessions` : les lignes expirées disparaissent (GC effectif) — vérifier après `gc_maxlifetime` + quelques requêtes
- [ ] Une navigation sans modification de session ne produit **aucun `UPDATE`** sur `sessions` (vérifier avec le journal des requêtes)
- [ ] Pod démarré avec une variable d'environnement manquante → **échoue au démarrage**, message explicite
- [ ] `healthz.php` → 200 base disponible, 503 base coupée ; **non affecté** par une coupure de Keycloak ou MinIO
- [ ] Aucun fichier écrit sous `/var/www` pendant un enrôlement complet
- [ ] Aucun secret dans l'image (`docker history`, `grep` sur les couches)
- [ ] `phinx migrate` rejoué sur une base à jour → aucune action, sortie `0`
- [ ] Migration en échec → le pod **ne démarre pas** (`CrashLoopBackOff`), le rollout s'arrête
- [ ] Deux pods démarrés simultanément sur une base en retard → **une seule** migration appliquée, aucune erreur (test du verrou)
- [ ] `phinx rollback` puis `phinx migrate` → état identique
- [ ] Base vide + `phinx migrate` + import données (`--complete-insert`) → application fonctionnelle, `COUNT(*)` identiques à la source (test du bootstrap)
- [ ] Logs applicatifs en **JSON sur `stderr`** (`kubectl logs`) ; une erreur PHP non attrapée y apparaît en JSON, pas en texte brut
- [ ] `GRAYLOG_ENABLED=true` → une connexion apparaît dans Graylog avec `user_ref`, **sans token**
- [ ] `GRAYLOG_HOST` injoignable → **aucune erreur visible**, `stderr` continue, la requête n'est pas ralentie
- [ ] `GRAYLOG_ENABLED=false` → aucune tentative réseau vers Graylog
- [ ] `grep` des logs d'une session complète (login, upload, logout) : **zéro** token, `code=`, URL présignée, numéro de CNI

---

## 10. Questions ouvertes

Chaque question indique le lot qu'elle bloque ; celles marquées **Lot 0** sont à traiter en premier.

| # | Question | Destinataire | Bloque |
|---|---|---|---|
| Q1 | ~~L'hébergement PHP est-il dans le réseau Orange ?~~ **Résolu** : déploiement sur le cluster k8s interne, `minio.adcm.orangecm` joignable depuis les pods (§4.1, §5). | — | — |
| Q2 | Un TLS est-il prévu sur l'endpoint interne MinIO ? | Infra | Non |
| Q3 | La fédération AD est-elle déjà configurée dans le realm, ou reste-t-elle à créer ? | IAM | Lot 2 |
| Q4 | Transmission des **secrets** des deux clients (canal sécurisé), ajout des `redirect_uri` de **dev local** (`http://localhost:8080/callback.php`) et de **production** sur les deux clients, et accès au realm de **préproduction** pour les développeurs (§5.8) | IAM | Lot 2 |
| Q5 | ~~Un seul realm ou deux ?~~ **Résolu** : realm `digital-app` mutualisé, clients `ultrack-backoffice` et `ultrack-frontoffice` **créés**. Reste : les rôles `ADMIN`, `SUPERVISEUR`, `RECRUTEUR` (§3.1) et le compte de service (Q15). | Vous | Lot 2 |
| Q6 | ~~E-mail des recruteurs / SMTP du realm~~ **Résolu** : les partenaires ont un e-mail, et le SMTP est configuré sur `digital-app`. `execute-actions-email` (§3.8) est le flux retenu ; le repli « mot de passe temporaire » n'est plus nécessaire. | — | — |
| Q7 | **Collecte des adresses e-mail des partenaires existants** — **engagée** par le métier. Livrable attendu avant le lot R : un fichier `username → email` couvrant tous les comptes actifs, importé dans `users.email` juste après le dump (§5.6, étape 4). Le script de reprise leur enverra ensuite « Définissez votre mot de passe » (§3.10). | Métier | Lot R |
| Q8 | `photos_final/`, `photos_final_pocv2/`, `uploads_test/` (613 fichiers) : confirmation qu'ils sont abandonnés ? | Métier | Lot 1 |
| Q9 | Durée de conservation réglementaire des images de CNI ? Conditionne les règles de cycle de vie MinIO. | Conformité / Juridique | Non |
| Q10 | Le risque d'indisponibilité MinIO bloquant l'enrôlement terrain est-il acceptable ? (§4.10) | Métier | Lot 1 |
| Q11 | Confirmer la convention `rec-<ancien username>` pour les usernames recruteurs — aucun CUID ne commence par `rec-` ? (§3.5, §3.8) | IAM | Lot 3 |
| Q12 | Image de base : `php:8.x-apache` (recommandé, `.htaccess` conservé) ou nginx + FPM imposé par les standards du cluster ? (§5.4) | Infra | Lot 0 |
| Q13 | Valeur du *SSO Session Idle* du realm `digital-app`, pour aligner `session.gc_maxlifetime` (§5.2) | IAM | Lot 0 |
| Q14 | Le cluster impose-t-il `runAsNonRoot` et/ou un système de fichiers racine en lecture seule ? Conditionne le port d'écoute et le montage `/tmp` (§5.4) | Infra | Lot 0 |
| Q15 | Compte de service pour `droits.php` : **(a)** 3ᵉ client `ultrack-provisioning` ou *Service accounts roles* sur `ultrack-backoffice` ? **(b)** `manage-users` realm-wide, ou *Fine-Grained Permissions* restreintes à un groupe `ultrack-recruteurs` ? (§3.1) | IAM + Sécurité | Lot 3 |
| Q16 | Les anciens `username` back-office sont-ils des CUID ? Sinon, table de correspondance username → CUID. Et `enseigne` distingue-t-il fiablement recruteurs et back-office dans le dump ? (§5.6) | Métier | Lot R |
| Q17 | Durée prévue des lots 1 à 3. Au-delà de quelques semaines, appliquer le correctif minimal de la fuite de CNI sur l'hébergement actuel (§8) ? | Métier + Sécurité | Lot 1 |
| Q18 | Graylog : hôte et port des instances de développement et de production ; input GELF UDP existant ou à créer ; egress UDP autorisé depuis le namespace ULTRACK (§5.9) | Infra / Observabilité | Lot 0 (non bloquant : `GRAYLOG_ENABLED=false` en attendant) |

---

## 11. Dépendances Composer

```json
{
    "require": {
        "php": ">=8.0",
        "dompdf/dompdf": "^3.1",
        "aws/aws-sdk-php": "^3",
        "jumbojett/openid-connect-php": "^1.0",
        "robmorgan/phinx": "^0.16",
        "monolog/monolog": "^3",
        "graylog2/gelf-php": "^2"
    }
}
```

Extensions PHP requises : `curl`, `gd` (JPEG), `fileinfo`, `openssl`, `json`, `mbstring`, `pdo_mysql`, `sockets` (transport UDP de gelf-php).

> La version de PHP est désormais fixée par l'image (§5.4). Partir sur la **dernière 8.x stable** : `dompdf ^3.1` tolère PHP 7.1+, mais `aws/aws-sdk-php ^3` et le support des correctifs de sécurité imposent 8.0 au minimum.
