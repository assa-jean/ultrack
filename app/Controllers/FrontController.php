<?php
// new/app/Controllers/FrontController.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/storage.php';
require_once __DIR__ . '/AuthController.php';

// 1. Affichage de la page d'Accueil / Recensement
function showRegisterForm() {
    global $db;

    requireAuth(['RECRUTEUR']);

    require_once __DIR__ . '/../Views/frontoffice/register.php';
}

// 2. Affichage de la page Statistiques pour le Superviseur
function showSuperviseurStats() {
    global $db;

    requireAuth(['RECRUTEUR']);

    $userId = $_SESSION['user_id'];

    // Récupérer le total des agents enregistrés par CE superviseur
    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM agents WHERE user_id = ?");
    $stmtTotal->execute([$userId]);
    $totalMyAgents = $stmtTotal->fetchColumn();

    // Récupérer la répartition par statut de traitement
    $stmtValide = $db->prepare("SELECT COUNT(*) FROM agents WHERE user_id = ? AND Etat_traitement = 2");
    $stmtValide->execute([$userId]);
    $totalValide = $stmtValide->fetchColumn();

    $stmtAttente = $db->prepare("SELECT COUNT(*) FROM agents WHERE user_id = ? AND (Etat_traitement = 1 OR Etat_traitement = 0)");
    $stmtAttente->execute([$userId]);
    $totalAttente = $stmtAttente->fetchColumn();

    // Récupérer la liste des 10 derniers agents recensés par ce superviseur
    $stmtRecent = $db->prepare("SELECT * FROM agents WHERE user_id = ? ORDER BY id DESC LIMIT 10");
    $stmtRecent->execute([$userId]);
    $myRecentAgents = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    require_once __DIR__ . '/../Views/frontoffice/stats.php';
}

// 3. Traitement de la soumission du formulaire
function submitAgent() {
    global $db;

    requireAuth(['RECRUTEUR']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $userId = $_SESSION['user_id'];
        $nom = htmlspecialchars($_POST['nom'] ?? '');
        $login = htmlspecialchars($_POST['login'] ?? '');
        $famocoId = htmlspecialchars($_POST['famoco_id'] ?? '');
        $cniNumber = htmlspecialchars($_POST['cni_number'] ?? '');
        $region = htmlspecialchars($_POST['region'] ?? '');
        $regionAdmin = htmlspecialchars($_POST['region_admin'] ?? '');
        $typeEnseigne = htmlspecialchars($_POST['type_enseigne'] ?? '');
        $nomEnseigne = htmlspecialchars($_POST['nom_enseigne'] ?? '');

        // Upload Photo Profil
        $photoPath = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photoPath = ultrack_storage_put('uploads', $_FILES['photo'], 'agent');
        }

        // Upload CNI Recto
        $cniFront = '';
        if (isset($_FILES['cni_front']) && $_FILES['cni_front']['error'] === UPLOAD_ERR_OK) {
            $cniFront = ultrack_storage_put('cni_pictures', $_FILES['cni_front'], 'cni_recto');
        }

        // Upload CNI Verso
        $cniBack = '';
        if (isset($_FILES['cni_back']) && $_FILES['cni_back']['error'] === UPLOAD_ERR_OK) {
            $cniBack = ultrack_storage_put('cni_pictures', $_FILES['cni_back'], 'cni_verso');
        }

        $stmt = $db->prepare("
            INSERT INTO agents 
            (user_id, nom, login, famoco_id, cni_number, region, region_admin, type_enseigne, nom_enseigne, photo_path, cni_front, cni_back, acceptation_reglement, Etat_traitement, date_created) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())
        ");

        $stmt->execute([
            $userId, $nom, $login, $famocoId, $cniNumber, $region, $regionAdmin, 
            $typeEnseigne, $nomEnseigne, $photoPath, $cniFront, $cniBack
        ]);

        header('Location: index.php?route=front_register&success=1');
        exit;
    }
}


// 4. API AJAX : RECHERCHE DYNAMIQUE DE FAMOCO & LOGIN KAABU EN BD
function searchFamoco() {
    global $db;

    requireAuth(['RECRUTEUR']);

    header('Content-Type: application/json');

    $enseigne = htmlspecialchars($_GET['enseigne'] ?? '');
    $term = htmlspecialchars($_GET['term'] ?? '');

    if (empty($enseigne) || empty($term)) {
        echo json_encode([]);
        exit;
    }

    // Recherche les Famocos correspondant aux 4 derniers caractères et à l'enseigne
    $stmt = $db->prepare("
        SELECT famoco_id as id, login_kaabu as login 
        FROM famocos 
        WHERE nom_enseigne = ? AND famoco_id LIKE ? 
        LIMIT 10
    ");
    $stmt->execute([$enseigne, '%' . $term]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);
    exit;
}

// 5. API CROSS-CHECKING : VÉRIFICATION DES DOUBLONS
function checkDuplicate() {
    global $db;

    requireAuth(['RECRUTEUR']);
    header('Content-Type: application/json');

    $famoco_id = trim($_GET['famoco_id'] ?? '');
    $login = trim($_GET['login'] ?? '');
    $cni_number = trim($_GET['cni_number'] ?? '');

    $errors = [];

    // 1. Vérifier si le Famoco ID existe déjà dans la table agents
    if (!empty($famoco_id)) {
        $stmt = $db->prepare("SELECT id FROM agents WHERE famoco_id = ? LIMIT 1");
        $stmt->execute([$famoco_id]);
        if ($stmt->fetch()) {
            $errors[] = "Le Famoco ID ($famoco_id) est déjà enregistré pour un autre agent.";
        }
    }

    // 2. Vérifier si le Login Kaabu existe déjà dans la table agents
    if (!empty($login)) {
        $stmt = $db->prepare("SELECT id FROM agents WHERE login = ? LIMIT 1");
        $stmt->execute([$login]);
        if ($stmt->fetch()) {
            $errors[] = "Le Login Kaabu ($login) est déjà associé à un agent recensé.";
        }
    }

    // 3. Vérifier si le Numéro CNI existe déjà dans la table agents
    if (!empty($cni_number)) {
        $stmt = $db->prepare("SELECT id FROM agents WHERE cni_number = ? LIMIT 1");
        $stmt->execute([$cni_number]);
        if ($stmt->fetch()) {
            $errors[] = "Le Numéro CNI ($cni_number) existe déjà dans la base de données.";
        }
    }

    if (!empty($errors)) {
        echo json_encode(['status' => 'duplicate', 'message' => implode('<br>', $errors)]);
    } else {
        echo json_encode(['status' => 'ok']);
    }
    exit;
}

?>