<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>UltraTrack - Connexion</title>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl overflow-hidden">
        
        <!-- EN-TÊTE ORANGE / SLATE -->
        <div class="bg-slate-950 p-8 text-center border-b border-slate-800 relative">
            <div class="w-16 h-16 bg-orange-600 rounded-2xl mx-auto flex items-center justify-center text-white text-3xl shadow-lg shadow-orange-600/30 mb-4">
                <i class="fas fa-id-badge"></i>
            </div>
            <h1 class="text-2xl font-black text-white tracking-wider">ULTRACK</h1>
            <p class="text-slate-400 text-xs mt-1">Plateforme d'enrôlement des commandos & Gestion Terrain</p>
        </div>

        <!-- FORMULAIRE -->
        <div class="p-8">

            <?php if (isset($error) || !empty($_SESSION['oidc_error'] ?? '')): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-xl text-xs font-bold mb-6 flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-base"></i>
                    <span><?= htmlspecialchars($error ?? ($_SESSION['oidc_error'] ?? '')) ?></span>
                </div>
                <?php unset($_SESSION['oidc_error']); ?>
            <?php endif; ?>

            <?php $oidcEnabled = function_exists('oidcEnabled') && oidcEnabled(); ?>
            <?php if ($oidcEnabled): ?>
                <div class="mb-6">
                    <a href="index.php?route=oidc_login" class="w-full inline-flex items-center justify-center gap-2 py-3 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-xl transition-all shadow-lg shadow-sky-600/20 text-sm">
                        <i class="fas fa-shield-alt"></i>
                        Se connecter avec Keycloak
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!$oidcEnabled): ?>
            <form action="index.php?route=login" method="POST" class="space-y-5">
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Nom d'utilisateur</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" name="username" required placeholder="Nom d'utilisateur" class="w-full pl-10 pr-4 py-3.5 border border-slate-200 rounded-xl outline-none focus:border-orange-500 text-sm bg-slate-50 focus:bg-white font-medium transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Mot de passe</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="password" required placeholder="••••••••" class="w-full pl-10 pr-4 py-3.5 border border-slate-200 rounded-xl outline-none focus:border-orange-500 text-sm bg-slate-50 focus:bg-white font-medium transition-colors">
                    </div>
                </div>

                <button type="submit" class="w-full py-4 bg-orange-600 hover:bg-orange-700 text-white font-extrabold rounded-xl transition-all shadow-lg shadow-orange-600/30 text-sm flex items-center justify-center gap-2 mt-4">
                    <i class="fas fa-sign-in-alt"></i> Se Connecter
                </button>

            </form>
            <?php endif; ?>

            <div class="mt-8 text-center border-t border-slate-100 pt-6">
                <p class="text-xs text-slate-400">OCM - Direction Distribution — Ultrack v2</p>
            </div>

        </div>

    </div>

</body>
</html>