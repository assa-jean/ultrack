<?php
// new/app/Controllers/DashboardController.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/AuthController.php';

function dashboard() {
    global $db; // On utilise la connexion PDO du fichier database.php

    // 1. Sécurité
    requireAuth(['ADMIN', 'SUPERVISEUR']);

    // 2. Listes pour les menus déroulants des filtres
    $users_list = $db->query("SELECT id, username FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $enseignes_list = $db->query("SELECT DISTINCT nom_enseigne FROM agents WHERE nom_enseigne IS NOT NULL ORDER BY nom_enseigne ASC")->fetchAll(PDO::FETCH_COLUMN);

    // Récupérer les régions commerciales et administratives distinctes en BD
    $regions_list = $db->query("SELECT DISTINCT region FROM agents WHERE region IS NOT NULL AND region != '' ORDER BY region ASC")->fetchAll(PDO::FETCH_COLUMN);
    $regions_admin_list = $db->query("SELECT DISTINCT region_admin FROM agents WHERE region_admin IS NOT NULL AND region_admin != '' ORDER BY region_admin ASC")->fetchAll(PDO::FETCH_COLUMN);

    // Récupérer les types d'enseignes (Canaux) distincts en BD
    $canaux_list = $db->query("SELECT DISTINCT type_enseigne FROM agents WHERE type_enseigne IS NOT NULL AND type_enseigne != '' ORDER BY type_enseigne ASC")->fetchAll(PDO::FETCH_COLUMN);

    // 3. Gestion dynamique des filtres (Barre de recherche)
    $where = [];
    $params = [];
        
    if (!empty($_GET['region'])) { $where[] = "region = :r"; $params['r'] = $_GET['region']; }
    if (!empty($_GET['region_admin'])) { $where[] = "region_admin = :ra"; $params['ra'] = $_GET['region_admin']; }
    if (!empty($_GET['type'])) { $where[] = "type_enseigne = :t"; $params['t'] = $_GET['type']; }
    if (!empty($_GET['user_id'])) { $where[] = "user_id = :uid"; $params['uid'] = $_GET['user_id']; }
    if (!empty($_GET['nom_enseigne'])) { $where[] = "nom_enseigne = :ne"; $params['ne'] = $_GET['nom_enseigne']; }
    
    if (!empty($_GET['filter_nom'])) { 
        $where[] = "nom LIKE :nom"; 
        $params['nom'] = '%' . $_GET['filter_nom'] . '%'; 
    }
    if (!empty($_GET['filter_login'])) { 
        $where[] = "login LIKE :login"; 
        $params['login'] = '%' . $_GET['filter_login'] . '%'; 
    }
    
    if (!empty($_GET['filter_famoco_id'])) { 
        $where[] = "famoco_id LIKE :famoco_id"; 
        $params['famoco_id'] = '%' . $_GET['filter_famoco_id'] . '%'; 
    }
    
    if (isset($_GET['etat_traitement']) && $_GET['etat_traitement'] !== '') { 
        $where[] = "Etat_traitement = :et"; 
        $params['et'] = (int)$_GET['etat_traitement']; 
    }

    $whereClause = $where ? " WHERE " . implode(' AND ', $where) : "";

    // 4. Export CSV (Intercepte la requête avant d'afficher la page)
    if (isset($_GET['export_csv'])) {
        $stmtExport = $db->prepare("SELECT * FROM agents" . $whereClause);
        $stmtExport->execute($params);
        $allAgents = $stmtExport->fetchAll(PDO::FETCH_ASSOC);
    
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=export_agents_' . date('Y-m-d_H-i-s') . '.csv');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM pour Excel
        
        if (!empty($allAgents)) {
            fputcsv($output, array_keys($allAgents[0]), ';');
            foreach ($allAgents as $row) {
                fputcsv($output, $row, ';');
            }
        }
        fclose($output);
        exit();
    }

    // 5. Pagination
    $limit = 20;
    $page = max(1, isset($_GET['p']) ? (int)$_GET['p'] : 1);
    $offset = ($page - 1) * $limit;
    
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM agents" . $whereClause);
    $stmtCount->execute($params);
    $totalAgents = $stmtCount->fetchColumn();
    $totalPages = ceil($totalAgents / $limit);
    
    // 6. Récupération des données pour le grand tableau
    $sql = "SELECT * FROM agents" . $whereClause . " ORDER BY id DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Statistiques POC V2 & V3
    $stmtStats = $db->query("SELECT
        SUM(CASE WHEN type_enseigne = 'AGENCE_BTL' THEN 1 ELSE 0 END) as total,
        SUM(CASE WHEN type_enseigne = 'AGENCE_BTL' AND nom_enseigne = 'BMTC' THEN 1 ELSE 0 END) as nb_bmtc,
        SUM(CASE WHEN type_enseigne = 'AGENCE_BTL' AND nom_enseigne = 'CALIFAR' THEN 1 ELSE 0 END) as nb_califar,
        SUM(CASE WHEN type_enseigne = 'AGENCE_BTL' AND nom_enseigne = 'RATACAM' THEN 1 ELSE 0 END) as nb_ratacam,
        SUM(CASE WHEN type_enseigne = 'PARTNER' THEN 1 ELSE 0 END) as nb_partner_total, 
        SUM(CASE WHEN type_enseigne = 'PARTNER' AND nom_enseigne = 'BMTC' THEN 1 ELSE 0 END) as nb_partner_bmtc,
        SUM(CASE WHEN type_enseigne = 'PARTNER' AND nom_enseigne = 'TEN HORNS' THEN 1 ELSE 0 END) as nb_partner_ten_horns
        FROM agents");
        
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    if (!$stats || $stats['total'] === null) {
        $stats = [
            'total' => 0, 'nb_bmtc' => 0, 'nb_califar' => 0, 'nb_ratacam' => 0,
            'nb_partner_total' => 0, 'nb_partner_bmtc' => 0, 'nb_partner_ten_horns' => 0
        ];
    }

    // 8. Envoi de toutes les données à la Vue
    require_once __DIR__ . '/../Views/backoffice/dashboard.php';
}
?>