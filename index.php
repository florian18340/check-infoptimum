<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Infoptimum</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="images/logo.svg" alt="Logo" class="logo">
        </div>
        <h1>Vérificateur de Stock</h1>
        
        <div id="loginSection" style="display: none;">
            <h2>Connexion</h2>
            <form id="loginForm">
                <input type="email" id="emailInput" placeholder="Email" required>
                <input type="password" id="passwordInput" placeholder="Mot de passe" required>
                <button type="submit">Se connecter</button>
            </form>
        </div>

        <div id="mainSection" style="display: none;">
            <div class="header-actions">
                <button id="settingsBtn" class="secondary-btn">Paramètres</button>
                <button id="logoutBtn" class="secondary-btn">Déconnexion</button>
            </div>

            <div id="settingsPanel" style="display: none; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px;">
                <h3>Email de notification</h3>
                <form id="notificationEmailForm">
                    <input type="email" id="notifEmailInput" placeholder="Email pour les alertes" required>
                    <button type="submit">Enregistrer</button>
                </form>
                <hr>
                <h3>Gestion des Workers</h3>
                <p>Ajoutez ici les URLs complètes de vos scripts <code>worker.php</code>.</p>
                <form id="addWorkerForm">
                    <input type="url" id="workerUrlInput" placeholder="https://worker1.example.com/worker.php" required>
                    <button type="submit">Ajouter Worker</button>
                </form>
                <table id="workersTable">
                    <thead><tr><th>URL du Worker</th><th>Action</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="add-url-section">
                <h2>Ventes à surveiller</h2>
                <form id="addUrlForm">
                    <input type="url" id="urlInput" placeholder="URL Infoptimum" required>
                    <button type="submit">Ajouter</button>
                </form>
            </div>

            <div class="list-section">
                <button id="checkAllBtn">Vérifier maintenant (via Cron)</button>
                <table id="stockTable">
                    <thead><tr><th>URL</th><th>État</th><th>Dernière vérification</th><th>Action</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>