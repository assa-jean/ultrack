<?php
// new/app/Controllers/AuthController.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (!class_exists('UltrackOpenIDConnectClient')) {
    class UltrackOpenIDConnectClient extends Jumbojett\OpenIDConnectClient
    {
        private bool $forceJwksRefresh = false;

        protected function fetchURL(string $url, ?string $post_body = null, array $headers = []): string
        {
            $path = (string) parse_url($url, PHP_URL_PATH);
            $cacheable = $post_body === null
                && (str_ends_with($path, '/.well-known/openid-configuration') || str_ends_with($path, '/certs'));
            if (!$cacheable) {
                return parent::fetchURL($url, $post_body, $headers);
            }

            $cachePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ultrack_oidc_' . hash('sha256', $url) . '.json';
            if (!$this->forceJwksRefresh && is_file($cachePath) && time() - (int) filemtime($cachePath) < 86400) {
                $cached = file_get_contents($cachePath);
                if (is_string($cached) && $cached !== '') {
                    return $cached;
                }
            }

            $response = parent::fetchURL($url, $post_body, $headers);
            if (json_decode($response) !== null) {
                $temporaryPath = tempnam(sys_get_temp_dir(), 'ultrack_oidc_');
                if ($temporaryPath !== false) {
                    if (file_put_contents($temporaryPath, $response, LOCK_EX) !== false) {
                        @unlink($cachePath);
                        @rename($temporaryPath, $cachePath);
                    } else {
                        @unlink($temporaryPath);
                    }
                }
            }

            return $response;
        }

        public function verifyJWTSignature(string $jwt): bool
        {
            try {
                return parent::verifyJWTSignature($jwt);
            } catch (Throwable $exception) {
                $jwksUri = $this->getProviderConfigValue('jwks_uri');
                if (is_string($jwksUri) && $jwksUri !== '') {
                    @unlink(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ultrack_oidc_' . hash('sha256', $jwksUri) . '.json');
                }

                $this->forceJwksRefresh = true;
                try {
                    return parent::verifyJWTSignature($jwt);
                } finally {
                    $this->forceJwksRefresh = false;
                }
            }
        }

        public function getLogoutEndpoint(): string
        {
            return (string) $this->getProviderConfigValue('end_session_endpoint', '');
        }
    }
}

function oidcClientId(): string
{
    return trim((string) ultrack_env('OIDC_BACK_CLIENT_ID', ultrack_env('OIDC_CLIENT_ID', '')));
}

function requireAuth(array $requiredRoles = ['ADMIN', 'SUPERVISEUR']): void
{
    $hasOidcSession = !empty($_SESSION['access_token']);
    if (!isset($_SESSION['user_id'])
        || ($hasOidcSession && empty($_SESSION['user_ref']))
        || (isset($_SESSION['expires_at']) && !ultrack_refresh_session())) {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php?route=login');
        exit;
    }

    $sessionRoles = $_SESSION['roles'] ?? [];
    if (!is_array($sessionRoles) || $sessionRoles === []) {
        $sessionRoles = [$_SESSION['role'] ?? ''];
    }
    $sessionRoles = array_map(static fn($role): string => strtoupper((string) $role), $sessionRoles);
    $requiredRoles = array_map(static fn($role): string => strtoupper((string) $role), $requiredRoles);

    if ($requiredRoles !== [] && array_intersect($requiredRoles, $sessionRoles) === []) {
        http_response_code(403);
        echo 'Accès refusé : rôle insuffisant.';
        exit;
    }
}

function oidcEnabled(): bool
{
    return !empty(ultrack_env('KEYCLOAK_BASE_URL', ''))
        && !empty(oidcClientId())
        && !empty(ultrack_env('OIDC_CLIENT_SECRET', ''))
        && !empty(ultrack_env('OIDC_REDIRECT_URI', ''));
}

function oidcBuildClient(): UltrackOpenIDConnectClient
{
    $providerUrl = rtrim(ultrack_env('KEYCLOAK_BASE_URL', ''), '/') . '/realms/' . ultrack_env('KEYCLOAK_REALM', 'digital-app');
    $issuer = $providerUrl;
    $client = new UltrackOpenIDConnectClient(
        $providerUrl,
        oidcClientId(),
        ultrack_env('OIDC_CLIENT_SECRET', ''),
        $issuer
    );
    $client->setVerifyPeer(true);
    $client->setVerifyHost(true);
    $client->setRedirectURL(ultrack_env('OIDC_REDIRECT_URI', ''));
    $client->addScope(['openid', 'profile', 'email']);
    $client->setCodeChallengeMethod('S256');

    return $client;
}

function oidcRoleFromAccessToken(): string
{
    $access = null;
    if (isset($_SESSION['oidc_access_token_payload'])) {
        $access = $_SESSION['oidc_access_token_payload'];
    }

    if (!$access) {
        return '';
    }

    $clientId = oidcClientId();
    $resourceAccess = $access->resource_access ?? null;
    if (is_object($resourceAccess) && isset($resourceAccess->$clientId->roles)) {
        $roles = array_map('strtoupper', array_map('strval', (array) $resourceAccess->$clientId->roles));
        foreach (['ADMIN', 'SUPERVISEUR', 'RECRUTEUR'] as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }
    }

    return '';
}

function ultrack_expected_issuer(): string
{
    return rtrim((string) ultrack_env('KEYCLOAK_BASE_URL', ''), '/')
        . '/realms/' . rawurlencode((string) ultrack_env('KEYCLOAK_REALM', 'digital-app'));
}

function ultrack_verified_access_claims(UltrackOpenIDConnectClient $client, string $accessToken): object
{
    if ($accessToken === '' || !$client->verifyJWTSignature($accessToken)) {
        throw new RuntimeException('Access token signature verification failed.');
    }

    $claims = $client->getAccessTokenPayload();
    if (!is_object($claims)
        || !isset($claims->iss, $claims->exp)
        || !is_numeric($claims->exp)
        || $claims->iss !== ultrack_expected_issuer()
        || (int) $claims->exp <= time()
        || (isset($claims->nbf) && (!is_numeric($claims->nbf) || (int) $claims->nbf > time()))) {
        throw new RuntimeException('Access token claims are invalid.');
    }

    return $claims;
}

function ultrack_jit_upsert_user(PDO $db, string $userRef, string $subject, ?string $email, string $role): int
{
    if ($userRef === '' || strlen($userRef) > 64 || $subject === '' || strlen($subject) > 36) {
        throw new RuntimeException('Required identity claims are missing.');
    }

    $columnRows = $db->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
    $columns = [];
    foreach ($columnRows as $column) {
        $columns[$column['Field']] = $column;
    }
    foreach (['id', 'username', 'user_ref', 'keycloak_sub', 'email'] as $requiredColumn) {
        if (!isset($columns[$requiredColumn])) {
            throw new RuntimeException('The users table is missing a required Keycloak column.');
        }
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE user_ref = ? LIMIT 1');
    $stmt->execute([$userRef]);
    $existingId = $stmt->fetchColumn();
    $now = date('Y-m-d H:i:s');

    if ($existingId !== false) {
        $updates = [
            'username' => $userRef,
            'keycloak_sub' => $subject,
            'email' => $email,
        ];
        if (isset($columns['role'])) {
            $updates['role'] = $role;
        }

        $set = [];
        $values = [];
        foreach ($updates as $column => $value) {
            if (isset($columns[$column])) {
                $set[] = '`' . $column . '` = ?';
                $values[] = $value;
            }
        }
        if ($set !== []) {
            $values[] = (int) $existingId;
            $db->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($values);
        }

        return (int) $existingId;
    }

    $valuesByColumn = [
        'username' => $userRef,
        'user_ref' => $userRef,
        'keycloak_sub' => $subject,
        'email' => $email,
        'password' => '',
        'role' => $role,
        'flotte' => '',
        'enseigne' => '',
        'telephone' => 0,
        'cni_recto' => '',
        'cni_verso' => '',
        'created_at' => $now,
    ];
    $insertColumns = [];
    $insertValues = [];
    foreach ($columns as $name => $definition) {
        if (array_key_exists($name, $valuesByColumn)) {
            $insertColumns[] = '`' . $name . '`';
            $insertValues[] = $valuesByColumn[$name];
            continue;
        }

        if ($name !== 'id' && $definition['Null'] === 'NO' && $definition['Default'] === null
            && stripos((string) $definition['Extra'], 'auto_increment') === false) {
            throw new RuntimeException('The users table has an unsupported required column for Keycloak JIT.');
        }
    }

    $placeholders = implode(', ', array_fill(0, count($insertValues), '?'));
    try {
        $insert = $db->prepare('INSERT INTO users (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')');
        $insert->execute($insertValues);
        return (int) $db->lastInsertId();
    } catch (PDOException $exception) {
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }

        $stmt->execute([$userRef]);
        $racedId = $stmt->fetchColumn();
        if ($racedId === false) {
            throw $exception;
        }
        return ultrack_jit_upsert_user($db, $userRef, $subject, $email, $role);
    }
}

function ultrack_refresh_session(): bool
{
    $expiresAt = (int) ($_SESSION['expires_at'] ?? 0);
    if ($expiresAt > time() + 30) {
        return true;
    }

    $refreshToken = (string) ($_SESSION['refresh_token'] ?? '');
    if ($refreshToken === '' || !oidcEnabled()) {
        return false;
    }

    try {
        $client = oidcBuildClient();
        $response = $client->refreshToken($refreshToken);
        if (!is_object($response) || empty($response->access_token)) {
            return false;
        }
        $accessClaims = ultrack_verified_access_claims($client, (string) $response->access_token);
        $tokenRoles = $accessClaims->resource_access->{oidcClientId()}->roles ?? [];
        $_SESSION['roles'] = is_array($tokenRoles) ? array_values(array_map('strval', $tokenRoles)) : [];
        $role = '';
        foreach (['ADMIN', 'SUPERVISEUR'] as $candidate) {
            if (in_array($candidate, array_map('strtoupper', $_SESSION['roles']), true)) {
                $role = $candidate;
                break;
            }
        }
        if ($role === '') {
            return false;
        }
        $_SESSION['role'] = $role;
        $_SESSION['access_token'] = (string) $response->access_token;
        $_SESSION['oidc_access_token_payload'] = $accessClaims;
        $_SESSION['expires_at'] = (int) $accessClaims->exp;
        if (!empty($response->refresh_token)) {
            $_SESSION['refresh_token'] = (string) $response->refresh_token;
        }
        if (!empty($response->id_token)) {
            $_SESSION['id_token'] = (string) $response->id_token;
            $_SESSION['oidc_id_token'] = (string) $response->id_token;
        }

        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

// 1. Afficher le formulaire de connexion
function showLoginForm() {
    // Si l'utilisateur est déjà connecté, on le redirige selon son rôle
    if (isset($_SESSION['user_id'])) {
        redirectByRole();
    }
    require_once __DIR__ . '/../Views/auth/login.php';
}

function startOidcLogin() {
    if (!oidcEnabled()) {
        header('Location: index.php?route=login');
        exit;
    }

    try {
        $client = oidcBuildClient();
        $client->authenticate();
    } catch (Throwable $exception) {
        $_SESSION['oidc_error'] = 'La connexion Keycloak a échoué. Réessayez ou contactez l’administrateur.';
        header('Location: index.php?route=login');
        exit;
    }
}

function handleOidcCallback() {
    if (!oidcEnabled()) {
        header('Location: index.php?route=login');
        exit;
    }

    try {
        $client = oidcBuildClient();
        if (isset($_GET['code'])) {
            $expectedState = $_SESSION['openid_connect_state'] ?? null;
            $receivedState = $_GET['state'] ?? null;
            if (!is_string($expectedState) || !is_string($receivedState) || !hash_equals($expectedState, $receivedState)) {
                throw new RuntimeException('Invalid OIDC state.');
            }
        }
        $client->authenticate();

        $claims = $client->getVerifiedClaims();
        $currentClientId = oidcClientId();

        $aud = $claims->aud ?? null;
        $audList = is_array($aud) ? $aud : [$aud];
        if (!in_array($currentClientId, $audList, true)) {
            throw new RuntimeException('aud mismatch');
        }

        if (($claims->azp ?? null) !== $currentClientId) {
            throw new RuntimeException('azp mismatch');
        }

        $userRef = trim((string) ($claims->preferred_username ?? ''));
        $subject = trim((string) ($claims->sub ?? ''));
        if ($userRef === '' || $subject === '') {
            throw new RuntimeException('Required identity claims are missing.');
        }

        $accessToken = (string) $client->getAccessToken();
        $accessPayload = ultrack_verified_access_claims($client, $accessToken);
        $_SESSION['oidc_access_token_payload'] = $accessPayload;
        $role = oidcRoleFromAccessToken();
        if (!in_array($role, ['ADMIN', 'SUPERVISEUR'], true)) {
            http_response_code(403);
            exit('Accès refusé : rôle back-office manquant.');
        }

        global $db;
        $localUserId = ultrack_jit_upsert_user(
            $db,
            $userRef,
            $subject,
            isset($claims->email) ? (string) $claims->email : null,
            $role
        );
        if ($localUserId <= 0) {
            throw new RuntimeException('Local user provisioning failed.');
        }

        session_regenerate_id(true);
        $_SESSION = [
            'user_ref' => $userRef,
            'keycloak_sub' => $subject,
            'user_id' => $localUserId,
            'username' => $userRef,
            'nom_complet' => (string) ($claims->name ?? ''),
            'email' => isset($claims->email) ? (string) $claims->email : null,
            'roles' => array_values(array_map('strval', (array) ($accessPayload->resource_access->{$currentClientId}->roles ?? []))),
            'role' => $role,
            'id_token' => (string) $client->getIdToken(),
            'oidc_id_token' => (string) $client->getIdToken(),
            'access_token' => $accessToken,
            'refresh_token' => (string) ($client->getRefreshToken() ?? ''),
            'expires_at' => (int) $accessPayload->exp,
        ];
        redirectByRole();
    } catch (Throwable $exception) {
        $_SESSION['oidc_error'] = 'La validation de la connexion a échoué. Veuillez recommencer.';
        header('Location: index.php?route=login');
        exit;
    }
}

// 2. Traitement de la connexion
function login() {
    global $db;

    if (oidcEnabled()) {
        startOidcLogin();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!empty($username) && !empty($password)) {
            // Recherche de l'utilisateur en BD
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérification du mot de passe
            if ($user && ($password === $user['password'] || password_verify($password, $user['password']))) {
                // Stockage des informations en session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = strtoupper($user['role']);
                $_SESSION['roles'] = [strtoupper((string) $user['role'])];
                $_SESSION['flotte'] = $user['flotte'] ?? '';

                redirectByRole();
            } else {
                $error = "Nom d'utilisateur ou mot de passe incorrect.";
                require_once __DIR__ . '/../Views/auth/login.php';
            }
        } else {
            $error = "Veuillez remplir tous les champs.";
            require_once __DIR__ . '/../Views/auth/login.php';
        }
    }
}

// 3. Redirection automatique selon le Rôle
function redirectByRole() {
    $role = strtoupper((string) ($_SESSION['role'] ?? ''));

    if ($role === 'ADMIN' || $role === 'SUPERVISEUR') {
        header('Location: index.php?route=dashboard');
        exit;
    }

    if ($role === 'RECRUTEUR') {
        header('Location: index.php?route=front_register');
        exit;
    }

    header('Location: index.php?route=login');
    exit;
}

// 4. Déconnexion
function logout() {
    $redirectUri = (string) ultrack_env('APP_URL', 'http://localhost/recensementagentsocm/public');
    $idTokenHint = $_SESSION['id_token'] ?? $_SESSION['oidc_id_token'] ?? null;

    $_SESSION = [];
    session_destroy();

    if (oidcEnabled() && !empty($idTokenHint)) {
        try {
            $client = oidcBuildClient();
            $logoutEndpoint = $client->getLogoutEndpoint();
            if ($logoutEndpoint !== '') {
                $params = [
                    'post_logout_redirect_uri' => $redirectUri,
                    'id_token_hint' => $idTokenHint,
                    'client_id' => oidcClientId(),
                ];
                header('Location: ' . $logoutEndpoint . '?' . http_build_query($params));
                exit;
            }
        } catch (Throwable $exception) {
            // Fall through to local logout when discovery is unavailable.
        }
    }

    header('Location: index.php?route=login');
    exit;
}
?>