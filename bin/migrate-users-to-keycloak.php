#!/usr/bin/env php
<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/Controllers/RoleController.php';

$options = getopt('d::', ['dry-run', 'only:']);
$dryRun = isset($options['d']) || isset($options['dry-run']);
$only = $options['only'] ?? null;

if (!function_exists('ultrack_keycloak_create_recruteur')) {
    fwrite(STDERR, "Keycloak provisioning helpers are not available.\n");
    exit(1);
}

if (!$dryRun && (empty(ultrack_env('KC_ADMIN_CLIENT_ID', '')) || empty(ultrack_env('KC_ADMIN_CLIENT_SECRET', '')))) {
    fwrite(STDERR, "Missing KC_ADMIN_CLIENT_ID / KC_ADMIN_CLIENT_SECRET.\n");
    exit(1);
}

$users = $db->query(
    "SELECT id, username, email, user_ref, keycloak_sub
     FROM users
     WHERE email IS NOT NULL AND email <> ''
     ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$processed = 0;
$failed = 0;

foreach ($users as $user) {
    $username = (string) ($user['username'] ?? '');
    if ($only !== null && strtolower($username) !== strtolower((string) $only)) {
        continue;
    }

    if ($username === '') {
        continue;
    }

    if (!empty($user['keycloak_sub']) && !empty($user['user_ref'])) {
        echo "SKIP {$username}: already linked to Keycloak.\n";
        continue;
    }

    if ($dryRun) {
        echo "DRY-RUN: would provision recruiter {$username}.\n";
        continue;
    }

    $keycloakUser = ultrack_keycloak_create_recruteur(
        $username,
        (string) $user['email']
    );
    if ($keycloakUser === null) {
        fwrite(STDERR, "FAILED {$username}: Keycloak provisioning did not complete.\n");
        $failed++;
        continue;
    }

    try {
        $db->beginTransaction();
        $update = $db->prepare(
            "UPDATE users SET user_ref = ?, keycloak_sub = ?, role = 'RECRUTEUR' WHERE id = ?"
        );
        $update->execute([$keycloakUser['username'], $keycloakUser['user_id'], (int) $user['id']]);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if (!empty($keycloakUser['created']) && !ultrack_keycloak_delete_user($keycloakUser['user_id'])) {
            fwrite(STDERR, "COMPENSATION FAILED for {$username}; Keycloak account may remain.\n");
        }
        fwrite(STDERR, "FAILED {$username}: local mapping failed; compensation attempted.\n");
        $failed++;
        continue;
    }

    $mailStatus = !empty($keycloakUser['email_sent']) ? 'email sent' : 'email pending';
    echo "PROVISIONED {$username}: {$mailStatus}.\n";
    $processed++;
}

if (!$dryRun) {
    echo "Completed: {$processed} provisioned, {$failed} failed.\n";
}

exit($failed > 0 ? 1 : 0);
