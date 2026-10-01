<?php
require_once __DIR__ . '/bootstrap.php';

$host = ultrack_env('DB_HOST', 'localhost');
$dbname = ultrack_env('DB_NAME', 'ultrack');
$username = ultrack_env('DB_USER', 'root');
$password = ultrack_env('DB_PASS', '');
$port = ultrack_env('DB_PORT', '3306');

try {
    $db = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if (PHP_SAPI === 'cli') {
        throw $e;
    }

    http_response_code(500);
    echo 'Erreur de connexion à la base de données.';
    exit;
}
?>