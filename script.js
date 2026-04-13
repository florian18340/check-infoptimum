document.addEventListener('DOMContentLoaded', () => {
    // Éléments de structure
    const loginSection = document.getElementById('loginSection');
    const mainSection = document.getElementById('mainSection');
    const settingsPanel = document.getElementById('settingsPanel');
    
    // Formulaires et Boutons
    const loginForm = document.getElementById('loginForm');
    const logoutBtn = document.getElementById('logoutBtn');
    const settingsBtn = document.getElementById('settingsBtn');
    const addUrlForm = document.getElementById('addUrlForm');
    const checkAllBtn = document.getElementById('checkAllBtn');
    const notificationEmailForm = document.getElementById('notificationEmailForm');

    // Corps de tableaux
    const tableBody = document.querySelector('#stockTable tbody');

    console.log("Initialisation du script...");

    // --- INITIALISATION ---
    checkSession();

    function checkSession() {
        fetch('api.php?action=list')
            .then(r => {
                if (r.status === 401) {
                    showLogin();
                } else {
                    return r.json().then(data => {
                        showMain();
                    });
                }
            })
            .catch(err => {
                console.error("Erreur session:", err);
                showLogin();
            });
    }

    function showLogin() {
        if (loginSection) loginSection.style.display = 'block';
        if (mainSection) mainSection.style.display = 'none';
    }

    function showMain() {
        if (loginSection) loginSection.style.display = 'none';
        if (mainSection) mainSection.style.display = 'block';
        loadUrls();
        loadUserInfo();
    }

    // --- Gestion des URLs ---
    function loadUrls() {
        fetch('api.php?action=list')
            .then(r => r.json())
            .then(data => renderUrls(data))
            .catch(err => console.error("Erreur chargement URLs:", err));
    }

    function renderUrls(items) {
        if (!tableBody) return;
        if (!items || !Array.isArray(items)) {
            tableBody.innerHTML = '<tr><td colspan="4">Aucune donnée</td></tr>';
            return;
        }
        tableBody.innerHTML = items.map(item => `
            <tr>
                <td><a href="${item.url}" target="_blank">${item.url}</a></td>
                <td class="status-${item.last_status}">${item.last_status}</td>
                <td>${item.last_check || 'Jamais'}</td>
                <td><button onclick="deleteUrl(${item.id})">Supprimer</button></td>
            </tr>
        `).join('');
    }

    window.deleteUrl = (id) => {
        if(confirm('Supprimer cette surveillance ?')) {
            fetch('api.php?action=delete', {
                method: 'POST',
                body: JSON.stringify({id})
            }).then(() => loadUrls());
        }
    }

    if (addUrlForm) {
        addUrlForm.onsubmit = (e) => {
            e.preventDefault();
            const input = document.getElementById('urlInput');
            fetch('api.php?action=add', {
                method: 'POST',
                body: JSON.stringify({url: input.value})
            }).then(() => { loadUrls(); e.target.reset(); });
        };
    }

    // --- PARAMÈTRES ET AUTH ---
    function loadUserInfo() {
        fetch('api.php?action=get_user_info')
            .then(r => r.json())
            .then(data => {
                const input = document.getElementById('notifEmailInput');
                if (data && input) input.value = data.notification_email || data.email;
            });
    }

    if (notificationEmailForm) {
        notificationEmailForm.onsubmit = (e) => {
            e.preventDefault();
            const email = document.getElementById('notifEmailInput').value;
            fetch('api.php?action=update_notification_email', {
                method: 'POST',
                body: JSON.stringify({email})
            }).then(() => alert('Email mis à jour'));
        };
    }

    if (loginForm) {
        loginForm.onsubmit = (e) => {
            e.preventDefault();
            const email = document.getElementById('emailInput').value;
            const password = document.getElementById('passwordInput').value;
            fetch('api.php?action=login', {
                method: 'POST',
                body: JSON.stringify({email, password})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) showMain();
                else alert('Identifiants incorrects');
            });
        };
    }

    if (logoutBtn) {
        logoutBtn.onclick = () => {
            fetch('api.php?action=logout').then(() => {
                location.reload(); // Recharger proprement pour vider l'état
            });
        };
    }

    if (settingsBtn) {
        settingsBtn.onclick = () => {
            if (settingsPanel) settingsPanel.style.display = settingsPanel.style.display === 'none' ? 'block' : 'none';
        };
    }

    if (checkAllBtn) {
        checkAllBtn.onclick = () => {
            checkAllBtn.disabled = true;
            checkAllBtn.textContent = 'Vérification...';
            fetch('api.php?action=check_all')
                .then(() => {
                    loadUrls();
                    checkAllBtn.disabled = false;
                    checkAllBtn.textContent = 'Vérifier maintenant';
                })
                .catch(() => {
                    checkAllBtn.disabled = false;
                    checkAllBtn.textContent = 'Vérifier maintenant';
                });
        };
    }
});