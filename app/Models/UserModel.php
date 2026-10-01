<?php
// new/app/Models/UserModel.php
require_once __DIR__ . '/../../config/database.php';

function getUserByUsername($username) {
    global $db;
    // On cherche dans la table 'users' avec la colonne 'username'
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>