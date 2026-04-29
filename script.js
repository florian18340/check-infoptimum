document.addEventListener('DOMContentLoaded', () => {
    const loginSection = document.getElementById('loginSection');
    const mainSection = document.getElementById('mainSection');
    const settingsPanel = document.getElementById('settingsPanel');
    
    const loginForm = document.getElementById('loginForm');
    const logoutBtn = document.getElementById('logoutBtn');
    const settingsBtn = document.getElementById('settingsBtn');
    const addUrlForm = document.getElementById('addUrlForm');
    const checkAllBtn = document.getElementById('checkAllBtn');
    const notificationEmailForm = document.getElementById('notificationEmailForm');
    const addWorkerForm = document.getElementById('addWorkerForm');

    const urlsTableBody = document.querySelector('#stockTable tbody');
    const workersTableBody = document.querySelector('#workersTable tbody');

    // --- INITIALISATION ---
    checkSession();

    function checkSession() {
        fetch('api.php?action=list')
            .then(r => r.status === 401 ? showLogin() : showMain())
            .catch(() => showLogin());
    }

    function showLogin() {
        loginSection.style.display = 'block';
        mainSection.style.display = 'none';
    }

    function showMain() {
        loginSection.style.display = 'none';
        mainSection.style.display = 'block';
        loadUrls();
        loadWorkers();
        loadUserInfo();
    }

    // --- GESTION DES URLS ---
    function loadUrls() {
        fetch('api.php?action=list')
            .then(r => r.json())
            .then(data => {
                urlsTableBody.innerHTML = data.map(item => `
                    <tr>
                        <td><a href="${item.url}" target="_blank">${item.url}</a></td>
                        <td class="status-${item.last_status}">${item.last_status}</td>
                        <td>${item.last_check || 'Jamais'}</td>
                        <td><button onclick="deleteUrl(${item.id})">Supprimer</button></td>
                    </tr>
                `).join('');
            });
    }

    window.deleteUrl = (id) => {
        if(confirm('Supprimer cette surveillance ?')) {
            fetch('api.php?action=delete', {
                method: 'POST', body: JSON.stringify({id})
            }).then(loadUrls);
        }
    }

    if (addUrlForm) {
        addUrlForm.onsubmit = (e) => {
            e.preventDefault();
            const input = document.getElementById('urlInput');
            fetch('api.php?action=add', {
                method: 'POST', body: JSON.stringify({url: input.value})
            }).then(() => { loadUrls(); input.value = ''; });
        };
    }

    // --- GESTION DES WORKERS ---
    function loadWorkers() {
        fetch('api.php?action=list_workers')
            .then(r => r.json())
            .then(data => {
                workersTableBody.innerHTML = data.map(item => `
                    <tr>
                        <td>${item.url}</td>
                        <td><button onclick="deleteWorker(${item.id})">Supprimer</button></td>
                    </tr>
                `).join('');
            });
    }

    window.deleteWorker = (id) => {
        if(confirm('Supprimer ce worker ?')) {
            fetch('api.php?action=delete_worker', {
                method: 'POST', body: JSON.stringify({id})
            }).then(loadWorkers);
        }
    }

    if (addWorkerForm) {
        addWorkerForm.onsubmit = (e) => {
            e.preventDefault();
            const input = document.getElementById('workerUrlInput');
            fetch('api.php?action=add_worker', {
                method: 'POST', body: JSON.stringify({url: input.value})
            }).then(() => { loadWorkers(); input.value = ''; });
        };
    }

    // --- PARAMÈTRES ET AUTH ---
    function loadUserInfo() {
        fetch('api.php?action=get_user_info')
            .then(r => r.json())
            .then(data => {
                if (data) document.getElementById('notifEmailInput').value = data.notification_email || data.email;
            });
    }

    if (notificationEmailForm) {
        notificationEmailForm.onsubmit = (e) => {
            e.preventDefault();
            const email = document.getElementById('notifEmailInput').value;
            fetch('api.php?action=update_notification_email', {
                method: 'POST', body: JSON.stringify({email})
            }).then(() => alert('Email mis à jour'));
        };
    }

    if (loginForm) {
        loginForm.onsubmit = (e) => {
            e.preventDefault();
            const email = document.getElementById('emailInput').value;
            const password = document.getElementById('passwordInput').value;
            fetch('api.php?action=login', {
                method: 'POST', body: JSON.stringify({email, password})
            })
            .then(r => r.json())
            .then(data => data.success ? showMain() : alert('Identifiants incorrects'));
        };
    }

    if (logoutBtn) logoutBtn.onclick = () => fetch('api.php?action=logout').then(() => location.reload());
    if (settingsBtn) settingsBtn.onclick = () => settingsPanel.style.display = settingsPanel.style.display === 'none' ? 'block' : 'none';
    if (checkAllBtn) checkAllBtn.onclick = () => alert("La vérification est maintenant gérée par le cron du serveur principal. Cette action est désactivée.");
});