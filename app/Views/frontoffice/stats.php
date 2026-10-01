<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>UltraTrack - Mes Statistiques</title>
</head>
<body class="bg-slate-100 min-h-screen flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col min-h-screen shadow-2xl flex-shrink-0">
        <div class="p-6 bg-slate-950 flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center gap-3">
                <i class="fas fa-id-badge text-orange-500 text-2xl"></i>
                <span class="text-white text-xl font-bold tracking-wider">UltraTrack</span>
            </div>
        </div>

        <div class="p-4 bg-slate-800/50 mx-4 my-4 rounded-xl border border-slate-700/50">
            <p class="text-[10px] uppercase font-bold text-slate-400">Superviseur Connecté</p>
            <p class="text-sm font-extrabold text-white mt-0.5"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></p>
            <span class="inline-block mt-1 px-2 py-0.5 bg-orange-500/20 text-orange-400 text-[10px] font-bold rounded">
                Flotte : <?= htmlspecialchars($_SESSION['flotte'] ?? 'Générale') ?>
            </span>
        </div>

        <nav class="flex-1 px-4 space-y-2 overflow-y-auto">
            <p class="px-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Menu Principal</p>
            
            <a href="index.php?route=front_register" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 text-slate-400 hover:text-white rounded-xl transition-colors font-medium">
                <i class="fas fa-home w-5 text-center"></i>
                <span>Enrôlement</span>
            </a>

            <a href="index.php?route=front_stats" class="flex items-center gap-3 px-4 py-3 bg-orange-600 text-white rounded-xl shadow-md transition-colors font-medium">
                <i class="fas fa-chart-line w-5 text-center"></i>
                <span>Statistiques</span>
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800">
            <a href="index.php?route=logout" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-red-500/10 rounded-xl transition-colors text-sm font-bold">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </aside>

    <!-- CONTENU PRINCIPAL -->
    <main class="flex-1 flex flex-col h-screen overflow-y-auto">

        <header class="bg-white p-6 shadow-sm border-b border-slate-200 sticky top-0 z-10">
            <h1 class="text-xl font-extrabold text-slate-800">Mes Performances Terrain</h1>
            <p class="text-xs text-orange-600 font-semibold mt-0.5">Statistiques personnelles de recensement</p>
        </header>

        <div class="p-6 max-w-5xl mx-auto w-full space-y-6">

            <!-- CARTES DE METRIQUES -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase font-bold text-slate-400">Total Enregistrés</p>
                        <p class="text-3xl font-black text-slate-800 mt-1"><?= $totalMyAgents ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center text-xl">
                        <i class="fas fa-users"></i>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase font-bold text-slate-400">Dossiers Validés</p>
                        <p class="text-3xl font-black text-emerald-600 mt-1"><?= $totalValide ?></p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xl">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase font-bold text-slate-400">En Attente Back-Office</p>
                        <p class="text-3xl font-black text-amber-500 mt-1"><?= $totalAttente ?></p>
                    </div>
                    <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-xl flex items-center justify-center text-xl">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>

            </div>

            <!-- DERNIERS AGENTS ENREGISTRÉS -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-sm font-extrabold text-slate-800">Mes 10 Derniers Recensements</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase font-bold border-b border-slate-100">
                            <tr>
                                <th class="p-4">Nom Agent</th>
                                <th class="p-4">Login</th>
                                <th class="p-4">Enseigne</th>
                                <th class="p-4">Région</th>
                                <th class="p-4">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($myRecentAgents)): foreach ($myRecentAgents as $a): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($a['nom']) ?></td>
                                <td class="p-4 font-medium text-slate-600"><?= htmlspecialchars($a['login']) ?></td>
                                <td class="p-4 text-slate-600"><?= htmlspecialchars($a['nom_enseigne']) ?></td>
                                <td class="p-4 text-slate-600"><?= htmlspecialchars($a['region']) ?></td>
                                <td class="p-4 text-slate-400 text-xs"><?= htmlspecialchars($a['date_created']) ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="5" class="p-6 text-center text-slate-400">Aucun agent recensé pour le moment.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

</body>
</html>