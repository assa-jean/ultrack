<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>UltraTrack - Gestion des Accès</title>
</head>
<body class="bg-slate-50 min-h-screen flex h-screen overflow-hidden">
    
    <!-- SIDEBAR -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col min-h-screen shadow-2xl flex-shrink-0">
        <div class="p-6 bg-slate-950 flex items-center gap-3 border-b border-slate-800">
            <i class="fas fa-id-badge text-orange-500 text-2xl"></i>Welcome,<br>
            <span class="text-white text-xl font-bold tracking-wider"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <p class="px-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Tableau de bord</p>
            
            <a href="index.php?route=dashboard" class="flex items-center gap-3 px-4 py-3  text-white rounded-lg shadow-md transition-colors">
                <i class="fas fa-home w-5 text-center"></i>
                <span class="font-medium">Accueil</span>
            </a>
            <a href="index.php?route=flottes" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-lg transition-colors">
                <i class="fas fa-truck w-5 text-center"></i>
                <span class="font-medium">Overview Flottes</span>
            </a>
           
            <a href="index.php?route=roles" class="flex items-center gap-3 px-4 py-3 bg-orange-600 hover:bg-slate-800 hover:text-white rounded-lg transition-colors">
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
    <!-- CONTENU PRINCIPAL -->
    <main class="flex-1 flex flex-col h-screen overflow-y-auto">
        
        <header class="bg-white p-8 shadow-sm border-b border-slate-100 z-10">
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Gestion des Accès</h1>
            <p class="text-orange-600 text-sm font-medium mt-1">Administration des utilisateurs et superviseurs</p>
        </header>

        <div class="p-8 max-w-full">
            
            <?php 
                // Calculs rapides pour les cartes du haut
                $totalUsers = count($users);
                // Compter les enseignes uniques
                $enseignesSet = array_filter(array_unique(array_column($users, 'enseigne')));
                $totalEnseignes = count($enseignesSet);
            ?>

            <!-- CARTES STATISTIQUES DU HAUT -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Carte Total Superviseurs -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Total Superviseurs</p>
                        <p class="text-4xl font-extrabold text-slate-800 mt-2"><?= $totalUsers ?></p>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center text-xl shadow-inner">
                        <i class="fas fa-users-cog"></i>
                    </div>
                </div>

                <!-- Carte Total Enseignes -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Total Enseignes</p>
                        <p class="text-4xl font-extrabold text-slate-800 mt-2"><?= $totalEnseignes ?></p>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shadow-inner">
                        <i class="fas fa-store"></i>
                    </div>
                </div>
            </div>

            <!-- EN-TÊTE DE LA LISTE + BOUTON AJOUTER -->
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-slate-800">Liste des utilisateurs</h2>
                <button onclick="document.getElementById('addSupervisorModal').classList.remove('hidden')" class="bg-slate-900 text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-orange-600 transition-colors shadow-md flex items-center gap-2">
                    <i class="fas fa-plus"></i> Ajouter un recruteur
                </button>
            </div>

            <?php foreach (['keycloak_notice' => 'emerald', 'keycloak_error' => 'red'] as $flashKey => $flashColor): ?>
                <?php if (!empty($_SESSION[$flashKey])): ?>
                    <div class="mb-5 rounded-lg border border-<?= $flashColor ?>-200 bg-<?= $flashColor ?>-50 px-4 py-3 text-sm text-<?= $flashColor ?>-800">
                        <?= htmlspecialchars((string) $_SESSION[$flashKey]) ?>
                    </div>
                    <?php unset($_SESSION[$flashKey]); ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- POPUP / BANDEAU ALERTE SUCCÈS -->
                <?php if (isset($_GET['success'])): ?>
                    <div id="successAlert" class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-2xl shadow-sm flex items-center justify-between transition-all duration-500">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <p class="font-bold text-sm">Opération réussie !</p>
                                <p class="text-xs text-emerald-600 mt-0.5">
                                    <?= $_GET['success'] == '1' ? 'Le compte recruteur a été traité.' : 'Les modifications de l\'utilisateur ont été enregistrées avec succès.' ?>
                                </p>
                            </div>
                        </div>
                        <button onclick="document.getElementById('successAlert').style.display='none'" class="text-emerald-400 hover:text-emerald-700 font-bold text-sm">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Disparition automatique après 4 secondes -->
                    <script>
                        setTimeout(() => {
                            const alertBox = document.getElementById('successAlert');
                            if (alertBox) {
                                alertBox.style.opacity = '0';
                                setTimeout(() => alertBox.style.display = 'none', 500);
                            }
                        }, 4000);
                    </script>
                <?php endif; ?>

            <!-- TABLEAU DES UTILISATEURS -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-slate-100">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-slate-50/50 text-slate-400 text-[11px] uppercase tracking-wider font-bold border-b border-slate-100">
                            <tr>
                                <th class="p-5">ID</th>
                                <th class="p-5">Nom d'utilisateur</th>
                                <th class="p-5">Téléphone</th>
                                <th class="p-5">Flotte</th>
                                <th class="p-5">Rôle</th>
                                <th class="p-5">Date de création</th>
                                <th class="p-5">Pièces CNI</th>
                                <th class="p-5 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php if (!empty($users)): foreach ($users as $u): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-5 font-bold text-slate-600">#<?= $u['id'] ?></td>
                                <td class="p-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                                            <?= substr($u['username'], 0, 1) ?>
                                        </div>
                                        <span class="font-bold text-slate-800"><?= htmlspecialchars($u['username']) ?></span>
                                    </div>
                                </td>
                                
                                <td class="p-5 font-medium text-slate-600"><?= htmlspecialchars($u['telephone'] ?? '-') ?></td>
                                <td class="p-5">
                                    <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-orange-50 text-orange-600">
                                        <?= htmlspecialchars($u['flotte'] ?? '-') ?>
                                    </span>
                                </td>
                                <td class="p-5">
                                    <span class="px-3 py-1 rounded-full text-[10px] uppercase font-bold bg-blue-50 text-blue-600 border border-blue-100">
                                        <?= htmlspecialchars($u['role'] ?? 'USER') ?>
                                    </span>
                                </td>
                                <td class="p-5 text-slate-500 text-xs font-medium">
                                    <i class="far fa-calendar-alt mr-1.5 text-slate-400"></i> <?= htmlspecialchars($u['created_at'] ?? '-') ?>
                                </td>
                                <td class="p-5">
                                    <div class="flex items-center gap-2">
                                        <?php if (!empty($u['cni_recto'])): ?>
                                            <a href="#" class="px-2.5 py-1 rounded-md bg-orange-50 text-orange-600 text-xs font-bold hover:bg-orange-100 transition-colors flex items-center gap-1">
                                                <i class="fas fa-id-card"></i> Recto
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-xs">Recto -</span>
                                        <?php endif; ?>

                                        <?php if (!empty($u['cni_verso'])): ?>
                                            <a href="#" class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-600 text-xs font-bold hover:bg-slate-200 transition-colors flex items-center gap-1">
                                                <i class="fas fa-id-card"></i> Verso
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-xs">Verso -</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-5 text-center">
                                    <div class="flex justify-center items-center gap-3">
                                        <?php if (empty($u['keycloak_sub'])): ?>
                                            <a href="index.php?route=roles&edit=<?= $u['id'] ?>" class="text-blue-500 hover:text-blue-700 transition-colors p-1" title="Éditer le profil local">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (($u['role'] ?? '') === 'RECRUTEUR' && !empty($u['keycloak_sub'])): ?>
                                            <form action="index.php?route=resend_recruteur_email" method="POST" class="inline">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="text-emerald-600 hover:text-emerald-800 transition-colors p-1" title="Renvoyer le lien de définition du mot de passe">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                            </form>
                                            <form action="index.php?route=deactivate_recruteur" method="POST" class="inline" onsubmit="return confirm('Désactiver ce recruteur ?')">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700 transition-colors p-1" title="Désactiver le compte">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="9" class="p-8 text-center text-slate-400">Aucun utilisateur trouvé.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            

            <!-- MODAL AJOUTER UN SUPERVISEUR -->
            <div id="addSupervisorModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm hidden flex items-center justify-center p-6 z-[200]">
                <div class="bg-white rounded-2xl p-8 max-w-xl w-full max-h-[90vh] overflow-y-auto relative shadow-2xl">
                    
                    <div class="flex items-center justify-between mb-6 border-b border-slate-100 pb-4">
                        <h3 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-user-plus text-orange-600"></i> Nouveau recruteur
                        </h3>
                        <button onclick="document.getElementById('addSupervisorModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 font-bold text-xl">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- ENCTYPE OBLIGATOIRE POUR LES FICHIERS -->
                    <form action="index.php?route=add_supervisor" method="POST" enctype="multipart/form-data" class="space-y-4">
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Identifiant du recruteur</label>
                            <input type="text" name="username" required placeholder="Ex: Jean Dupont" class="w-full p-3 border border-slate-200 rounded-xl outline-none focus:border-orange-500 text-sm bg-slate-50 focus:bg-white transition-colors">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Téléphone</label>
                                <input type="text" name="telephone" required placeholder="699943580" class="w-full p-3 border border-slate-200 rounded-xl outline-none focus:border-orange-500 text-sm bg-slate-50 focus:bg-white transition-colors">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Enseigne</label>
                                <input type="text" name="enseigne" placeholder="Ex: BMTC, MYGRACE..." class="w-full p-3 border border-slate-200 rounded-xl outline-none focus:border-orange-500 text-sm bg-slate-50 focus:bg-white transition-colors">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- SELECT FLOTTE -->
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Flotte</label>
                                <select name="flotte" required class="w-full p-3 border border-slate-200 rounded-xl outline-none focus:border-orange-500 text-sm bg-slate-50 focus:bg-white transition-colors font-medium">
                                    <option value="">Sélectionner une flotte</option>
                                    <option value="B2B">B2B</option>
                                    <option value="DD">DD</option>
                                    <option value="OMCM">OMCM</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Adresse e-mail</label>
                            <input type="email" name="email" required placeholder="nom@orange.cm" class="w-full p-3 border border-slate-200 rounded-xl outline-none focus:border-orange-500 text-sm bg-slate-50 focus:bg-white transition-colors">
                        </div>

                        <!-- UPLOAD CNI RECTO & VERSO -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CNI Recto (Image)</label>
                                <input type="file" name="cni_recto" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CNI Verso (Image)</label>
                                <input type="file" name="cni_verso" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-8 pt-4 border-t border-slate-100">
                            <button type="button" onclick="document.getElementById('addSupervisorModal').classList.add('hidden')" class="px-5 py-2.5 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-colors text-sm">
                                Annuler
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-orange-600 text-white font-bold rounded-xl hover:bg-orange-700 transition-colors shadow-md text-sm">
                                Créer l'utilisateur
                            </button>
                        </div>

                    </form>
                </div>
            </div>
            
            <!-- MODAL MODIFIER UN SUPERVISEUR -->
                <?php 
                if (isset($_GET['edit'])): 
                    $edit_user_id = (int)$_GET['edit'];
                    $stmtEditUser = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmtEditUser->execute([$edit_user_id]);
                    $userEdit = $stmtEditUser->fetch(PDO::FETCH_ASSOC);

                    if ($userEdit && empty($userEdit['keycloak_sub'])):
                ?>
                <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-6 z-[200]">
                    <div class="bg-white rounded-2xl p-8 max-w-xl w-full max-h-[90vh] overflow-y-auto relative shadow-2xl">
                        
                        <div class="flex items-center justify-between mb-6 border-b border-slate-100 pb-4">
                            <h3 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                                <i class="fas fa-user-edit text-blue-600"></i> Modifier l'Utilisateur #<?= $userEdit['id'] ?>
                            </h3>
                            <a href="index.php?route=roles" class="text-slate-400 hover:text-slate-600 font-bold text-xl">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>

                        <form action="index.php?route=update_supervisor" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="user_id" value="<?= $userEdit['id'] ?>">

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nom d'utilisateur (Username)</label>
                                <input type="text" name="username" value="<?= htmlspecialchars($userEdit['username']) ?>" required class="w-full p-3 border border-slate-200 rounded-xl outline-none focus:border-blue-500 text-sm bg-slate-50 focus:bg-white transition-colors">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Téléphone</label>
                                    <input type="text" name="telephone" value="<?= htmlspecialchars($userEdit['telephone'] ?? '') ?>" required class="w-full p-3 border border-slate-200 rounded-xl outline-none focus:border-blue-500 text-sm bg-slate-50 focus:bg-white transition-colors">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Enseigne</label>
                                    <input type="text" name="enseigne" value="<?= htmlspecialchars($userEdit['enseigne'] ?? '') ?>" class="w-full p-3 border border-slate-200 rounded-xl outline-none focus:border-blue-500 text-sm bg-slate-50 focus:bg-white transition-colors">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- SELECT FLOTTE -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Flotte</label>
                                    <select name="flotte" required class="w-full p-3 border border-slate-200 rounded-xl outline-none focus:border-blue-500 text-sm bg-slate-50 focus:bg-white transition-colors font-medium">
                                        <option value="B2B" <?= ($userEdit['flotte'] ?? '') === 'B2B' ? 'selected' : '' ?>>B2B</option>
                                        <option value="DD" <?= ($userEdit['flotte'] ?? '') === 'DD' ? 'selected' : '' ?>>DD</option>
                                        <option value="OMCM" <?= ($userEdit['flotte'] ?? '') === 'OMCM' ? 'selected' : '' ?>>OMCM</option>
                                    </select>
                                </div>
                                <!-- SELECT ROLE -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Rôle</label>
                                    <select name="role" required class="w-full p-3 border border-slate-200 rounded-xl outline-none focus:border-blue-500 text-sm bg-slate-50 focus:bg-white transition-colors font-medium">
                                        <option value="Superviseur" <?= ($userEdit['role'] ?? '') === 'Superviseur' ? 'selected' : '' ?>>Superviseur</option>
                                        <option value="Admin" <?= ($userEdit['role'] ?? '') === 'Admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Adresse e-mail</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($userEdit['email'] ?? '') ?>" required class="w-full p-3 border border-slate-200 rounded-xl outline-none focus:border-blue-500 text-sm bg-slate-50 focus:bg-white transition-colors">
                            </div>

                            <!-- UPLOAD CNI RECTO & VERSO -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Modifier CNI Recto</label>
                                    <input type="file" name="cni_recto" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Modifier CNI Verso</label>
                                    <input type="file" name="cni_verso" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 mt-8 pt-4 border-t border-slate-100">
                                <a href="index.php?route=roles" class="px-5 py-2.5 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-colors text-sm">
                                    Annuler
                                </a>
                                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-md text-sm">
                                    Enregistrer les modifications
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
                <?php endif; endif; ?>
                

                
        </div>
    </main>
</body>
</html>