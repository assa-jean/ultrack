<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>UltraTrack - Recensement Agent</title>
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
            
            <a href="index.php?route=front_register" class="flex items-center gap-3 px-4 py-3 bg-orange-600 text-white rounded-xl shadow-md transition-colors font-medium">
                <i class="fas fa-home w-5 text-center"></i>
                <span>Enrôlement</span>
            </a>

            <a href="index.php?route=front_stats" class="flex items-center gap-3 px-4 py-3 text-white rounded-xl shadow-md transition-colors font-medium">
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

        <div class="md:hidden bg-slate-900 text-white p-4 flex justify-between items-center shadow-md sticky top-0 z-50">
            <span class="font-bold tracking-wider">Commando OCM</span>
            <button id="menu-btn" class="text-xl"><i class="fas fa-bars"></i></button>
        </div>

        <div class="p-4 md:p-8 max-w-4xl mx-auto w-full">
            
            <!-- BANNIERE KYA -->
            <div class="bg-gradient-to-br from-orange-600 to-red-700 rounded-3xl p-8 mb-8 text-white shadow-xl">
                <h1 class="text-3xl font-extrabold mb-2">Know Your Acquisition Agents (KYA)</h1>
                <p class="text-orange-100">Veuillez remplir soigneusement le formulaire d'enregistrement.</p>
            </div>

            <!-- ALERTE SUCCÈS -->
            <?php if (isset($_GET['success'])): ?>
                <div id="alertSuccess" class="bg-emerald-500 text-white p-4 rounded-2xl shadow-lg mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-2xl"></i>
                        <div>
                            <p class="font-bold text-sm">Agent Enregistré avec Succès !</p>
                            <p class="text-xs opacity-90">Les données sont enregistrées avec succès.</p>
                        </div>
                    </div>
                    <button onclick="document.getElementById('alertSuccess').style.display='none'" class="text-white font-bold">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- FORMULAIRE -->
            <form action="index.php?route=submit_agent" method="POST" enctype="multipart/form-data" class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200 space-y-6">
                
                <!-- REGION / REGION ADMIN / TYPE ENSEIGNE -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Région</label>
                        <select id="region" name="region" class="w-full p-4 border border-gray-200 rounded-2xl bg-gray-50 outline-none" required>
                            <option value="">-- Choisir --</option>
                            <option value="RC_LITTORAL">RC_LITTORAL</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Région admin</label>
                        <select id="region_admin" name="region_admin" class="w-full p-4 border border-gray-200 rounded-2xl bg-gray-50 outline-none" required>
                            <option value="">-- Sélectionnez une region --</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Type d'enseigne</label>
                        <div class="flex gap-4 pt-2">
                            <label class="flex items-center gap-2 font-medium">
                                <input type="radio" name="type_enseigne" value="PARTNER" required checked> PARTNER
                            </label>
                        </div>
                    </div>
                </div>

                <!-- NOM DE L'ENSEIGNE -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nom de l'enseigne</label>
                    <select id="nom_enseigne" name="nom_enseigne" class="w-full p-4 border border-gray-200 rounded-2xl bg-gray-50 outline-none" required>
                        <option value="">-- Sélectionnez type d'enseigne --</option>
                    </select>
                </div>

                <!-- DOCUMENTS A FOURNIR (UPLOADS) -->
                <div class="border-t pt-6 space-y-4">
                    <label class="block text-sm font-bold text-gray-800">Documents à fournir</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Prendre votre photo</label>
                            <input type="file" name="photo" accept="image/*" capture="user" class="w-full p-3 text-sm border rounded-xl" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">CNI Recto</label>
                            <input type="file" name="cni_front" accept="image/*" capture="environment" class="w-full p-3 text-sm border rounded-xl" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">CNI Verso</label>
                            <input type="file" name="cni_back" accept="image/*" capture="environment" class="w-full p-3 text-sm border rounded-xl" required>
                        </div>
                    </div>
                </div>

                <!-- CHMPS FAMOCO / LOGIN / CNI / NOM -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="relative">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Famoco_ID</label>
                        <input type="text" id="famoco_input" name="famoco_id" placeholder="Saisir les 4 derniers caractères" autocomplete="off" class="w-full p-4 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-orange-100 outline-none transition" required>
                        <ul id="famoco_results" class="absolute z-10 w-full bg-white border border-gray-200 rounded-lg mt-1 max-h-60 overflow-y-auto shadow-lg hidden"></ul>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Login Kaabu</label>
                        <input type="text" id="login_kaabu" name="login" placeholder="Auto-rempli selon le Famoco" readonly tabindex="-1" class="w-full p-4 border border-gray-200 rounded-2xl bg-gray-100 text-gray-500 cursor-not-allowed outline-none select-none" required>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Numéro CNI</label>
                        <input type="text" name="cni_number" placeholder="Saisir le long numéro" class="w-full p-4 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-orange-100 outline-none transition" required>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nom complet</label>
                        <input type="text" name="nom" placeholder="Nom complet" class="w-full p-4 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-orange-100 outline-none transition" required>
                    </div>
                </div>

                <!-- RÈGLEMENT INTÉRIEUR -->
                <div class="bg-slate-50 p-6 rounded-2xl border border-gray-100">
                    <h2 class="text-sm font-bold text-orange-700 uppercase mb-3">Règlement Intérieur</h2>
                    <div class="h-32 overflow-y-auto text-xs text-gray-600 bg-white p-4 border rounded-xl mb-4 leading-relaxed">
                        <p>Je reconnais expressément et librement avoir bien compris les règles et procédures très strictes qui régissent l'identification, l'activation des SIM et la création de compte Orange Money avant leurs ventes au client entre autres :</p>
                        <ul class="list-disc ml-4 space-y-1 mt-2">
                            <li>Toujours se rassurer d'avoir en face de soi, le client désireux d'acheter une Sim en demandant l'original de sa carte nationale d'identité.</li>
                            <li>Vérifier la validité de sa carte nationale d'identité.</li>
                            <li>Toujours remplir les conditions générales de ventes lors de l'identification d'une SIM et la création de compte Orange Money.</li>
                            <li>Toujours traiter toutes les informations personnelles des clients avec la plus grande confidentialité (Ne jamais divulguer, reproduire ou utiliser ces informations à des fins autres que celles spécifiquement autorisées par Orange Cameroun).</li>
                            <li>Chaque client (chaque CNI) ne peut acheter que 3 SIMs au maximum.</li>
                            <li>Aussi je m'engage, à ne jamais vendre les cartes SIM pré-identifiées.</li>
                        </ul>
                        <p class="mt-2 font-semibold">Conscient(e) que la violation des règles d'identification et/ou fraudes de toute nature, lors de la vente des Sims (Nouvel abonnement), avérées et prouvées par Orange Cameroun est un délit pénal susceptible de conduire à une peine d'emprisonnement.</p>
                        <p class="mt-2 italic">Par la présente, je m'engage à respecter tous ces principes qui m'ont été expliqués par Orange Cameroun et me reconnais coupable de toute situation contrevenante.</p>
                    </div>
                    <label class="flex items-center text-sm font-medium text-gray-700 cursor-pointer">
                        <input type="checkbox" name="acceptation_reglement" required class="mr-3 h-5 w-5 text-orange-600 rounded">
                        J'accepte le règlement intérieur
                    </label>
                </div>

                <button type="submit" class="w-full bg-slate-900 text-white font-bold py-5 rounded-2xl hover:bg-black transition-all shadow-lg active:scale-95">
                    Enregistrer l'agent
                </button>
            </form>
        </div>
    </main>

    <!-- SCRIPT DE CASCADE & AUTOCOMPLETE -->
    <script>
        const regionSelect = document.getElementById('region');
        const adminSelect = document.getElementById('region_admin');
        const enseigneSelect = document.getElementById('nom_enseigne');
        const inputFamoco = document.getElementById('famoco_input');
        const loginInput = document.getElementById('login_kaabu');
        const resultsList = document.getElementById('famoco_results');

        // Cascade dynamique des régions administratives
        const regionAdminData = {
            "RC_LITTORAL": ["LITTORAL", "SUD-OUEST"]
        };

        const enseigneData = {
            "SUD-OUEST": ["BMTC", "TEN HORNS"]
        };

        regionSelect.addEventListener('change', function() {
            const reg = this.value;
            adminSelect.innerHTML = '<option value="">-- Sélectionnez une region --</option>';
            if (regionAdminData[reg]) {
                regionAdminData[reg].forEach(z => adminSelect.innerHTML += `<option value="${z}">${z}</option>`);
            }
            updateEnseignes();
        });

        adminSelect.addEventListener('change', updateEnseignes);

        function updateEnseignes() {
            const admin = adminSelect.value;
            enseigneSelect.innerHTML = '<option value="">-- Sélectionnez type d\'enseigne --</option>';
            if (admin && enseigneData[admin]) {
                enseigneData[admin].forEach(e => enseigneSelect.innerHTML += `<option value="${e}">${e}</option>`);
            }
        }

        // RECHERCHE FAMOCO EN BD VIA FETCH API
        inputFamoco.addEventListener('input', function() {
            const val = this.value.trim().toUpperCase();
            const selectedEnseigne = enseigneSelect.value;
            resultsList.innerHTML = '';
            
            if (val.length < 1 || !selectedEnseigne) {
                resultsList.classList.add('hidden');
                return;
            }

            // Appel de notre route backend
            fetch(`index.php?route=search_famoco&enseigne=${encodeURIComponent(selectedEnseigne)}&term=${encodeURIComponent(val)}`)
                .then(response => response.json())
                .then(data => {
                    resultsList.innerHTML = '';
                    if (data.length > 0) {
                        resultsList.classList.remove('hidden');
                        data.forEach(item => {
                            const li = document.createElement('li');
                            li.textContent = item.id;
                            li.className = "p-3 hover:bg-orange-50 cursor-pointer text-sm border-b border-gray-100 font-mono text-gray-800 font-semibold";
                            
                            li.onclick = () => { 
                                inputFamoco.value = item.id; 
                                loginInput.value = item.login; // Auto-remplissage du Login depuis la BD
                                resultsList.classList.add('hidden'); 
                            };
                            resultsList.appendChild(li);
                        });
                    } else {
                        resultsList.classList.add('hidden');
                    }
                })
                .catch(err => console.error("Erreur de recherche Famoco:", err));
        });

        // Masquer la liste au clic extérieur
        document.addEventListener('click', function(e) {
            if (!inputFamoco.contains(e.target) && !resultsList.contains(e.target)) {
                resultsList.classList.add('hidden');
            }
        });

        // Menu Mobile
        const menuBtn = document.getElementById('menu-btn');
        const sidebar = document.getElementById('sidebar');
        if (menuBtn && sidebar) {
            menuBtn.addEventListener('click', () => sidebar.classList.toggle('hidden'));
        }
    </script>


    <!-- POPUP LOADER CROSS-CHECKING (10 SECONDES) -->
    <div id="crossCheckModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md hidden flex items-center justify-center p-6 z-[300]">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full text-center shadow-2xl relative border border-slate-100">
            
            <!-- LOADER EN COURS -->
            <div id="loaderContent">
                <div class="w-20 h-20 mx-auto mb-6 relative flex items-center justify-center">
                    <div class="absolute inset-0 rounded-full border-4 border-orange-100 border-t-orange-600 animate-spin"></div>
                    <i class="fas fa-search text-orange-600 text-2xl"></i>
                </div>
                
                <h3 class="text-xl font-extrabold text-slate-800 mb-2">Cross-Checking en cours...</h3>
                <p class="text-xs text-slate-500 font-medium leading-relaxed mb-6">
                    Veuillez patientez 5s pour vérification de la conformité des données.
                </p>

                <!-- Barre de progression -->
                <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden mb-3">
                    <div id="progressBar" class="bg-gradient-to-r from-orange-500 to-amber-500 h-full w-0 transition-all ease-linear duration-1000"></div>
                </div>
                <p id="timerText" class="text-xs font-bold text-orange-600">Traitement : 10s restantes...</p>
            </div>

            <!-- ERREUR DOUBLON -->
            <div id="errorContent" class="hidden">
                <div class="w-16 h-16 bg-red-100 text-red-600 rounded-2xl mx-auto flex items-center justify-center text-2xl mb-4">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-2">Doublon Détecté !</h3>
                <div id="errorMessage" class="text-xs text-red-600 bg-red-50 p-4 rounded-xl font-medium mb-6 text-left leading-relaxed border border-red-100"></div>
                
                <button type="button" onclick="closeCrossCheckModal()" class="w-full py-3.5 bg-slate-900 text-white font-bold rounded-xl hover:bg-black transition text-sm">
                    Corriger les informations
                </button>
            </div>

        </div>
    </div>
    
    <script>
        const form = document.querySelector('form[action*="submit_agent"]');
        const crossCheckModal = document.getElementById('crossCheckModal');
        const loaderContent = document.getElementById('loaderContent');
        const errorContent = document.getElementById('errorContent');
        const errorMessage = document.getElementById('errorMessage');
        const progressBar = document.getElementById('progressBar');
        const timerText = document.getElementById('timerText');

        form.addEventListener('submit', function(e) {
            e.preventDefault(); // On bloque la soumission classique pour faire le cross-checking

            const famocoId = document.getElementById('famoco_input').value.trim();
            const login = document.getElementById('login_kaabu').value.trim();
            const cniNumber = document.querySelector('input[name="cni_number"]').value.trim();

            // Réinitialiser le Modal Loader
            loaderContent.classList.remove('hidden');
            errorContent.classList.add('hidden');
            crossCheckModal.classList.remove('hidden');
            progressBar.style.width = '0%';
            
            let secondsLeft = 5; // Durée du compte à rebours en secondes
            timerText.textContent = `Traitement : ${secondsLeft}s restantes...`;

            // Lancement de la barre de progression (10s)
            const progressInterval = setInterval(() => {
                secondsLeft--;
                const percentage = ((5 - secondsLeft) / 5) * 100;
                progressBar.style.width = `${percentage}%`;
                timerText.textContent = `Traitement : ${secondsLeft}s restantes...`;

                if (secondsLeft <= 0) {
                    clearInterval(progressInterval);
                }
            }, 1000);

            // Lancer la vérification de doublons AJAX en parallèle
            fetch(`index.php?route=check_duplicate&famoco_id=${encodeURIComponent(famocoId)}&login=${encodeURIComponent(login)}&cni_number=${encodeURIComponent(cniNumber)}`)
                .then(response => response.json())
                .then(data => {
                    // On attend la fin du compte à rebours de 10 secondes pour le loader
                    setTimeout(() => {
                        clearInterval(progressInterval);

                        if (data.status === 'duplicate') {
                            // Afficher le message d'erreur
                            loaderContent.classList.add('hidden');
                            errorContent.classList.remove('hidden');
                            errorMessage.innerHTML = data.message;
                        } else {
                            // Si tout est OK, on soumet le formulaire vers le serveur
                            form.submit();
                        }
                    }, 10000); // 10000 ms = 10 secondes
                })
                .catch(err => {
                    clearInterval(progressInterval);
                    loaderContent.classList.add('hidden');
                    errorContent.classList.remove('hidden');
                    errorMessage.textContent = "Erreur de connexion lors de la vérification du cross-checking.";
                });
        });

        function closeCrossCheckModal() {
            crossCheckModal.classList.add('hidden');
        }
    </script>

</body>
</html>