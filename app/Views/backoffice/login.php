<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orange OCM - Admin Back Office</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @keyframes pulse-glow {
            0%, 100% { transform: scale(1); filter: drop-shadow(0 0 15px rgba(249, 115, 22, 0.4)); }
            50% { transform: scale(1.08); filter: drop-shadow(0 0 25px rgba(249, 115, 22, 0.8)); }
        }
        .animate-pulse-glow {
            animation: pulse-glow 2s infinite ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col lg:flex-row bg-slate-900 overflow-x-hidden">

    <!-- OVERLAY LOADING APP / SPLASH SCREEN -->
    <div id="appLoader" class="fixed inset-0 bg-slate-950 z-[999] flex flex-col items-center justify-center transition-opacity duration-700">
        <div class="relative flex flex-col items-center">
            <div class="absolute -inset-4 rounded-full border-2 border-orange-500/20 border-t-orange-500 animate-spin"></div>
            
            <div class="bg-slate-900 p-6 rounded-3xl backdrop-blur-xl border border-orange-500/30 animate-pulse-glow mb-6 shadow-[0_0_30px_rgba(249,115,22,0.15)]">
                <i class="fas fa-user-shield text-5xl text-orange-500"></i>
            </div>
        </div>

        <h2 class="text-2xl font-bold text-white tracking-wide mb-1">Orange Cameroun</h2>
        <p class="text-xs uppercase tracking-widest text-orange-500 font-bold mb-8">Administration KYA</p>

        <div class="w-48 h-1.5 bg-slate-800 rounded-full overflow-hidden">
            <div class="w-full h-full bg-gradient-to-r from-orange-600 to-amber-400 rounded-full animate-[pulse_1s_infinite]"></div>
        </div>
    </div>

    <!-- PANNEAU GAUCHE SOMBRE (DESKTOP) -->
    <div class="hidden lg:flex w-1/2 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex-col justify-center items-center text-white p-12 relative overflow-hidden border-r border-slate-700/50">
        
        <!-- Éclairage de fond subtil orange -->
        <div class="absolute -left-20 -top-20 w-80 h-80 bg-orange-600/10 rounded-full blur-3xl"></div>
        <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-slate-700/30 rounded-full blur-2xl"></div>
        
        <div class="mb-10 text-center z-10">
            <div class="bg-slate-800 p-5 rounded-2xl inline-block mb-4 border border-slate-700 shadow-xl shadow-slate-950/50">
                <i class="fas fa-cogs text-4xl text-orange-500"></i>
            </div>
            <h1 class="text-3xl font-bold">Orange Cameroun</h1>
            <p class="mt-2 text-orange-500 font-bold tracking-widest uppercase text-sm">Interface Back Office</p>
            <p class="opacity-70 text-sm mt-2">Gestion centralisée des agents d'acquisition</p>
        </div>

        <div class="space-y-4 w-full max-w-sm z-10">
            <div class="flex items-center gap-4 bg-slate-800/60 p-4 rounded-xl border border-slate-700 transition hover:bg-slate-800">
                <div class="w-10 h-10 rounded-lg bg-orange-500/10 flex items-center justify-center">
                    <i class="fas fa-users-cog text-orange-500"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-slate-200">Supervision des effectifs</h3>
                    <p class="text-xs text-slate-400">Gestion des accès et rôles</p>
                </div>
            </div>
            <div class="flex items-center gap-4 bg-slate-800/60 p-4 rounded-xl border border-slate-700 transition hover:bg-slate-800">
                <div class="w-10 h-10 rounded-lg bg-orange-500/10 flex items-center justify-center">
                    <i class="fas fa-chart-line text-orange-500"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-slate-200">Monitoring Terrain</h3>
                    <p class="text-xs text-slate-400">Suivi des validations KYA</p>
                </div>
            </div>
        </div>
    </div>

    <!-- FORMULAIRE (DROITE) -->
    <div class="w-full lg:w-1/2 flex flex-col justify-center items-center p-6 lg:p-8 bg-white min-h-screen">
        
        <!-- En-tête sombre pour version MOBILE -->
        <div class="lg:hidden w-full bg-slate-900 p-8 pb-12 flex flex-col items-center text-white shadow-lg rounded-b-[2rem] mb-6 relative overflow-hidden border-b-2 border-orange-500">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-orange-600/20 via-slate-900 to-slate-900"></div>
            <div class="bg-slate-800 p-4 rounded-2xl mb-4 border border-slate-700 z-10">
                <i class="fas fa-user-shield text-orange-500 text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold z-10">Orange Cameroun</h1>
            <p class="text-orange-500 font-bold uppercase tracking-widest text-[10px] mt-1 z-10">Back Office Admin</p>
        </div>

        <div class="w-full max-w-sm">
            <h2 class="text-2xl font-bold text-slate-800 mb-2">Connexion Sécurisée</h2>
            <p class="text-sm text-slate-500 mb-6">Veuillez vous authentifier pour accéder à l'administration.</p>
                
            <?php if (isset($erreur)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-xl shadow-sm">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <p class="text-sm text-red-700 font-medium"><?php echo $erreur; ?></p>
                    </div>
                </div>
            <?php endif; ?>
           <!-- Remplacez le /new/public/... par : -->
            <form action="index.php?route=login" method="POST" id="loginForm" class="space-y-5">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Identifiant administrateur</label>
                    <div class="relative">
                        <i class="fas fa-user-shield absolute left-3.5 top-4 text-slate-400"></i>
                        <input type="text" name="identifiant" required placeholder="admin.ocm" class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition bg-slate-50 focus:bg-white placeholder:text-slate-400">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Mot de passe</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3.5 top-4 text-slate-400"></i>
                        <input type="password" name="password" required placeholder="••••••••" class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition bg-slate-50 focus:bg-white placeholder:text-slate-400">
                    </div>
                </div>
                <button type="submit" id="btnSubmit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-3.5 rounded-xl transition shadow-lg shadow-slate-200 flex items-center justify-center gap-2 mt-2">
                    <span id="btnText">Accéder au Back Office</span>
                    <i id="btnSpinner" class="fas fa-circle-notch animate-spin hidden"></i>
                </button>
            </form>
            
            <!-- Mentions légales / sécurité (optionnel, renforce l'aspect pro) -->
            <div class="mt-8 text-center border-t border-slate-100 pt-6">
                <p class="text-xs text-slate-400 flex items-center justify-center gap-2">
                    <i class="fas fa-shield-alt text-slate-300"></i> Accès restreint au personnel autorisé
                </p>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const loader = document.getElementById('appLoader');
                loader.classList.add('opacity-0');
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 700);
            }, 1200); // Temps de chargement légèrement réduit
        });

        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('btnText').textContent = 'Authentification...';
            document.getElementById('btnSpinner').classList.remove('hidden');
            document.getElementById('btnSubmit').disabled = true;
            document.getElementById('btnSubmit').classList.add('opacity-80', 'cursor-not-allowed');
        });
    </script>
</body>
</html>