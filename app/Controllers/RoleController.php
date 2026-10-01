<?php
// new/app/Controllers/RoleController.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/storage.php';
require_once __DIR__ . '/AuthController.php';

function ultrack_keycloak_admin_base(): string
{
    return rtrim((string) ultrack_env('KEYCLOAK_BASE_URL', ''), '/')
        . '/admin/realms/' . rawurlencode((string) ultrack_env('KEYCLOAK_REALM', 'digital-app'));
}

function ultrack_keycloak_token_from_admin_client(): ?string
{
    static $token = null;
    static $tokenExpiresAt = 0;
    $cacheKey = 'ultrack.kc.admin.' . hash('sha256', implode('|', [
        (string) ultrack_env('KEYCLOAK_BASE_URL', ''),
        (string) ultrack_env('KEYCLOAK_REALM', ''),
        (string) ultrack_env('KC_ADMIN_CLIENT_ID', ''),
    ]));

    if ($token !== null && $tokenExpiresAt > time() + 30) {
        return $token;
    }
    if (function_exists('apcu_fetch') && function_exists('apcu_enabled') && apcu_enabled()) {
        $cached = apcu_fetch($cacheKey, $success);
        if ($success && is_array($cached) && ($cached['expires_at'] ?? 0) > time() + 30) {
            $token = (string) $cached['token'];
            $tokenExpiresAt = (int) $cached['expires_at'];
            return $token;
        }
    }

    $clientId = trim((string) ultrack_env('KC_ADMIN_CLIENT_ID', ''));
    $clientSecret = trim((string) ultrack_env('KC_ADMIN_CLIENT_SECRET', ''));
    if ($clientId === '' || $clientSecret === '') {
        return null;
    }

    $tokenUrl = rtrim((string) ultrack_env('KEYCLOAK_BASE_URL', ''), '/')
        . '/realms/' . rawurlencode((string) ultrack_env('KEYCLOAK_REALM', 'digital-app'))
        . '/protocol/openid-connect/token';

    $payload = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ]);

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400 || $curlError !== '') {
        return null;
    }

    $data = json_decode($response, true);
    $token = $data['access_token'] ?? null;
    $expiresIn = max(60, (int) ($data['expires_in'] ?? 60));
    if (!is_string($token) || $token === '') {
        return null;
    }

    $tokenExpiresAt = time() + $expiresIn;
    if (function_exists('apcu_store') && function_exists('apcu_enabled') && apcu_enabled()) {
        apcu_store($cacheKey, ['token' => $token, 'expires_at' => $tokenExpiresAt], $expiresIn);
    }

    return $token;
}

function ultrack_keycloak_admin_request(string $method, string $path, array $body = []): array
{
    $token = ultrack_keycloak_token_from_admin_client();
    if ($token === null) {
        return ['ok' => false, 'status' => 401, 'message' => 'Missing KC_ADMIN_CLIENT_ID or KC_ADMIN_CLIENT_SECRET'];
    }

    $url = ultrack_keycloak_admin_base() . $path;
    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ];

    if (!empty($body) || strtoupper($method) === 'PUT' || strtoupper($method) === 'POST') {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        return ['ok' => false, 'status' => 0, 'message' => 'Keycloak request failed.'];
    }

    $headerText = substr($response, 0, $headerSize);
    $bodyText = substr($response, $headerSize);

    $parsedHeaders = [];
    foreach (explode("\r\n", $headerText) as $rawLine) {
        if ($rawLine === '' || preg_match('/^HTTP\//', $rawLine) === 1) {
            continue;
        }
        $parts = explode(':', $rawLine, 2);
        if (count($parts) === 2) {
            $parsedHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
    }

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'status' => (int) $httpCode,
        'body' => $bodyText,
        'headers' => $parsedHeaders,
        'message' => $httpCode >= 400 ? 'Keycloak returned an error.' : ($parsedHeaders['location'] ?? ''),
    ];
}

function ultrack_keycloak_frontoffice_client_uuid(): ?string
{
    $clientId = (string) ultrack_env('OIDC_FRONT_CLIENT_ID', 'ultrack-frontoffice');
    $response = ultrack_keycloak_admin_request('GET', '/clients?clientId=' . rawurlencode($clientId));
    if (!$response['ok']) {
        return null;
    }

    $clients = json_decode($response['body'], true);
    if (!is_array($clients)) {
        return null;
    }

    foreach ($clients as $client) {
        if (($client['clientId'] ?? '') === $clientId) {
            return (string) ($client['id'] ?? '');
        }
    }

    return null;
}

function ultrack_keycloak_recruteur_role_id(string $clientUuid): ?string
{
    if ($clientUuid === '') {
        return null;
    }

    $response = ultrack_keycloak_admin_request('GET', '/clients/' . rawurlencode($clientUuid) . '/roles/RECRUTEUR');
    if (!$response['ok']) {
        return null;
    }

    $role = json_decode($response['body'], true);
    if (!is_array($role) || ($role['name'] ?? '') !== 'RECRUTEUR') {
        return null;
    }

    return (string) ($role['id'] ?? '');
}

function ultrack_keycloak_delete_user(string $userId): bool
{
    if ($userId === '') {
        return false;
    }

    $response = ultrack_keycloak_admin_request('DELETE', '/users/' . rawurlencode($userId));
    return $response['ok'];
}

function ultrack_keycloak_find_user(string $username): ?array
{
    $response = ultrack_keycloak_admin_request('GET', '/users?username=' . rawurlencode($username) . '&exact=true');
    if (!$response['ok']) {
        return null;
    }
    $users = json_decode($response['body'], true);
    if (!is_array($users)) {
        return null;
    }
    foreach ($users as $user) {
        if (($user['username'] ?? '') === $username && !empty($user['id'])) {
            return $user;
        }
    }
    return [];
}

function ultrack_keycloak_trigger_password_email(string $userId): bool
{
    if ($userId === '') {
        return false;
    }
    $redirectUri = rtrim((string) ultrack_env('APP_URL', ''), '/') . '/';
    $query = http_build_query([
        'client_id' => (string) ultrack_env('OIDC_FRONT_CLIENT_ID', 'ultrack-frontoffice'),
        'redirect_uri' => $redirectUri,
        'lifespan' => 172800,
    ]);
    $response = ultrack_keycloak_admin_request(
        'PUT',
        '/users/' . rawurlencode($userId) . '/execute-actions-email?' . $query,
        ['UPDATE_PASSWORD']
    );
    return $response['ok'];
}

function ultrack_keycloak_disable_user(string $userId): bool
{
    if ($userId === '') {
        return false;
    }
    $response = ultrack_keycloak_admin_request('PUT', '/users/' . rawurlencode($userId), ['enabled' => false]);
    return $response['ok'];
}

function ultrack_keycloak_create_recruteur(string $username, string $email, string $enseigne = '', string $telephone = ''): ?array
{
    $username = trim((string) $username);
    $email = trim((string) $email);
    if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $usernameSlug = trim((string) preg_replace('/[^a-zA-Z0-9._-]+/', '-', strtolower($username)), '-_.');
    $keycloakUsername = $usernameSlug === '' ? 'rec-' . bin2hex(random_bytes(4)) : 'rec-' . $usernameSlug;
    $keycloakUsername = substr($keycloakUsername, 0, 255);

    $existing = ultrack_keycloak_find_user($keycloakUsername);
    if ($existing === null) {
        return null;
    }
    $created = false;
    if ($existing !== []) {
        $userId = (string) $existing['id'];
    } else {
        $payload = [
            'username' => $keycloakUsername,
            'email' => $email,
            'enabled' => true,
            'emailVerified' => false,
            'attributes' => [
                'enseigne' => [$enseigne],
                'telephone' => [$telephone],
            ],
        ];
        $createResponse = ultrack_keycloak_admin_request('POST', '/users', $payload);
        if (!$createResponse['ok'] || $createResponse['status'] !== 201) {
            return null;
        }
        $location = (string) ($createResponse['headers']['location'] ?? '');
        if (preg_match('#/users/([^/\\s]+)$#', $location, $matches) !== 1) {
            $orphan = ultrack_keycloak_find_user($keycloakUsername);
            if (is_array($orphan) && !empty($orphan['id'])) {
                ultrack_keycloak_delete_user((string) $orphan['id']);
            }
            return null;
        }
        $userId = rawurldecode($matches[1]);
        $created = true;
    }

    $clientUuid = ultrack_keycloak_frontoffice_client_uuid();
    if ($clientUuid === null) {
        if ($created) ultrack_keycloak_delete_user($userId);
        return null;
    }
    $roleId = ultrack_keycloak_recruteur_role_id($clientUuid);
    if ($roleId === null) {
        if ($created) ultrack_keycloak_delete_user($userId);
        return null;
    }
    $roleResponse = ultrack_keycloak_admin_request(
        'POST',
        '/users/' . rawurlencode($userId) . '/role-mappings/clients/' . rawurlencode($clientUuid),
        [['id' => $roleId, 'name' => 'RECRUTEUR']]
    );
    if (!$roleResponse['ok']) {
        if ($created) ultrack_keycloak_delete_user($userId);
        return null;
    }

    $emailSent = ultrack_keycloak_trigger_password_email($userId);

    return [
        'user_id' => $userId,
        'username' => $keycloakUsername,
        'email' => $email,
        'email_sent' => $emailSent,
        'created' => $created,
    ];
}

function ultrack_user_column_exists(PDO $db, string $column): bool
{
    static $cache = [];
    $cacheKey = $column;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE ?");
    $stmt->execute([$column]);
    $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    $cache[$cacheKey] = $exists;

    return $exists;
}

// 1. Afficher la liste des utilisateurs et leurs rôles
function manageRoles() {
    global $db;

    // Sécurité : Vérifier si l'utilisateur connecté est administrateur
    requireAuth(['ADMIN']);

    // Récupérer la liste de tous les utilisateurs
    $stmt = $db->query("SELECT * FROM users ORDER BY id DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Charger la vue dédiée aux droits
    require_once __DIR__ . '/../Views/backoffice/droits.php';
}

// 2. Mettre à jour le rôle ou les droits d'un utilisateur
function updateRole() {
    global $db;
    requireAuth(['ADMIN']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        $role = htmlspecialchars($_POST['role']);

        $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $userId]);

        header('Location: index.php?route=roles&success=1');
        exit;
    }
}



// 3. AJOUTER UN NOUVEAU SUPERVISEUR
// 3. AJOUTER UN NOUVEAU SUPERVISEUR AVEC UPLOAD CNI
function addSupervisor() {
    global $db;

    requireAuth(['ADMIN']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $telephone = trim((string) ($_POST['telephone'] ?? ''));
        $enseigne = trim((string) ($_POST['enseigne'] ?? ''));
        $flotte = trim((string) ($_POST['flotte'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($username === '' || $telephone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['keycloak_error'] = 'Identifiant, téléphone et adresse e-mail valide obligatoires.';
            header('Location: index.php?route=roles&error=1');
            exit;
        }

        $keycloakUser = ultrack_keycloak_create_recruteur($username, $email, $enseigne, $telephone);
        if ($keycloakUser === null) {
            $_SESSION['keycloak_error'] = 'Le compte Keycloak ou son rôle RECRUTEUR n’a pas pu être créé.';
            header('Location: index.php?route=roles&error=1');
            exit;
        }

        $uploadedObjects = [];
        try {
            $cni_recto_name = '';
            $cni_verso_name = '';

            if (isset($_FILES['cni_recto']) && $_FILES['cni_recto']['error'] === UPLOAD_ERR_OK) {
                $cni_recto_name = ultrack_storage_put('cni_pictures', $_FILES['cni_recto'], 'recto');
                $uploadedObjects[] = $cni_recto_name;
            }

            if (isset($_FILES['cni_verso']) && $_FILES['cni_verso']['error'] === UPLOAD_ERR_OK) {
                $cni_verso_name = ultrack_storage_put('cni_pictures', $_FILES['cni_verso'], 'verso');
                $uploadedObjects[] = $cni_verso_name;
            }

            $fields = ['username', 'telephone', 'enseigne', 'flotte', 'role', 'password', 'cni_recto', 'cni_verso'];
            $values = [
                $keycloakUser['username'],
                $telephone,
                $enseigne,
                $flotte,
                'RECRUTEUR',
                '',
                $cni_recto_name,
                $cni_verso_name,
            ];

            if (ultrack_user_column_exists($db, 'email')) {
                $fields[] = 'email';
                $values[] = $email;
            }
            if (ultrack_user_column_exists($db, 'user_ref')) {
                $fields[] = 'user_ref';
                $values[] = $keycloakUser['username'];
            }
            if (ultrack_user_column_exists($db, 'keycloak_sub')) {
                $fields[] = 'keycloak_sub';
                $values[] = $keycloakUser['user_id'];
            }
            if (ultrack_user_column_exists($db, 'created_at')) {
                $fields[] = 'created_at';
                $values[] = date('Y-m-d H:i:s');
            }

            $placeholders = implode(', ', array_fill(0, count($values), '?'));
            $sql = 'INSERT INTO users (' . implode(', ', $fields) . ') VALUES (' . $placeholders . ')';
            $stmt = $db->prepare($sql);
            $stmt->execute($values);

            if (empty($keycloakUser['email_sent'])) {
                $_SESSION['keycloak_notice'] = 'Le compte existe, mais l’e-mail n’a pas été envoyé. Utilisez « Renvoyer l’e-mail » dans la liste.';
            } else {
                $_SESSION['keycloak_notice'] = 'Le lien de définition du mot de passe a été envoyé.';
            }

            header('Location: index.php?route=roles&success=1');
            exit;
        } catch (Throwable $e) {
            foreach ($uploadedObjects as $object) {
                ultrack_storage_delete('cni_pictures', $object);
            }
            if (!empty($keycloakUser['created'])) {
                ultrack_keycloak_delete_user($keycloakUser['user_id'] ?? '');
            }
            $_SESSION['keycloak_error'] = 'La création du profil local a échoué; la compensation applicable a été exécutée.';
            header('Location: index.php?route=roles&error=1');
            exit;
        }
    }
}

function resendRecruteurEmail(): void
{
    global $db;
    requireAuth(['ADMIN']);

    $userId = (int) ($_POST['user_id'] ?? 0);
    $stmt = $db->prepare("SELECT keycloak_sub FROM users WHERE id = ? AND role = 'RECRUTEUR' LIMIT 1");
    $stmt->execute([$userId]);
    $keycloakSub = (string) ($stmt->fetchColumn() ?: '');
    $sent = $keycloakSub !== '' && ultrack_keycloak_trigger_password_email($keycloakSub);
    $_SESSION[$sent ? 'keycloak_notice' : 'keycloak_error'] = $sent
        ? 'L’e-mail de définition du mot de passe a été renvoyé.'
        : 'Le renvoi de l’e-mail a échoué.';
    header('Location: index.php?route=roles');
    exit;
}

function deactivateRecruteur(): void
{
    global $db;
    requireAuth(['ADMIN']);

    $userId = (int) ($_POST['user_id'] ?? 0);
    $stmt = $db->prepare("SELECT user_ref, keycloak_sub FROM users WHERE id = ? AND role = 'RECRUTEUR' LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || empty($user['keycloak_sub']) || !ultrack_keycloak_disable_user((string) $user['keycloak_sub'])) {
        $_SESSION['keycloak_error'] = 'La désactivation Keycloak a échoué.';
        header('Location: index.php?route=roles&error=1');
        exit;
    }

    try {
        $deleteSessions = $db->prepare('DELETE FROM sessions WHERE user_ref = ?');
        $deleteSessions->execute([(string) $user['user_ref']]);
    } catch (Throwable $exception) {
        $_SESSION['keycloak_error'] = 'Compte désactivé dans Keycloak, mais la révocation locale des sessions a échoué.';
        header('Location: index.php?route=roles&error=1');
        exit;
    }
    $_SESSION['keycloak_notice'] = 'Compte recruteur désactivé et sessions actives révoquées.';
    header('Location: index.php?route=roles&success=1');
    exit;
}

// 4. METTRE À JOUR UN SUPERVISEUR
function updateSupervisor() {
    global $db;

    requireAuth(['ADMIN']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
        $id = (int) $_POST['user_id'];
        $stmtOld = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmtOld->execute([$id]);
        $user = $stmtOld->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            header('Location: index.php?route=roles&error=1');
            exit;
        }

        $isKeycloakUser = !empty($user['keycloak_sub']);
        $cni_recto_name = $user['cni_recto'] ?? null;
        $cni_verso_name = $user['cni_verso'] ?? null;
        $newObjects = [];
        if (isset($_FILES['cni_recto']) && $_FILES['cni_recto']['error'] === UPLOAD_ERR_OK) {
            $cni_recto_name = ultrack_storage_put('cni_pictures', $_FILES['cni_recto'], 'recto');
            $newObjects[] = $cni_recto_name;
        }
        if (isset($_FILES['cni_verso']) && $_FILES['cni_verso']['error'] === UPLOAD_ERR_OK) {
            $cni_verso_name = ultrack_storage_put('cni_pictures', $_FILES['cni_verso'], 'verso');
            $newObjects[] = $cni_verso_name;
        }

        $updates = [
            'telephone' => trim((string) ($_POST['telephone'] ?? '')),
            'enseigne' => trim((string) ($_POST['enseigne'] ?? '')),
            'flotte' => trim((string) ($_POST['flotte'] ?? '')),
            'cni_recto' => $cni_recto_name,
            'cni_verso' => $cni_verso_name,
        ];
        if (!$isKeycloakUser) {
            $updates['username'] = trim((string) ($_POST['username'] ?? ''));
            $updates['role'] = trim((string) ($_POST['role'] ?? ''));
            if (ultrack_user_column_exists($db, 'email')) {
                $updates['email'] = trim((string) ($_POST['email'] ?? ''));
            }
        }

        $setFields = [];
        $params = [];
        foreach ($updates as $column => $value) {
            if (ultrack_user_column_exists($db, $column)) {
                $setFields[] = '`' . $column . '` = ?';
                $params[] = $value;
            }
        }
        $params[] = $id;
        try {
            $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $setFields) . ' WHERE id = ?');
            $stmt->execute($params);
        } catch (Throwable $exception) {
            foreach ($newObjects as $object) {
                ultrack_storage_delete('cni_pictures', $object);
            }
            throw $exception;
        }

        foreach (['cni_recto', 'cni_verso'] as $field) {
            $oldObject = (string) ($user[$field] ?? '');
            $newObject = $field === 'cni_recto' ? (string) $cni_recto_name : (string) $cni_verso_name;
            if ($oldObject !== '' && $oldObject !== $newObject) {
                ultrack_storage_delete('cni_pictures', $oldObject);
            }
        }

        header('Location: index.php?route=roles&success=2');
        exit;
    }
}



?>

