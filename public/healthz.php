<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

try {
    $db->query('SELECT 1');
    echo json_encode([
        'status' => 'ok',
        'database' => 'connected',
        'time' => time(),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'database' => 'unavailable',
        'message' => 'Database check failed.',
    ], JSON_THROW_ON_ERROR);
}
