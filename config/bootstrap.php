<?php

if (!function_exists('ultrack_env')) {
    function ultrack_env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        if (is_string($value) && strtolower($value) === 'false') {
            return false;
        }
        if (is_string($value) && strtolower($value) === 'true') {
            return true;
        }
        if (is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        return $value;
    }
}

if (!function_exists('ultrack_load_env')) {
    function ultrack_load_env(string $path = null): void
    {
        $envPath = $path ?? __DIR__ . '/../.env';

        if (!is_file($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            $value = preg_replace('/^(["\'])(.*)\1$/s', '$2', $value) ?? $value;
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }
    }
}

if (!isset($GLOBALS['ULTRACK_BOOTSTRAPPED'])) {
    ultrack_load_env();

    $sessionName = ultrack_env('SESSION_NAME', 'ULTRACK_SESSION');
    if (!headers_sent()) {
        session_name($sessionName);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    require_once __DIR__ . '/session_db.php';

    $dbDsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        ultrack_env('DB_HOST', 'localhost'),
        ultrack_env('DB_PORT', '3306'),
        ultrack_env('DB_NAME', 'ultrack')
    );

    try {
        $db = new PDO(
            $dbDsn,
            ultrack_env('DB_USER', 'root'),
            ultrack_env('DB_PASS', '')
        );
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $sessionHandler = new SessionDbHandler($db);
        session_set_save_handler($sessionHandler, true);
    } catch (Throwable $e) {
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
            echo 'Database configuration is invalid or unreachable.';
            exit;
        }
    }

    $GLOBALS['ULTRACK_BOOTSTRAPPED'] = true;
}
