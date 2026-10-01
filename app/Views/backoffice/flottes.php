<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>UltraTrack - Overview Flottes</title>
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
            <a href="index.php?route=flottes" class="flex items-center gap-3 px-4 py-3  bg-orange-600 hover:bg-slate-800 hover:text-white rounded-lg transition-colors">
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

    <!-- CONTENU PRINCIPAL -->
    <main class="flex-1 flex flex-col h-screen overflow-y-auto">
        <header class="bg-white p-8 shadow-sm border-b border-slate-100 z-10 sticky top-0">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Overview des Flottes</h1>
            <p class="text-orange-600 text-sm font-medium mt-1">Répartition globale des effectifs sur le terrain</p>
        </header>

        <div class="p-8 max-w-7xl mx-auto w-full">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <!-- CARTE DD -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 relative overflow-hidden transition-transform hover:-translate-y-1">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 rounded-full bg-orange-50 opacity-50"></div>
                    <div class="relative z-10 flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Flotte</p>
                            <h3 class="text-2xl font-extrabold text-slate-800 mt-1">DD</h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center text-xl shadow-inner">
                            <i class="fas fa-briefcase"></i>
                        </div>
                    </div>
                    
                    <div class="mt-8">
                        <p class="text-6xl font-black text-slate-900 tracking-tighter"><?= $stats['DD']['total'] ?></p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mt-2">Effectif Global</p>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100 flex flex-col gap-3">
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2 text-slate-500">
                                <i class="fas fa-user-tie"></i> <span>Superviseurs</span>
                            </div>
                            <span class="font-extrabold text-slate-800 bg-slate-100 px-3 py-1 rounded-lg"><?= $stats['DD']['users'] ?></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2 text-slate-500">
                                <i class="fas fa-users"></i> <span>Agents Terrain</span>
                            </div>
                            <span class="font-extrabold text-slate-800 bg-orange-50 text-orange-700 px-3 py-1 rounded-lg"><?= $stats['DD']['agents'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- CARTE B2B -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 relative overflow-hidden transition-transform hover:-translate-y-1">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 rounded-full bg-blue-50 opacity-50"></div>
                    <div class="relative z-10 flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Flotte</p>
                            <h3 class="text-2xl font-extrabold text-slate-800 mt-1">B2B</h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl shadow-inner">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                    
                    <div class="mt-8">
                        <p class="text-6xl font-black text-slate-900 tracking-tighter"><?= $stats['B2B']['total'] ?></p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mt-2">Effectif Global</p>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100 flex flex-col gap-3">
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2 text-slate-500">
                                <i class="fas fa-user-tie"></i> <span>Superviseurs</span>
                            </div>
                            <span class="font-extrabold text-slate-800 bg-slate-100 px-3 py-1 rounded-lg"><?= $stats['B2B']['users'] ?></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2 text-slate-500">
                                <i class="fas fa-users"></i> <span>Agents Terrain</span>
                            </div>
                            <span class="font-extrabold text-slate-800 bg-blue-50 text-blue-700 px-3 py-1 rounded-lg"><?= $stats['B2B']['agents'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- CARTE OMCM -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 relative overflow-hidden transition-transform hover:-translate-y-1">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 rounded-full bg-emerald-50 opacity-50"></div>
                    <div class="relative z-10 flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Flotte</p>
                            <h3 class="text-2xl font-extrabold text-slate-800 mt-1">OMCM</h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl shadow-inner">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                    </div>
                    
                    <div class="mt-8">
                        <p class="text-6xl font-black text-slate-900 tracking-tighter"><?= $stats['OMCM']['total'] ?></p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mt-2">Effectif Global</p>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100 flex flex-col gap-3">
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2 text-slate-500">
                                <i class="fas fa-user-tie"></i> <span>Superviseurs</span>
                            </div>
                            <span class="font-extrabold text-slate-800 bg-slate-100 px-3 py-1 rounded-lg"><?= $stats['OMCM']['users'] ?></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2 text-slate-500">
                                <i class="fas fa-users"></i> <span>Agents Terrain</span>
                            </div>
                            <span class="font-extrabold text-slate-800 bg-emerald-50 text-emerald-700 px-3 py-1 rounded-lg"><?= $stats['OMCM']['agents'] ?></span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
</body>
</html>