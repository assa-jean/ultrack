# ULTRACK — Prompts d'exécution de la spec

Compagnon de `SPEC-KEYCLOAK-MINIO.md`. À coller dans l'assistant de code de votre choix (Cursor, Copilot Chat, ChatGPT, Claude Code, Windsurf…). Rien ici n'est spécifique à un outil.

---

## Principes

**Un prompt = un lot × un dépôt.** La spec est séquencée (§8) parce que les lots touchent les mêmes fichiers. Un prompt « implémente la spec » produit du code incohérent. Découpez.

**Ordre d'exécution** — par lot, Backend puis Frontend, jamais les deux en parallèle sur le même lot dans la même main :

| # | Dépôt | Lot |
|---|---|---|
| 1 | Backend | 0 — Prérequis |
| 2 | Frontend | 0 — Prérequis (reprise du socle) |
| 3 | Backend | 1 — MinIO |
| 4 | Frontend | 1 — MinIO (`storage.php` repris + `save.php`) |
| 5 | Backend | 2 — Keycloak back-office *(pas de Frontend)* |
| 6 | Backend | 3 — Provisioning |
| 7 | Frontend | 3 — Login front-office |

Les prompts Frontend commencent tous par « le Backend a terminé ce lot » et reprennent ses fichiers : ils ne peuvent pas être lancés avant.

**Le plan avant le code.** Exigez toujours un plan en quelques lignes et validez-le avant que l'assistant écrive quoi que ce soit. C'est le moment le moins cher pour corriger une mauvaise compréhension — et il y en a toujours une.

**Nommez les endroits où la spec s'écarte de l'habitude.** Les assistants ont un fort a priori sur « comment on fait une intégration Keycloak ». La spec les contredit sur plusieurs points, avec de bonnes raisons : rôles dans l'access token et non l'ID token, `preferred_username` comme clé et non `sub`, `azp` à vérifier à la main, pas de « lecture double » sur k8s. Si vous ne le rappelez pas dans le prompt, l'assistant reviendra à son réflexe.

**Interdisez de deviner l'API des librairies.** `jumbojett/openid-connect-php`, `aws/aws-sdk-php`, `robmorgan/phinx`, `monolog/monolog` : l'assistant en connaît des versions anciennes. Exigez qu'il lise `vendor/` à la version épinglée.

**Rappelez ce qui ne va jamais dans un log** (§5.9, L5). Un assistant journalise volontiers « pour aider au debug » — l'exception avec le token dedans, l'URL présignée complète. Ces logs partent vers Graylog, consulté par des gens sans accès à l'application.

**Faites-lui dire ce qu'il n'a pas fait.** Terminer chaque lot par « quels points de la recette (§9) dois-je vérifier à la main ? » évite l'illusion d'un travail fini.

**Si l'outil n'a pas accès au dépôt** (ChatGPT web, par exemple) : collez les sections de la spec listées dans le prompt, puis les fichiers concernés. Le reste du prompt est inchangé.

---

## Gabarit

```
Tu travailles sur le dépôt <ULTRACK-Backend | ULTRACK-Frontend> : PHP 8 procédural,
sans framework, Composer.

CONTEXTE
Le fichier SPEC-KEYCLOAK-MINIO.md à la racine est la spécification de référence.
Lis-le INTÉGRALEMENT avant toute action. Ne te fie pas à ce qu'une intégration
<Keycloak | MinIO | k8s> "fait d'habitude" : la spec s'en écarte volontairement
à plusieurs endroits et explique pourquoi à chaque fois. En cas de doute, c'est
elle qui a raison.

TÂCHE
Implémenter le <Lot N — Titre> (§8) :
  - <fichier> : <ce qui change, une ligne>
  - …
Sections à appliquer : <liste>.
HORS PÉRIMÈTRE : <ce qu'il ne doit pas toucher>.

CONTRAINTES
1. Ne modifie que les fichiers du tableau <§x.y> pour ce lot. Si un autre
   fichier doit changer, dis-le et attends ma réponse.
2. <exigences non négociables du lot>
3. <rappels des points où la spec s'écarte de l'habitude>
4. Utilise <librairie> à la version épinglée dans composer.lock. Ne devine pas
   son API : lis le code dans vendor/ avant d'appeler une méthode.
5. Toute configuration vient des variables d'environnement de <§>. N'en invente
   aucune autre. Aucune valeur par défaut pour un secret.
6. Si une décision dépend d'une question ouverte (§10), arrête-toi et demande.
   Ne suppose pas.

MÉTHODE
1. D'abord un PLAN : fichiers à créer/modifier, et pour chacun ce qui change,
   en 15 lignes maximum. Attends ma validation avant d'écrire du code.
2. Puis un fichier à la fois. Après chaque fichier, dis quels points de la
   recette <§9.x> il couvre.
3. Termine par : les points de <§9.x> que je dois vérifier à la main, et ce
   dont j'ai besoin pour le faire.
```

---

## Lot 0 — Prérequis (Backend, puis Frontend)

```
Tu travailles sur le dépôt ULTRACK-Backend : PHP 8 procédural, sans framework, Composer.

CONTEXTE
Le fichier SPEC-KEYCLOAK-MINIO.md à la racine est la spécification de référence.
Lis-le INTÉGRALEMENT avant toute action.

TÂCHE
Implémenter la partie code du Lot 0 — Prérequis (§8), c'est-à-dire rendre
l'application déployable sur Kubernetes avec une base vide :
  - config.php : lecture stricte de l'environnement (§5.3), enregistrement du
    handler de session AVANT session_start() (§5.2), DSN en utf8mb4
  - session_db.php : handler implémentant SessionHandlerInterface ET
    SessionUpdateTimestampHandlerInterface (§5.2 — les deux, c'est indispensable)
  - phinx.php + db/migrations/ : les migrations …0001 (schéma initial users,
    agents), …0002 (user_ref, keycloak_sub, email — password CONSERVÉ), …0003
    (sessions). PAS …0005. (§5.7)
  - db/with-lock.php : GET_LOCK autour de l'appel à Phinx (§5.7)
  - docker-entrypoint.sh, Dockerfile (deux étapes), .dockerignore (§5.4, §5.7)
  - healthz.php : base uniquement, ni Keycloak ni MinIO (§5.5)
  - logger.php + SafeGelfHandler.php : logger Monolog unique vers php://stderr en
    JSON, toujours actif ; handler Graylog GELF/UDP AJOUTÉ par pushHandler, activé
    par GRAYLOG_ENABLED, désactivé par défaut ; Monolog\ErrorHandler::register()
    pour router les erreurs PHP (§5.9)
  - docker-compose.yml, .env.example (§5.8)
  - .gitignore ; retrait de .ftpquota, tmp/restart.txt, error_log, vendor/ (§5.10)
Sections à appliquer : §5.2 à §5.5, §5.7 à §5.10, §6.
HORS PÉRIMÈTRE : Keycloak (§3), MinIO (§4). L'authentification par mot de passe
reste en place et doit continuer à fonctionner à l'identique.

CONTRAINTES
1. Ne modifie que les fichiers du tableau §5.9.
2. Le schéma de …0001 doit être extrait de la base réelle (je te fournirai la
   sortie de mysqldump --no-data), nettoyé : plus de AUTO_INCREMENT=…, charset
   utf8mb4_unicode_ci. Ne l'invente pas à partir du code.
3. Le handler de session : lazy_write ne fonctionne que si updateTimestamp()
   existe. Réutilise le PDO de config.php, pas de seconde connexion.
4. Migration à l'entrypoint, SOUS VERROU, même si on part sur un seul pod.
   Pas de Job k8s (c'est l'alternative écartée, §5.7).
5. Utilise robmorgan/phinx à la version épinglée. Lis vendor/ pour la DSL.
6. Toute configuration vient des variables d'environnement de §3.3, §4.3, §5.3,
   §5.9. config.php échoue explicitement au démarrage si l'une manque.
7. Logger — contraintes L1 à L5 de §5.9, non négociables :
   - Graylog est AJOUTÉ (pushHandler), jamais setHandlers. stderr fonctionne à
     l'identique avec ou sans.
   - Toute erreur Graylog (hôte injoignable, config invalide, exception du
     transport) est avalée : try/catch à la construction ET SafeGelfHandler qui
     surcharge write() pour attraper tout Throwable. Rien ne doit pouvoir
     empêcher le démarrage ni casser une requête.
   - UDP uniquement (Gelf\Transport\UdpTransport), jamais TCP/HTTP.
   - N'utilise PAS Monolog\Processor\WebProcessor : il journalise la query
     string, donc le code OIDC de callback.php. Un processeur maison avec ip,
     méthode, chemin sans query string, user_ref.
   - Rien de secret dans un log, jamais : tokens, code=, mots de passe, URLs
     présignées, numéros de CNI, $_SESSION brute.
8. Utilise monolog/monolog ^3 et graylog2/gelf-php ^2 aux versions épinglées.
   Lis vendor/ : la signature de GelfHandler::write() a changé entre Monolog 2 et 3.
9. Si une décision dépend de Q12, Q13, Q14 ou Q18 (§10), arrête-toi et demande.
   Pour Q18 (hôte Graylog), GRAYLOG_ENABLED=false suffit à avancer.

MÉTHODE
1. Plan en 15 lignes, attends ma validation.
2. Un fichier à la fois. Après chaque fichier, quels points de §9.3 il couvre.
3. Termine par la liste de ce que je dois vérifier à la main (§9.3) — dont
   "GRAYLOG_HOST injoignable → aucune erreur visible" et "grep des logs : zéro
   token" — et par la commande exacte pour démarrer en local avec docker compose.
```

**Puis, sur le Frontend — une fois le Backend committé :**

```
Tu travailles sur le dépôt ULTRACK-Frontend : PHP 8 procédural, sans framework, Composer.
Le dépôt ULTRACK-Backend (chemin : ../ULTRACK-Backend) a terminé son Lot 0 : il
démarre sur k8s avec migrations Phinx, sessions en base et sondes.

CONTEXTE
Le fichier SPEC-KEYCLOAK-MINIO.md à la racine est la spécification de référence.
Lis-le INTÉGRALEMENT avant toute action.

TÂCHE
Implémenter le Lot 0 — Prérequis (§8) sur le Frontend, en REPRENANT le socle du
Backend, pas en le réinventant :
  - Copie À L'IDENTIQUE depuis ../ULTRACK-Backend : config.php, session_db.php,
    logger.php, SafeGelfHandler.php, phinx.php, db/ (migrations, seeds,
    with-lock.php), Dockerfile, .dockerignore, docker-entrypoint.sh, healthz.php,
    docker-compose.yml, .env.example, .gitignore
  - Adapte UNIQUEMENT : session.name = ULTRACK_FO_SESS (§5.2) ; dans .env.example,
    les valeurs propres au Frontend (§3.3 : OIDC_CLIENT_ID=ultrack-frontoffice,
    OIDC_REDIRECT_URI du Frontend ; §5.9 : APP_NAME=ultrack-frontoffice) ; le nom
    de l'image dans docker-compose.yml
  - Retire du dépôt : .ftpquota, tmp/restart.txt, error_log, vendor/
  - Les dossiers d'upload locaux (uploads/, cni_pictures/, …) ne sont PAS touchés :
    ils restent jusqu'au lot R
Sections : §5.2 à §5.5, §5.7 à §5.10.
HORS PÉRIMÈTRE : Keycloak (§3), MinIO (§4). L'authentification par mot de passe
reste en place et doit fonctionner à l'identique.

CONTRAINTES
1. Copie, ne réécris pas. Pour chaque fichier repris, montre-moi le diff avec la
   version Backend : il doit être vide ou se limiter aux adaptations listées.
   Toute autre différence est un bug.
2. Les migrations sont LES MÊMES fichiers que côté Backend : les deux applications
   partagent une seule base et une seule table phinxlog (§5.7). Un fichier de
   migration présent dans un seul dépôt est une erreur.
3. Ne modifie ni home.php, ni save.php, ni statistiques.php, ni index.php dans ce
   lot — sauf si la façon d'inclure config.php change de forme.
4. Si une décision dépend de Q12, Q13 ou Q14 (§10), arrête-toi et demande.

MÉTHODE
1. Plan en 10 lignes, attends ma validation.
2. Fichier par fichier, avec le diff Backend/Frontend à chaque fois.
3. Termine par ce que je dois vérifier à la main (§9.3) et la commande docker
   compose pour démarrer.
```

---

## Lot 1 — MinIO (Backend, puis Frontend)

```
Tu travailles sur le dépôt ULTRACK-Backend : PHP 8 procédural, sans framework, Composer.

CONTEXTE
Le fichier SPEC-KEYCLOAK-MINIO.md à la racine est la spécification de référence.
Lis-le INTÉGRALEMENT avant toute action. Ne te fie pas à ce qu'une intégration
S3 "fait d'habitude" : la spec s'en écarte et explique pourquoi.

TÂCHE
Implémenter le Lot 1 — MinIO (§8) :
  - storage.php : storage_put(), storage_get(), storage_delete(),
    storage_presign(), storage_exists() — DEUX clients S3, un pour le serveur
    (S3_ENDPOINT), un pour la présignature (S3_PUBLIC_ENDPOINT) (§4.1, §4.3, §4.4)
  - save.php : validation MIME réelle (finfo), compression GD vers fichier
    temporaire, putObject avec clé UUID, INSERT de la CLÉ ; objet d'abord,
    base ensuite (§4.5)
  - dashboard.php : base64 pour dompdf via getObject (§4.5) ; deleteObject
    après le DELETE (§4.6) ; $getValidFileUrl() → URL présignée (§4.5)
  - droits.php : le bloc d'upload des CNI s'aligne sur save.php (compression +
    putObject) ; les liens → URLs présignées (§4.4, §4.5)
  - bin/migrate-files-to-minio.php : réécriture des chemins, idempotent,
    --dry-run obligatoire (§4.8)
  - Journalisation via le logger de §5.9 : putObject / deleteObject (clé, agents.id,
    user_ref), garde-fou "valeur sans /" (warning), MinIO injoignable (error).
    Jamais d'URL présignée ni de clé d'accès dans un log.
Sections à appliquer : §4.1 à §4.10 ; tableau §4.10 lignes "Les deux" et "Backend" ;
tableau des événements §5.9, lignes "Lot 1".
HORS PÉRIMÈTRE : Keycloak (§3), le bloc de CRÉATION d'utilisateur de droits.php
(username/password — reste tel quel pour ce lot).

CONTRAINTES
1. Ne modifie que les fichiers du tableau §4.10.
2. La signature d'une URL présignée couvre l'en-tête Host : la présignature
   utilise OBLIGATOIREMENT le client configuré sur S3_PUBLIC_ENDPOINT (§4.1).
3. Stocke la CLÉ de l'objet en base, jamais une URL (§4.7).
4. PAS de "lecture double" avec repli disque : les pods n'ont pas les anciens
   dossiers. Une valeur sans "/" en base est une anomalie → journaliser,
   afficher un visuel "indisponible", ne JAMAIS construire d'URL avec (§4.7).
5. Nommage des objets : UUID v4, jamais le login (§4.2).
6. Utilise aws/aws-sdk-php ^3 à la version épinglée ; use_path_style_endpoint
   = true. Lis vendor/ pour createPresignedRequest.
7. Variables d'environnement : uniquement celles de §4.3.
8. Si une décision dépend de Q8 ou Q10 (§10), arrête-toi et demande.

MÉTHODE
1. Plan en 15 lignes, attends ma validation.
2. Un fichier à la fois. Après chaque fichier, quels points de §9.2 il couvre.
3. Termine par ce que je dois vérifier à la main (§9.2), dont le test
   "URL présignée expirée → 403" et "accès direct au bucket → 403".
```

**Puis, sur le Frontend — une fois le Backend committé et recetté :**

```
Tu travailles sur le dépôt ULTRACK-Frontend : PHP 8 procédural, sans framework, Composer.
Le dépôt ULTRACK-Backend (chemin : ../ULTRACK-Backend) a terminé son Lot 1 :
storage.php existe et est recetté.

CONTEXTE
Le fichier SPEC-KEYCLOAK-MINIO.md à la racine est la spécification de référence.
Lis-le INTÉGRALEMENT avant toute action.

TÂCHE
Implémenter la partie Frontend du Lot 1 — MinIO (§8) :
  - storage.php : reprends À L'IDENTIQUE ../ULTRACK-Backend/storage.php. Ne le
    réécris pas, ne l'"améliore" pas. Si tu penses qu'il doit changer, dis-le :
    la modification se fait côté Backend puis se recopie.
  - save.php : validation MIME réelle (finfo), compression GD vers fichier
    temporaire, putObject avec clé UUID via storage_put(), INSERT de la CLÉ ;
    objet d'abord, base ensuite (§4.5) ; journalise putObject et "MinIO
    injoignable" via le logger de §5.9 — jamais d'URL présignée dans un log
Sections à appliquer : §4.2, §4.3, §4.5, §4.7 ; tableau §4.10, lignes "Les deux" ;
tableau des événements §5.9, lignes "Lot 1".
HORS PÉRIMÈTRE : tout le reste. Le Frontend n'affiche ni ne supprime jamais de
fichier : pas de présignature, pas de deleteObject, pas de dashboard.

CONTRAINTES
1. Ne modifie que save.php ; storage.php est une copie.
2. Nommage des objets : UUID v4, jamais le login (§4.2). Le nommage actuel
   prof_{login}_{timestamp}.jpg disparaît.
3. Stocke la CLÉ de l'objet en base (agents.photo_path, cni_front, cni_back),
   jamais une URL (§4.7).
4. Objet d'abord, base ensuite. Si putObject échoue : aucune ligne en base, et un
   message explicite à l'utilisateur (§4.5 ; recette §9.2 "MinIO injoignable").
5. Variables d'environnement : uniquement celles de §4.3 — identiques à celles du
   Backend. Le bucket de développement est ultrack-dev.
6. Si une décision dépend de Q10 (§10), arrête-toi et demande.

MÉTHODE
1. Plan en 10 lignes, attends ma validation.
2. save.php, puis les points de §9.2 couverts.
3. Termine par ce que je dois vérifier à la main — dont un enrôlement complet
   depuis home.php : 3 objets dans ultrack-dev, clés UUID, 3 clés en base, puis
   affichage de cet agent dans le dashboard du Backend. C'est la recette de bout
   en bout du lot 1 : elle n'est possible qu'avec les deux dépôts terminés.
```

---

## Lot 2 — Keycloak Back-office (Backend uniquement)

```
Tu travailles sur le dépôt ULTRACK-Backend : PHP 8 procédural, sans framework, Composer.

CONTEXTE
Le fichier SPEC-KEYCLOAK-MINIO.md à la racine est la spécification de référence.
Lis-le INTÉGRALEMENT avant toute action. Ne te fie pas à ce qu'une intégration
Keycloak "fait d'habitude" : la spec s'en écarte volontairement à plusieurs endroits
et explique pourquoi à chaque fois. En cas de doute, c'est elle qui a raison.

TÂCHE
Implémenter le Lot 2 — Keycloak Back-office (§8) :
  - auth.php : require_auth(), rafraîchissement du token, découverte OIDC (§3.6)
  - callback.php : échange du code, validation des DEUX tokens, JIT, session
    (§3.4 — un squelette est fourni, pars de lui)
  - index.php : suppression du formulaire et de password_verify, redirection OIDC
  - dashboard.php et save.php : remplacer la garde par require_auth([...]) ;
    contrôle ADMIN explicite sur la suppression d'agent (§3.6)
  - logout_bo.php : déconnexion RP-initiated (§3.7)
  - config.php : les variables de §3.3 s'ajoutent à la lecture stricte existante
  - Journalisation via le logger de §5.9 : connexion réussie (user_ref, client_id,
    roles), 403 rôle absent (warning), échec de validation d'un token (warning,
    motif, ip), déconnexion. JAMAIS un token, un code=, un nonce dans un log.
Sections à appliquer : §3.1 à §3.7, §3.9, §3.11, §3.12 ; tableau §3.13, lignes
"Les deux" et "Backend" ; tableau des événements §5.9, lignes "Lot 2, 3".
HORS PÉRIMÈTRE : §3.8 (provisioning), §3.10 (reprise), le volet B (§4), le
Frontend. Ne touche pas à droits.php dans ce lot.

CONTRAINTES
1. Ne modifie que les fichiers du tableau §3.13 pour ce lot. Si un autre fichier
   doit changer, dis-le et attends ma réponse.
2. Les exigences §3.11 (A1 à A8) sont non négociables. À la fin, indique pour
   chacune OÙ elle est satisfaite dans le code.
3. Les rôles se lisent dans l'ACCESS token : resource_access.{client_id}.roles.
   Jamais realm_access, jamais l'ID token. Vérifie azp toi-même : la librairie
   ne le fait pas (§3.1, §3.4).
4. La clé de rapprochement est user_ref = preferred_username, pas sub (§3.5).
   $_SESSION['user_id'] reste l'entier users.id.
5. Utilise jumbojett/openid-connect-php à la version épinglée dans composer.lock.
   Ne devine pas son API : lis le code dans vendor/ avant d'appeler une méthode.
6. Toute configuration vient des variables d'environnement de §3.3. N'en invente
   aucune autre. Aucune valeur par défaut pour un secret.
7. Si une décision dépend d'une question ouverte (§10), arrête-toi et demande.
   Ne suppose pas.
8. dashboard.php fait 700 lignes : seule la garde en tête et le contrôle sur la
   suppression changent. Ne le réécris pas.

MÉTHODE
1. D'abord un PLAN : fichiers à créer/modifier, et pour chacun ce qui change,
   en 15 lignes maximum. Attends ma validation avant d'écrire du code.
2. Puis un fichier à la fois. Après chaque fichier, dis quels points de la
   recette §9.1 il couvre.
3. Termine par : les points de §9.1 que je dois vérifier à la main, et ce dont
   j'ai besoin pour le faire (secrets, redirect_uri, compte de test).
```

---

## Lot 3 — Keycloak Front-office (Backend pour le provisioning, Frontend pour le login)

**Backend — provisioning :**

```
Tu travailles sur le dépôt ULTRACK-Backend : PHP 8 procédural, sans framework, Composer.
Le Lot 2 est terminé : auth.php, callback.php et config.php existent et fonctionnent.

CONTEXTE
Le fichier SPEC-KEYCLOAK-MINIO.md à la racine est la spécification de référence.
Lis-le INTÉGRALEMENT avant toute action.

TÂCHE
Implémenter la partie Backend du Lot 3 — Keycloak Front-office (§8) :
  - droits.php, bloc de création : appel à l'Admin API dans l'ordre exact de
    §3.8 (token de service mis en cache → POST users SANS credentials → rôle
    DE CLIENT RECRUTEUR → execute-actions-email → CNI → INSERT local) ;
    SUPPRESSION du champ mot de passe, ajout du champ e-mail obligatoire ;
    bouton "Renvoyer l'e-mail" dans la liste ; compensation (DELETE Keycloak)
    si l'INSERT local échoue
  - droits.php, désactivation : PUT enabled=false + DELETE FROM sessions
    WHERE user_ref = ? (§3.8, §5.2)
  - bin/migrate-users-to-keycloak.php : script de reprise, idempotent,
    --dry-run, --only <username> (§3.10)
  - Journalisation via le logger de §5.9 : recruteur créé / e-mail envoyé /
    renvoyé / désactivé (info : user_ref du recruteur, keycloak_sub, user_ref de
    l'admin), compensation exécutée (error, cause). Jamais l'e-mail en clair.
Sections à appliquer : §3.8, §3.10, §3.11 ; tableau §3.13 lignes droits.php et
bin/ ; tableau des événements §5.9, lignes "Lot 3".
HORS PÉRIMÈTRE : le Frontend ; les gardes et le login (déjà faits au Lot 2).

CONTRAINTES
1. Ne modifie que droits.php et crée bin/migrate-users-to-keycloak.php.
2. Aucun mot de passe n'est généré, affiché ni transmis par l'application :
   c'est execute-actions-email qui fait tout (§3.8). Si tu te retrouves à
   écrire un mot de passe temporaire, c'est que tu t'écartes de la spec.
3. Le rôle est un rôle DE CLIENT : role-mappings/clients/{clientUuid}, pas
   role-mappings/realm (§3.8, étape 3). Le clientUuid s'obtient par l'Admin
   API, ce n'est pas le client_id.
4. Username Keycloak des recruteurs : rec-<…> (§3.5, §3.8).
5. La compensation est obligatoire : si l'étape 6 échoue après création côté
   Keycloak, supprime l'utilisateur Keycloak. Montre-moi où.
6. Variables : KC_ADMIN_CLIENT_ID / KC_ADMIN_CLIENT_SECRET (§3.3). Si Q15 n'est
   pas tranchée (3e client ou service account sur ultrack-backoffice ;
   manage-users ou fine-grained), arrête-toi et demande.
7. Ne devine pas les chemins de l'Admin API : ils sont dans §3.2 et §3.8, avec
   le segment /auth/.

MÉTHODE
1. Plan en 15 lignes, attends ma validation.
2. Un fichier à la fois. Après chaque fichier, quels points de §9.1 il couvre.
3. Termine par ce que je dois vérifier à la main — dont "création → e-mail
   reçu", "lien réutilisé → refusé", "échec INSERT → utilisateur Keycloak
   supprimé".
```

**Frontend — login :**

```
Tu travailles sur le dépôt ULTRACK-Frontend : PHP 8 procédural, sans framework, Composer.
Le dépôt ULTRACK-Backend (chemin : ../ULTRACK-Backend) a terminé son Lot 2.

CONTEXTE
Le fichier SPEC-KEYCLOAK-MINIO.md à la racine est la spécification de référence.
Lis-le INTÉGRALEMENT avant toute action.

TÂCHE
Implémenter la partie Frontend du Lot 3 (§8) :
  - Reprends À L'IDENTIQUE auth.php et callback.php depuis ../ULTRACK-Backend.
    Ne les réécris pas. Seule différence : le rôle attendu est RECRUTEUR et la
    page d'accueil est home.php.
  - index.php : suppression du formulaire et de password_verify, redirection OIDC
  - home.php, save.php, statistiques.php : la garde devient require_auth(['RECRUTEUR'])
  - logout.php : déconnexion RP-initiated (§3.7)
  - config.php : variables §3.3 côté Frontend (OIDC_CLIENT_ID=ultrack-frontoffice)
  - Journalisation : les mêmes événements qu'au Lot 2 (connexion, 403, échec de
    token, déconnexion), via le logger repris du Backend — §5.9
Sections : §3.3 à §3.7 ; tableau §3.13 lignes "Frontend" ; tableau §5.9 lignes "Lot 2, 3".
HORS PÉRIMÈTRE : droits.php, provisioning, volet B.

CONTRAINTES
1. Ne modifie que les fichiers du tableau §3.13 lignes "Frontend".
2. auth.php et callback.php doivent rester identiques à ceux du Backend, à
   l'exception des constantes de rôle et de page d'accueil. Si tu as besoin de
   diverger, dis-le : c'est un signal que le socle commun (§7) est mal découpé.
3. Les autres contraintes du Lot 2 s'appliquent (§3.11, rôles dans l'access
   token, user_ref = preferred_username, pas d'API devinée).

MÉTHODE
1. Plan en 10 lignes, attends ma validation.
2. Un fichier à la fois, points de §9.1 couverts.
3. Termine par ce que je dois vérifier à la main.
```

---

## Ce que le prompt ne remplace pas

- **La relecture du diff.** Un assistant qui a « suivi la spec » l'a interprétée. Relisez `callback.php` ligne à ligne contre §3.4 et §3.11 avant de le déployer, même en recette.
- **Les secrets et la configuration Keycloak.** Aucun prompt ne fera fonctionner le login si les `redirect_uri` ne sont pas déclarés sur les clients ou si les secrets ne sont pas dans l'environnement (Q4).
- **Les questions ouvertes.** Si l'assistant vous demande de trancher Q15 ou Q16, c'est le comportement attendu. Ne le laissez pas supposer.
