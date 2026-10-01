<?php
// new/app/Controllers/AgentController.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/storage.php';
require_once __DIR__ . '/AuthController.php';

// --- FONCTION HELPER POUR LES FICHIERS ---
function getValidFileUrl($filename, $folders) {
    if (!empty($filename)) {
        foreach ($folders as $folder) {
            if (storage_exists($folder, $filename)) {
                return storage_presign($folder, $filename, 300);
            }
        }
    }
    return '';
}

// 1. VOIR LA FICHE D'UN AGENT
function viewAgent() {
    global $db;
    
    if (!isset($_GET['id'])) {
        header('Location: index.php?route=dashboard');
        exit;
    }
    
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT a.*, u.username as creator_name FROM agents a LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$id]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        header('Location: index.php?route=dashboard');
        exit;
    }
    
    // Résolution des listes et des stats pour que la page de fond s'affiche bien
    $users_list = $db->query("SELECT id, username FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $enseignes_list = $db->query("SELECT DISTINCT nom_enseigne FROM agents WHERE nom_enseigne IS NOT NULL ORDER BY nom_enseigne ASC")->fetchAll(PDO::FETCH_COLUMN);

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

    // On récupère aussi la liste des agents pour le tableau en arrière-plan
    $agents = $db->query("SELECT * FROM agents ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $totalPages = 1;
    $page = 1;

    // On charge la vue dashboard.php (qui contient le code du modal à la fin)
    require_once __DIR__ . '/../Views/backoffice/dashboard.php';
}

// 2. SUPPRIMER UN AGENT
function deleteAgent() {
    global $db;

    requireAuth(['ADMIN']);

    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];

        // Optionnel : Récupérer les images pour les supprimer physiquement du disque
        $stmtSel = $db->prepare("SELECT photo_path, cni_front, cni_back FROM agents WHERE id = ?");
        $stmtSel->execute([$id]);
        $agent = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if ($agent) {
            if (!empty($agent['photo_path'])) {
                storage_delete('uploads', $agent['photo_path']);
            }
            if (!empty($agent['cni_front'])) {
                storage_delete('cni_pictures', $agent['cni_front']);
            }
            if (!empty($agent['cni_back'])) {
                storage_delete('cni_pictures', $agent['cni_back']);
            }
        }

        $stmt = $db->prepare("DELETE FROM agents WHERE id = ?");
        $stmt->execute([$id]);
    }

    header('Location: index.php?route=dashboard&deleted=1');
    exit;
}

// 3. TÉLÉCHARGER LE PDF (Dompdf)
function downloadPdf() {
    global $db;

    requireAuth(['ADMIN', 'SUPERVISEUR']);
    
    if (!isset($_GET['id'])) {
        header('Location: index.php?route=dashboard');
        exit;
    }
    
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT a.*, u.username as creator_name FROM agents a LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$id]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($agent) {
        // Détection automatique du chemin vers autoload.php
        $vendorPaths = [
            __DIR__ . '/../../vendor/autoload.php',       // À la racine du projet principal
            __DIR__ . '/../../old/vendor/autoload.php',   // Dans le dossier old/
            __DIR__ . '/../vendor/autoload.php',          // Dans new/
        ];

        $autoloadFound = false;
        foreach ($vendorPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $autoloadFound = true;
                break;
            }
        }

        if (!$autoloadFound) {
             echo "<script>alert('Erreur : Le fichier vendor/autoload.php est introuvable. Veuillez vérifier l\'emplacement de Dompdf.'); window.history.back();</script>";
             exit;
        }
        
        if (class_exists('\Dompdf\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
            
            // Conversion des images en Base64
            $getB64 = function($filename, $folders) {
                if (!empty($filename)) {
                    foreach ($folders as $folder) {
                        if (storage_exists($folder, $filename)) {
                            $data = storage_get($folder, $filename);
                            if ($data !== null) {
                                $type = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                $mime = match ($type) {
                                    'jpg', 'jpeg' => 'image/jpeg',
                                    'png' => 'image/png',
                                    'webp' => 'image/webp',
                                    'gif' => 'image/gif',
                                    default => 'image/jpeg',
                                };
                                return 'data:' . $mime . ';base64,' . base64_encode($data);
                            }
                        }
                    }
                }
                return '';
            };

            $photo_b64 = $getB64($agent['photo_path'], ['uploads', 'uploads_pocv2']);
            $cni_front_b64 = $getB64($agent['cni_front'], ['cni_pictures', 'cni_pictures_pocv2']);
            $cni_back_b64 = $getB64($agent['cni_back'], ['cni_pictures', 'cni_pictures_pocv2']);
            
            // Code couleur et libellé de l'état
            $v = isset($agent['Etat_traitement']) ? (int)$agent['Etat_traitement'] : (isset($agent['etat_traitement']) ? (int)$agent['etat_traitement'] : 0);
            $etat_color = ($v === 2) ? '#16a34a' : (($v === 1) ? '#dc2626' : '#d97706');
            $etat_text = ($v === 2) ? 'Validé' : (($v === 1) ? 'En attente BO' : 'En attente');

            $html_pdf = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <style>
                        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; color: #1e293b; margin: 0; padding: 0; font-size: 12px; }
                        .header { background-color: #0f172a; color: #ffffff; padding: 30px; margin-bottom: 25px; }
                        .header h1 { margin: 0; font-size: 24px; font-weight: bold; letter-spacing: -0.5px; }
                        .header p { margin: 5px 0 0 0; color: #f97316; font-size: 14px; font-weight: 500; }
                        .container { padding: 0 30px; }
                        .profile-section { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
                        .profile-table { width: 100%; border-collapse: collapse; }
                        .avatar { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid #f1f5f9; }
                        .agent-title { font-size: 20px; font-weight: bold; color: #0f172a; margin: 0; }
                        .agent-subtitle { font-size: 13px; color: #64748b; margin-top: 5px; }
                        .grid { width: 100%; margin-bottom: 25px; }
                        .grid td { width: 50%; padding: 10px 15px; vertical-align: top; }
                        .label { font-size: 10px; text-transform: uppercase; color: #94a3b8; font-weight: bold; margin-bottom: 4px; }
                        .value { font-size: 13px; font-weight: bold; color: #0f172a; }
                        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; color: #ffffff; font-size: 11px; font-weight: bold; }
                        .section-title { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: bold; margin-top: 30px; margin-bottom: 12px; letter-spacing: 1px; border-left: 3px solid #f97316; padding-left: 8px; }
                        .cni-container { width: 100%; }
                        .cni-box { width: 48%; display: inline-block; box-sizing: border-box; }
                        .cni-img { width: 100%; height: auto; border-radius: 6px; border: 1px solid #e2e8f0; }
                        .footer { position: fixed; bottom: 20px; left: 30px; right: 30px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 10px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>UltraTrack — Fiche d\'Identification</h1>
                        <p>Direction Orange — Monitoring Terrain</p>
                    </div>
                    
                    <div class="container">
                        <div class="profile-section">
                            <table class="profile-table">
                                <tr>
                                    <td style="width: 110px;">
                                        ' . ($photo_b64 ? '<img src="' . $photo_b64 . '" class="avatar">' : '<div style="width:90px; height:90px; background:#e2e8f0; border-radius:50%;"></div>') . '
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <div class="agent-title">' . htmlspecialchars($agent['nom']) . '</div>
                                        <div class="agent-subtitle">ID Agent: #' . $agent['id'] . ' | Enregistré par : ' . htmlspecialchars($agent['creator_name'] ?? 'Système') . '</div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <table class="grid">
                            <tr>
                                <td>
                                    <div class="label">Numéro de Login</div>
                                    <div class="value">' . htmlspecialchars($agent['login']) . '</div>
                                </td>
                                <td>
                                    <div class="label">Numéro CNI</div>
                                    <div class="value">' . htmlspecialchars($agent['cni_number']) . '</div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="label">Canal / Type Enseigne</div>
                                    <div class="value">' . htmlspecialchars($agent['type_enseigne']) . '</div>
                                </td>
                                <td>
                                    <div class="label">Nom de l\'Enseigne</div>
                                    <div class="value">' . htmlspecialchars($agent['nom_enseigne']) . '</div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="label">Région Commerciale</div>
                                    <div class="value">' . htmlspecialchars($agent['region']) . '</div>
                                </td>
                                <td>
                                    <div class="label">Région Administrative</div>
                                    <div class="value">' . htmlspecialchars($agent['region_admin']) . '</div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="label">Date d\'Enregistrement</div>
                                    <div class="value">' . htmlspecialchars($agent['date_created']) . '</div>
                                </td>
                                <td>
                                    <div class="label">Statut de Traitement</div>
                                    <div style="margin-top:4px;">
                                        <span class="status-badge" style="background-color: ' . $etat_color . ';">' . $etat_text . '</span>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <div class="section-title">Pièces Justificatives (CNI)</div>
                        <div class="cni-container">
                            <div class="cni-box" style="margin-right: 3%;">
                                <div class="label" style="margin-bottom:6px;">Face Avant</div>
                                ' . ($cni_front_b64 ? '<img src="' . $cni_front_b64 . '" class="cni-img">' : '<div style="height:150px; background:#f8fafc; border:1px dashed #cbd5e1; text-align:center; line-height:150px; color:#94a3b8;">Absente</div>') . '
                            </div>
                            <div class="cni-box">
                                <div class="label" style="margin-bottom:6px;">Face Arrière</div>
                                ' . ($cni_back_b64 ? '<img src="' . $cni_back_b64 . '" class="cni-img">' : '<div style="height:150px; background:#f8fafc; border:1px dashed #cbd5e1; text-align:center; line-height:150px; color:#94a3b8;">Absente</div>') . '
                            </div>
                        </div>
                    </div>

                    <div class="footer">
                        Fiche générée automatiquement via UltraTrack v2 — Document confidentiel Orange Direction.
                    </div>
                </body>
                </html>';

            $dompdf->loadHtml($html_pdf);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $filename = "Fiche_Agent_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $agent['nom']) . ".pdf";
            $dompdf->stream($filename, ["Attachment" => true]);
            exit();
        } else {
            echo "<script>alert('Erreur : La classe Dompdf n\'est pas chargée.'); window.history.back();</script>";
            exit();
        }
    }
}

// 4. METTRE À JOUR UN AGENT (depuis le popup d'édition)
function updateAgent() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agent_id'])) {
        $id = (int)$_POST['agent_id'];
        $nom = htmlspecialchars($_POST['nom']);
        $login = htmlspecialchars($_POST['login']);
        $famoco_id = htmlspecialchars($_POST['famoco_id']);
        $cni_number = htmlspecialchars($_POST['cni_number']);
        $region = htmlspecialchars($_POST['region']);
        $region_admin = htmlspecialchars($_POST['region_admin']);
        $type_enseigne = htmlspecialchars($_POST['type_enseigne']);
        $nom_enseigne = htmlspecialchars($_POST['nom_enseigne']);
        $etat_traitement = (int)$_POST['etat_traitement'];

        $sql = "UPDATE agents SET nom = ?, login = ?, famoco_id = ?, cni_number = ?, region = ?, region_admin = ?, type_enseigne = ?, nom_enseigne = ?, Etat_traitement = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$nom, $login, $famoco_id, $cni_number, $region, $region_admin, $type_enseigne, $nom_enseigne, $etat_traitement, $id]);

        header("Location: index.php?route=dashboard");
        exit;
    }
}
?>