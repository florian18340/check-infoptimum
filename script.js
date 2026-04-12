document.addEventListener('DOMContentLoaded', () => {
    const loginSection = document.getElementById('loginSection');
    const mainSection = document.getElementById('mainSection');
    const loginForm = document.getElementById('loginForm');
    const logoutBtn = document.getElementById('logoutBtn');
    const settingsBtn = document.getElementById('settingsBtn');
    const settingsPanel = document.getElementById('settingsPanel');
    const addUrlForm = document.getElementById('addUrlForm');
    const tableBody = document.querySelector('#stockTable tbody');
    const checkAllBtn = document.getElementById('checkAllBtn');

    // Email de notification
    const notificationEmailForm = document.getElementById('notificationEmailForm');
    const notifEmailInput = document.getElementById('notifEmailInput');

    // Comptes Infoptimum
    const accountsTableBody = document.querySelector('#accountsTable tbody');
    const addAccountForm = document.getElementById('addAccountForm');

    checkSession();

    function checkSession() {
        fetch('api.php?action=list').then(r => r.status === 401 ? showLogin() : showMain());
    }

    function showLogin() { loginSection.style.display = 'block'; mainSection.style.display = 'none'; }
    function showMain() { 
        loginSection.style.display = 'none'; 
        mainSection.style.display = 'block'; 
        loadUrls(); 
        loadAccounts();
        loadUserInfo();
    }

    // --- Gestion des URLs ---
    function loadUrls() {
        fetch('api.php?action=list').then(r => r.json()).then(data => renderUrls(data));
    }

    function renderUrls(items) {
        if (!items || !items.map) return;
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
        if(confirm('Supprimer ?')) fetch('api.php?action=delete', {
            method: 'POST',
            body: JSON.stringify({id})
        }).then(() => loadUrls());
    }

    addUrlForm.onsubmit = (e) => {
        e.preventDefault();
        fetch('api.php?action=add', {
            method: 'POST',
            body: JSON.stringify({url: document.getElementById('urlInput').value})
        }).then(() => { loadUrls(); e.target.reset(); });
    }

    // --- Paramètres Utilisateur ---
    function loadUserInfo() {
        fetch('api.php?action=get_user_info').then(r => r.json()).then(data => {
            if (data && notifEmailInput) notifEmailInput.value = data.notification_email || data.email;
        });
    }

    notificationEmailForm.onsubmit = (e) => {
        e.preventDefault();
        fetch('api.php?action=update_notification_email', {
            method: 'POST',
            body: JSON.stringify({email: notifEmailInput.value})
        }).then(r => r.json()).then(data => {
            alert(data.success ? 'Email mis à jour' : 'Erreur');
        });
    }

    // --- Gestion des Comptes Infoptimum ---
    function loadAccounts() {
        fetch('api.php?action=list_accounts').then(r => r.json()).then(data => {
            if (!data || !data.map) return;
            accountsTableBody.innerHTML = data.map(acc => `
                <tr>
                    <td>${acc.email}</td>
                    <td><button onclick="deleteAccount(${acc.id})">Supprimer</button></td>
                </tr>
            `).join('');
        });
    }

    window.deleteAccount = (id) => {
        if(confirm('Supprimer ce compte ?')) fetch('api.php?action=delete_account', {
            method: 'POST',
            body: JSON.stringify({id})
        }).then(() => loadAccounts());
    }

    addAccountForm.onsubmit = (e) => {
        e.preventDefault();
        fetch('api.php?action=add_account', {
            method: 'POST',
            body: JSON.stringify({
                email: document.getElementById('accEmail').value,
                password: document.getElementById('accPass').value
            })
        }).then(() => { loadAccounts(); e.target.reset(); });
    }

    checkAllBtn.onclick = () => {
        checkAllBtn.disabled = true;
        checkAllBtn.textContent = 'Vérification en cours...';
        fetch('api.php?action=check_all').then(() => {
            loadUrls();
            checkAllBtn.disabled = false;
            checkAllBtn.textContent = 'Vérifier maintenant';
        });
    }

    loginForm.onsubmit = (e) => {
        e.preventDefault();
        fetch('api.php?action=login', {
            method: 'POST',
            body: JSON.stringify({
                email: document.getElementById('emailInput').value,
                password: document.getElementById('passwordInput').value
            })
        }).then(r => r.json()).then(data => {
            if (data.success) showMain();
            else alert('Identifiants incorrects');
        });
    }

    logoutBtn.onclick = () => fetch('api.php?action=logout').then(() => showLogin());
    
    settingsBtn.onclick = () => {
        settingsPanel.style.display = settingsPanel.style.display === 'none' ? 'block' : 'none';
    };
});