
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>UltraTrack - Dashboard Direction</title>
</head>
<body class="bg-slate-50 min-h-screen flex h-screen overflow-hidden">
    
    <!-- Loader global -->
    <div id="fullLoader" class="fixed inset-0 bg-white/90 z-[100] hidden flex-col items-center justify-center">
        <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-orange-600 mb-4"></div>
        <p class="text-slate-800 font-bold text-xl">Recherche en cours...</p>
    </div>

    <!-- ==================== SIDEBAR ==================== -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col min-h-screen shadow-2xl flex-shrink-0">
        <div class="p-6 bg-slate-950 flex items-center gap-3 border-b border-slate-800">
            <i class="fas fa-id-badge text-orange-500 text-2xl"></i>Welcome,<br>
            <span class="text-white text-xl font-bold tracking-wider"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <p class="px-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Tableau de bord</p>
            
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-orange-600 text-white rounded-lg shadow-md transition-colors">
                <i class="fas fa-home w-5 text-center"></i>
                <span class="font-medium">Accueil</span>
            </a>
            <a href="index.php?route=flottes" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-lg transition-colors">
                <i class="fas fa-truck w-5 text-center"></i>
                <span class="font-medium">Overview Flottes</span>
            </a>
           
            <a href="index.php?route=roles" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-lg transition-colors">
                <i class="fas fa-users-cog w-5 text-center"></i>
                <span class="font-medium">Agents & Superviseurs</span>
            </a>

            <p class="px-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-8 mb-4">Administration</p>

            <a href="#" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-lg transition-colors">
                <i class="fas fa-cog w-5 text-center"></i>
                <span class="font-medium">Paramètres</span>
            </a>
        </nav>

        <div class="p-4 bg-slate-950 border-t border-slate-800">
            <a href="index.php?route=logout" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-red-500 hover:text-white rounded-lg transition-all duration-200 group">
                <i class="fas fa-sign-out-alt w-5 text-center group-hover:-translate-x-1 transition-transform"></i>
                <span class="font-medium">Déconnexion</span>
            </a>
        </div>
    </aside>

    <!-- ==================== CONTENU PRINCIPAL ==================== -->
    <main class="flex-1 flex flex-col h-screen overflow-y-auto">
        
        <!-- En-tête (Header) -->
        <header class="bg-white p-8 shadow-sm border-b border-slate-200 z-10 sticky top-0">
            <h1 class="text-3xl font-bold tracking-tight text-slate-800">Interface Back Office</h1>
            <p class="text-orange-600 font-medium mt-1">Monitoring Terrain | DD - OCM </p>
        </header>

        <div class="p-6 lg:p-8 max-w-full">
            
            <!-- Filtres -->
            <form method="GET" class="bg-white p-6 rounded-xl shadow-sm mb-8 flex gap-4 items-end flex-wrap border border-slate-100">
                <!-- Champ caché essentiel pour garder la route dashboard -->
                <input type="hidden" name="route" value="dashboard">

                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Région Admin.</label>
                    <select name="region_admin" class="p-2 border border-slate-200 rounded-lg w-full focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none font-medium">
                        <option value="">Toutes</option>
                        <?php foreach ($regions_admin_list as $ra): ?>
                            <option value="<?= htmlspecialchars($ra) ?>" <?= ($_GET['region_admin'] ?? '') === $ra ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ra) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Canal</label>
                    <select name="type" class="p-2 border border-slate-200 rounded-lg w-full focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none font-medium">
                        <option value="">Tous</option>
                        <?php foreach ($canaux_list as $canal): ?>
                            <option value="<?= htmlspecialchars($canal) ?>" <?= ($_GET['type'] ?? '') === $canal ? 'selected' : '' ?>>
                                <?= htmlspecialchars($canal) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Superviseurs</label>
                    <select name="user_id" class="p-2 border border-slate-200 rounded-lg w-full focus:border-orange-500 outline-none">
                        <option value="">Tous les utilisateurs</option>
                        <?php foreach ($users_list as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($_GET['user_id']??'') == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Nom Enseigne</label>
                    <select name="nom_enseigne" class="p-2 border border-slate-200 rounded-lg w-full outline-none focus:border-orange-500">
                        <option value="">Toutes</option>
                        <?php foreach ($enseignes_list as $enseigne): ?>
                            <option value="<?= htmlspecialchars($enseigne) ?>" <?= ($_GET['nom_enseigne']??'') == $enseigne ? 'selected' : '' ?>>
                                <?= htmlspecialchars($enseigne) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">État de traitement</label>
                    <select name="etat_traitement" class="p-2 border border-slate-200 rounded-lg w-full min-w-[150px] outline-none focus:border-orange-500">
                        <option value="">Tous</option>
                        <option value="0" <?= (isset($_GET['etat_traitement']) && $_GET['etat_traitement'] === '0') ? 'selected' : '' ?>>En attente (0)</option>
                        <option value="1" <?= ($_GET['etat_traitement']??'') == '1' ? 'selected' : '' ?>>En attente BO (1)</option>
                        <option value="2" <?= ($_GET['etat_traitement']??'') == '2' ? 'selected' : '' ?>>Validé (2)</option>
                    </select>
                </div>
                
                <div>
                     <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Filtrer par Nom</label>
                    <input type="text" name="filter_nom" value="<?= htmlspecialchars($_GET['filter_nom'] ?? '') ?>" 
                           placeholder="Nom de l'agent..." class="p-2 border border-slate-200 rounded-lg w-full outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Filtrer par Login</label>
                    <input type="text" name="filter_login" value="<?= htmlspecialchars($_GET['filter_login'] ?? '') ?>" placeholder="6XXXXXXXX" class="w-full p-2.5 border border-slate-200 rounded-lg text-sm outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Filtrer par Famoco_ID</label>
                    <input type="text" name="filter_famoco_id" value="<?= htmlspecialchars($_GET['filter_famoco_id'] ?? '') ?>" 
                           placeholder="XXXX" class="p-2 border border-slate-200 rounded-lg w-full outline-none focus:border-orange-500">
                </div>  
                
                <button type="submit" id="submitBtn" class="bg-orange-600 text-white px-8 py-2 rounded-lg hover:bg-orange-700 font-bold ml-auto shadow-sm transition-colors" onclick="showLoader(event)">
                    Rechercher
                </button>
            </form>

            <!-- STATS POC V2 -->
            <h2 class="text-sm font-bold text-slate-700 uppercase mb-3"><i class="fas fa-chart-pie mr-2 text-slate-400"></i>POC V2 - BTL SUD OUEST</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-l-blue-500 border border-slate-100">
                    <p class="text-slate-500 text-[10px] uppercase font-bold">Total</p>
                    <p class="text-3xl font-bold text-slate-800"><?= $stats['total'] ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-l-purple-500 border border-slate-100">
                    <p class="text-slate-500 text-[10px] uppercase font-bold">BMTC</p>
                    <p class="text-3xl font-bold text-slate-800"><?= $stats['nb_bmtc'] ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-l-yellow-500 border border-slate-100">
                    <p class="text-slate-500 text-[10px] uppercase font-bold">CALIFAR</p>
                    <p class="text-3xl font-bold text-slate-800"><?= $stats['nb_califar'] ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-l-red-500 border border-slate-100">
                    <p class="text-slate-500 text-[10px] uppercase font-bold">RATACAM</p>
                    <p class="text-3xl font-bold text-slate-800"><?= $stats['nb_ratacam'] ?></p>
                </div>
            </div>
            
            <!-- STATS POC V3 -->
            <h2 class="text-sm font-bold text-slate-700 uppercase mb-3"><i class="fas fa-chart-pie mr-2 text-slate-400"></i>POC V3 - PARTNER SUD OUEST</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 gap-4 mb-8">
                <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-l-emerald-500 border border-slate-100">
                    <p class="text-slate-500 text-[10px] uppercase font-bold">Total Partner</p>
                    <p class="text-3xl font-bold text-slate-800"><?= $stats['nb_partner_total'] ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-l-indigo-500 border border-slate-100">
                    <p class="text-slate-500 text-[10px] uppercase font-bold">BTMC SARL</p>
                    <p class="text-3xl font-bold text-slate-800"><?= $stats['nb_partner_bmtc'] ?></p>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-l-orange-500 border border-slate-100">
                    <p class="text-slate-500 text-[10px] uppercase font-bold">TEN HORNS</p>
                    <p class="text-3xl font-bold text-slate-800"><?= $stats['nb_partner_ten_horns'] ?></p>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="flex justify-end mb-4">
                <a href="?export_csv=1&<?= http_build_query($_GET) ?>" 
                   class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 font-bold flex items-center gap-2 shadow-sm transition-colors">
                    <i class="fas fa-file-csv"></i> Exporter en CSV
                </a>
            </div>

            <!-- TABLEAU -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-slate-100">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-slate-50 text-slate-600 text-xs uppercase font-bold border-b border-slate-200">
                            <tr>
                                <th class="p-4">Nom</th>
                                <th class="p-4">Login</th>
                                <th class="p-4">Famoco_ID</th>
                                <th class="p-4">Région</th>
                                <th class="p-4">Enseigne</th>
                                <th class="p-4">État Traitement</th> 
                                <th class="p-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach ($agents as $a): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="p-4 font-semibold text-slate-800"><?= htmlspecialchars($a['nom']) ?></td>
                                <td class="p-4 text-slate-600"><?= htmlspecialchars($a['login']) ?></td>
                                <td class="p-4 text-slate-600"><?= htmlspecialchars($a['famoco_id']) ?></td>
                                <td class="p-4 text-slate-600"><?= htmlspecialchars($a['region']) ?></td>
                                <td class="p-4 text-slate-600"><?= htmlspecialchars($a['nom_enseigne']) ?></td>
                                <td class="p-4">
                                    <?php 
                                        $status_val = isset($a['Etat_traitement']) ? (int)$a['Etat_traitement'] : (isset($a['etat_traitement']) ? (int)$a['etat_traitement'] : 0);
                                        
                                        if ($status_val === 0) {
                                            echo '<span class="px-3 py-1 rounded-full text-[10px] uppercase font-bold bg-amber-100 text-amber-700 border border-amber-200">En attente</span>';
                                        } elseif ($status_val === 1) {
                                            echo '<span class="px-3 py-1 rounded-full text-[10px] uppercase font-bold bg-red-100 text-red-700 border border-red-200">En attente BO</span>';
                                        } else {
                                            echo '<span class="px-3 py-1 rounded-full text-[10px] uppercase font-bold bg-green-100 text-green-700 border border-green-200">Validé</span>';
                                        }
                                    ?>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-3">
                                        <!-- 1. BOUTON VOIR (Ouvre le modal de détail) -->
                                        <a href="index.php?route=dashboard&view=<?= $a['id'] ?>" class="text-blue-500 hover:text-blue-700 transition-colors p-1 text-base" title="Voir les détails">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <!-- 2. BOUTON ÉDITER -->
                                        <a href="index.php?route=dashboard&edit=<?= $a['id'] ?>" class="text-amber-500 hover:text-amber-700 transition-colors p-1 text-base" title="Éditer l'agent">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <!-- 3. BOUTON TÉLÉCHARGER PDF -->
                                        <a href="index.php?route=agent_pdf&id=<?= $a['id'] ?>" target="_blank" class="text-slate-600 hover:text-orange-600 transition-colors p-1 text-base" title="Télécharger Fiche PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>

                                        <!-- 4. BOUTON SUPPRIMER -->
                                        <a href="index.php?route=delete_agent&id=<?= $a['id'] ?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement l\'agent <?= htmlspecialchars($a['nom']) ?> ?');" class="text-red-500 hover:text-red-700 transition-colors p-1 text-base" title="Supprimer">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="flex justify-center gap-2 p-4 border-t border-slate-100 flex-wrap bg-slate-50">
                    <?php for ($i = 1; $i <= $totalPages; $i++): 
                        $paramsLink = array_merge($_GET, ['p' => $i]);
                    ?>
                        <a href="?<?= http_build_query($paramsLink) ?>" 
                           class="px-3 py-1 rounded border text-sm font-medium transition-colors <?= $page == $i ? 'bg-orange-600 text-white border-orange-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- MODAL VUE AGENT -->
            <!-- MODAL DE VUE DE L'AGENT -->
            <?php 
            if (isset($_GET['view'])): 
                $view_id = (int)$_GET['view'];
                $stmtView = $db->prepare("SELECT a.*, u.username as creator_name FROM agents a LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ?");
                $stmtView->execute([$view_id]);
                $agentView = $stmtView->fetch(PDO::FETCH_ASSOC);

                if ($agentView):
                    // Logique pour le badge d'état (Couleur et Icône)
                    $etatValue = isset($agentView['Etat_traitement']) ? (int)$agentView['Etat_traitement'] : (isset($agentView['etat_traitement']) ? (int)$agentView['etat_traitement'] : 0);
                    
                    $etatBadge = '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-bold bg-amber-100 text-amber-700"><i class="fas fa-clock"></i> En attente</span>';
                    if ($etatValue === 2) {
                        $etatBadge = '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-bold bg-emerald-100 text-emerald-700"><i class="fas fa-check-circle"></i> Validé</span>';
                    } elseif ($etatValue === 1) {
                        $etatBadge = '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-bold bg-red-100 text-red-700"><i class="fas fa-exclamation-circle"></i> En attente BO</span>';
                    }
            ?>
            <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-4 md:p-6 z-[200]">
                <div class="bg-white rounded-3xl p-6 md:p-10 max-w-4xl w-full max-h-[95vh] overflow-y-auto relative shadow-2xl">
                    
                    <!-- EN-TÊTE : Photo, Titre et Bouton PDF -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
                        <div class="flex items-center gap-5">
                            <img src="../old/uploads/<?= htmlspecialchars($agentView['photo_path']) ?>" alt="Photo Profil" class="w-20 h-20 rounded-full object-cover border border-slate-200 shadow-sm" onerror="this.src='https://via.placeholder.com/150?text=Absente'">
                            <div>
                                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Fiche Agent</h2>
                                <p class="text-slate-500 mt-1 font-medium text-sm">
                                    Nom Complet : <span class="text-orange-600 font-bold uppercase"><?= htmlspecialchars($agentView['nom']) ?></span>
                                </p>
                            </div>
                        </div>
                        
                        <a href="index.php?route=agent_pdf&id=<?= $agentView['id'] ?>" target="_blank" class="bg-[#0ea5e9] hover:bg-sky-600 text-white font-bold py-2.5 px-5 rounded-lg flex items-center gap-2 transition-colors text-sm shadow-md shadow-sky-500/30">
                            <i class="fas fa-file-pdf"></i> Télécharger PDF
                        </a>
                    </div>

                    <!-- GRILLE D'INFORMATIONS (Fond gris) -->
                    <div class="bg-slate-50 rounded-2xl p-8 mb-10 border border-slate-100">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-y-8 gap-x-6">
                            <!-- Ligne 1 -->
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1.5">Login</p>
                                <p class="text-base font-bold text-slate-800"><?= htmlspecialchars($agentView['login']) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1.5">CNI</p>
                                <p class="text-base font-bold text-slate-800"><?= htmlspecialchars($agentView['cni_number']) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1.5">Canal</p>
                                <p class="text-base font-bold text-slate-800"><?= htmlspecialchars($agentView['type_enseigne']) ?></p>
                            </div>

                            <!-- Ligne 2 -->
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1.5">Enseigne</p>
                                <p class="text-base font-bold text-slate-800"><?= htmlspecialchars($agentView['nom_enseigne']) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1.5">Région Commerciale</p>
                                <p class="text-base font-bold text-slate-800"><?= htmlspecialchars($agentView['region']) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1.5">Région Admin.</p>
                                <p class="text-base font-bold text-slate-800"><?= htmlspecialchars($agentView['region_admin']) ?></p>
                            </div>

                            <!-- Ligne 3 -->
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1.5">Date Création</p>
                                <p class="text-base font-bold text-slate-800"><?= htmlspecialchars($agentView['date_created']) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1.5">Créé par</p>
                                <p class="text-base font-bold text-slate-800"><?= htmlspecialchars($agentView['creator_name'] ?? 'Système') ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1.5">État Traitement</p>
                                <div class="mt-1"><?= $etatBadge ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION DOCUMENTS CNI -->
                    <div>
                        <div class="flex items-center gap-2 mb-5 text-slate-400 font-bold uppercase text-[11px] tracking-widest border-b border-slate-100 pb-3">
                            <i class="fas fa-id-card text-base"></i> DOCUMENTS CNI
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <p class="text-xs text-slate-400 mb-3 font-medium">Face Avant</p>
                                <img src="../old/cni_pictures/<?= htmlspecialchars($agentView['cni_front']) ?>" class="w-full h-auto rounded-xl border border-slate-200 shadow-sm" alt="CNI Recto" onerror="this.src='https://via.placeholder.com/500x300?text=Absente'">
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 mb-3 font-medium">Face Arrière</p>
                                <img src="../old/cni_pictures/<?= htmlspecialchars($agentView['cni_back']) ?>" class="w-full h-auto rounded-xl border border-slate-200 shadow-sm" alt="CNI Verso" onerror="this.src='https://via.placeholder.com/500x300?text=Absente'">
                            </div>
                        </div>
                    </div>

                    <!-- BOUTON FERMER -->
                    <div class="mt-10">
                        <a href="index.php?route=dashboard" class="block w-full text-center py-4 bg-[#1e293b] text-white font-bold rounded-xl hover:bg-black transition-colors text-sm shadow-lg shadow-slate-900/20">
                            <i class="fas fa-times mr-2"></i> Fermer la fiche
                        </a>
                    </div>

                </div>
            </div>
            <?php endif; endif; ?>
            <!-- END MODAL VUE AGENT -->
            

            <!-- MODAL EDITION AGENT -->
            <?php 
            if (isset($_GET['edit'])): 
                $edit_id = (int)$_GET['edit'];
                // On récupère les infos de l'agent à modifier
                $stmtEdit = $db->prepare("SELECT * FROM agents WHERE id = ?");
                $stmtEdit->execute([$edit_id]);
                $agentEdit = $stmtEdit->fetch(PDO::FETCH_ASSOC);

                if ($agentEdit):
            ?>
            <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-6 z-[200]">
                <div class="bg-white rounded-2xl p-8 max-w-2xl w-full max-h-[90vh] overflow-y-auto relative shadow-2xl">
                    <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                        <i class="fas fa-user-edit text-orange-600"></i> Modifier l'Agent
                    </h2>

                    <form action="index.php?route=agent_update" method="POST" class="space-y-4">
                        <input type="hidden" name="agent_id" value="<?= $agentEdit['id'] ?>">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nom Complet</label>
                                <input type="text" name="nom" value="<?= htmlspecialchars($agentEdit['nom']) ?>" required class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Login (Téléphone)</label>
                                <input type="text" name="login" value="<?= htmlspecialchars($agentEdit['login']) ?>" required class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Famoco ID</label>
                                <input type="text" name="famoco_id" value="<?= htmlspecialchars($agentEdit['famoco_id']) ?>" class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Numéro CNI</label>
                                <input type="text" name="cni_number" value="<?= htmlspecialchars($agentEdit['cni_number']) ?>" class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Région Commerciale</label>
                                <input type="text" name="region" value="<?= htmlspecialchars($agentEdit['region']) ?>" class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Région Administrative</label>
                                <input type="text" name="region_admin" value="<?= htmlspecialchars($agentEdit['region_admin']) ?>" class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Canal (Type Enseigne)</label>
                                <input type="text" name="type_enseigne" value="<?= htmlspecialchars($agentEdit['type_enseigne']) ?>" class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nom Enseigne</label>
                                <input type="text" name="nom_enseigne" value="<?= htmlspecialchars($agentEdit['nom_enseigne']) ?>" class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-orange-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">État de Traitement</label>
                            <select name="etat_traitement" class="w-full p-2.5 border border-slate-300 rounded-lg outline-none focus:border-orange-500">
                                <option value="0" <?= $agentEdit['Etat_traitement'] == 0 ? 'selected' : '' ?>>En attente (0)</option>
                                <option value="1" <?= $agentEdit['Etat_traitement'] == 1 ? 'selected' : '' ?>>En attente BO (1)</option>
                                <option value="2" <?= $agentEdit['Etat_traitement'] == 2 ? 'selected' : '' ?>>Validé (2)</option>
                            </select>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
                            <a href="index.php?route=dashboard" class="px-5 py-2.5 bg-slate-200 text-slate-700 font-bold rounded-lg hover:bg-slate-300 transition-colors">Annuler</a>
                            <button type="submit" class="px-5 py-2.5 bg-orange-600 text-white font-bold rounded-lg hover:bg-orange-700 transition-colors">Enregistrer les modifications</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; endif; ?>

        </div>
    </main>

    <script>
    function showLoader(event) {
        const overlay = document.getElementById('fullLoader');
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
    }
    </script>
</body>
</html>