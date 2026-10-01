<?php
// new/app/Controllers/FlotteController.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/AuthController.php';

function overviewFlottes() {
    global $db;

    requireAuth(['ADMIN', 'SUPERVISEUR']);

    // 1. Compter les Superviseurs par Flotte
    $stmtUsers = $db->query("
        SELECT flotte, COUNT(*) as total_users 
        FROM users 
        WHERE flotte IN ('DD', 'B2B', 'OMCM') 
        GROUP BY flotte
    ");
    $usersByFlotte = $stmtUsers->fetchAll(PDO::FETCH_KEY_PAIR); 
    // Retourne un tableau formaté : ['DD' => X, 'B2B' => Y, 'OMCM' => Z]

    // 2. Compter les Agents liés à chaque Flotte (via le user_id de leur créateur)
    $stmtAgents = $db->query("
        SELECT u.flotte, COUNT(a.id) as total_agents 
        FROM agents a 
        JOIN users u ON a.user_id = u.id 
        WHERE u.flotte IN ('DD', 'B2B', 'OMCM') 
        GROUP BY u.flotte
    ");
    $agentsByFlotte = $stmtAgents->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. Assembler et structurer les statistiques pour la Vue
    $flottesList = ['DD', 'B2B', 'OMCM'];
    $stats = [];
    
    foreach ($flottesList as $f) {
        $uCount = isset($usersByFlotte[$f]) ? (int)$usersByFlotte[$f] : 0;
        $aCount = isset($agentsByFlotte[$f]) ? (int)$agentsByFlotte[$f] : 0;
        
        $stats[$f] = [
            'users' => $uCount,
            'agents' => $aCount,
            'total' => $uCount + $aCount // Cumul exact Superviseurs + Agents
        ];
    }

    // Charger la vue
    require_once __DIR__ . '/../Views/backoffice/flottes.php';
}
?>