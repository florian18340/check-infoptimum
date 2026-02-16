document.addEventListener('DOMContentLoaded', () => {
    const loginSection = document.getElementById('loginSection');
    const registerSection = document.getElementById('registerSection');
    const mainSection = document.getElementById('mainSection');
    
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('emailInput');
    const passwordInput = document.getElementById('passwordInput');
    const loginError = document.getElementById('loginError');
    
    const registerForm = document.getElementById('registerForm');
    const regEmailInput = document.getElementById('regEmailInput');
    const regPasswordInput = document.getElementById('regPasswordInput');
    const regConfirmPasswordInput = document.getElementById('regConfirmPasswordInput');
    const registerError = document.getElementById('registerError');

    const showRegisterLink = document.getElementById('showRegisterLink');
    const showLoginLink = document.getElementById('showLoginLink');
    const logoutBtn = document.getElementById('logoutBtn');
    const settingsBtn = document.getElementById('settingsBtn');
    const settingsPanel = document.getElementById('settingsPanel');
    const notificationEmailForm = document.getElementById('notificationEmailForm');
    const notifEmailInput = document.getElementById('notifEmailInput');
    const settingsMessage = document.getElementById('settingsMessage');

    const addUrlForm = document.getElementById('addUrlForm');
    const urlInput = document.getElementById('urlInput');
    const tableBody = document.querySelector('#stockTable tbody');
    const checkAllBtn = document.getElementById('checkAllBtn');
    const autoCheckToggle = document.getElementById('autoCheckToggle');

    let autoCheckInterval = null;

    // Vérifier si l'utilisateur est déjà connecté
    checkSession();

    // --- Gestion de l'interface Login / Register ---

    if (showRegisterLink) {
        showRegisterLink.addEventListener('click', (e) => {
            e.preventDefault();
            loginSection.style.display = 'none';
            registerSection.style.display = 'block';
            registerError.style.display = 'none';
        });
    }

    if (showLoginLink) {
        showLoginLink.addEventListener('click', (e) => {
            e.preventDefault();
            registerSection.style.display = 'none';
            loginSection.style.display = 'block';
            loginError.style.display = 'none';
        });
    }

    // --- Actions ---

    loginForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const email = emailInput.value;
        const password = passwordInput.value;

        fetch('api.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMainInterface();
                loginError.style.display = 'none';
            } else {
                loginError.textContent = data.message;
                loginError.style.display = 'block';
            }
        })
        .catch(err => {
            console.error(err);
            loginError.textContent = "Erreur de connexion au serveur";
            loginError.style.display = 'block';
        });
    });

    registerForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const email = regEmailInput.value;
        const password = regPasswordInput.value;
        const confirmPassword = regConfirmPasswordInput.value;

        if (password !== confirmPassword) {
            registerError.textContent = "Les mots de passe ne correspondent pas.";
            registerError.style.display = 'block';
            return;
        }

        fetch('api.php?action=register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Inscription réussie, on connecte l'utilisateur directement
                showMainInterface();
                registerError.style.display = 'none';
                // Reset form
                regEmailInput.value = '';
                regPasswordInput.value = '';
                regConfirmPasswordInput.value = '';
            } else {
                registerError.textContent = data.message;
                registerError.style.display = 'block';
            }
        })
        .catch(err => {
            console.error(err);
            registerError.textContent = "Erreur lors de l'inscription";
            registerError.style.display = 'block';
        });
    });

    logoutBtn.addEventListener('click', () => {
        fetch('api.php?action=logout')
            .then(() => {
                showLoginInterface();
                stopAutoCheck();
            });
    });

    settingsBtn.addEventListener('click', () => {
        if (settingsPanel.style.display === 'none') {
            settingsPanel.style.display = 'block';
            loadUserInfo();
        } else {
            settingsPanel.style.display = 'none';
        }
    });

    notificationEmailForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const email = notifEmailInput.value;
        
        fetch('api.php?action=update_notification_email', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                settingsMessage.textContent = "Email mis à jour avec succès !";
                settingsMessage.style.color = "green";
                settingsMessage.style.display = "block";
                setTimeout(() => { settingsMessage.style.display = "none"; }, 3000);
            } else {
                settingsMessage.textContent = "Erreur : " + data.message;
                settingsMessage.style.color = "red";
                settingsMessage.style.display = "block";
            }
        });
    });

    addUrlForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const url = urlInput.value.trim();
        if (url) {
            addUrl(url);
            urlInput.value = '';
        }
    });

    checkAllBtn.addEventListener('click', () => {
        checkAllStocks();
    });

    autoCheckToggle.addEventListener('change', (e) => {
        if (e.target.checked) {
            startAutoCheck();
        } else {
            stopAutoCheck();
        }
    });

    // --- Fonctions utilitaires ---

    function checkSession() {
        fetch('api.php?action=list')
            .then(response => {
                if (response.status === 401) {
                    showLoginInterface();
                } else {
                    return response.json();
                }
            })
            .then(data => {
                if (data) {
                    showMainInterface();
                    renderTable(data);
                }
            })
            .catch(() => showLoginInterface());
    }

    function showLoginInterface() {
        loginSection.style.display = 'block';
        registerSection.style.display = 'none';
        mainSection.style.display = 'none';
    }

    function showMainInterface() {
        loginSection.style.display = 'none';
        registerSection.style.display = 'none';
        mainSection.style.display = 'block';
        loadUrls();
    }

    function loadUrls() {
        fetch('api.php?action=list')
            .then(response => {
                if (response.status === 401) {
                    showLoginInterface();
                    return null;
                }
                return response.json();
            })
            .then(data => {
                if (data) renderTable(data);
            });
    }

    function loadUserInfo() {
        fetch('api.php?action=get_user_info')
            .then(response => response.json())
            .then(data => {
                if (data && data.notification_email) {
                    notifEmailInput.value = data.notification_email;
                } else if (data && data.email) {
                    notifEmailInput.value = data.email;
                }
            });
    }

    function addUrl(url) {
        fetch('api.php?action=add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: url })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadUrls();
            } else {
                alert('Erreur: ' + (data.message || 'Inconnue'));
            }
        });
    }

    function deleteUrl(id) {
        if(confirm('Supprimer cette surveillance ?')) {
            fetch('api.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadUrls();
                } else {
                    alert('Erreur: ' + (data.message || 'Impossible de supprimer'));
                }
            });
        }
    }

    function checkAllStocks() {
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach(row => {
            if(row.cells.length > 1) {
                row.cells[1].textContent = '...';
            }
        });

        fetch('api.php?action=check_all')
            .then(response => response.json())
            .then(() => loadUrls());
    }

    function startAutoCheck() {
        if (autoCheckInterval) clearInterval(autoCheckInterval);
        checkAllStocks();
        autoCheckInterval = setInterval(() => {
            checkAllStocks();
        }, 300000);
        alert('Vérification automatique activée (toutes les 5 min). Gardez cet onglet ouvert.');
    }

    function stopAutoCheck() {
        if (autoCheckInterval) {
            clearInterval(autoCheckInterval);
            autoCheckInterval = null;
        }
    }

    function renderTable(items) {
        tableBody.innerHTML = '';
        if (!items || items.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Aucune vente surveillée.</td></tr>';
            return;
        }

        items.forEach(item => {
            const row = document.createElement('tr');
            
            let statusClass = 'status-unknown';
            let statusText = 'Inconnu';
            
            if (item.last_status === 'available') {
                statusClass = 'status-available';
                statusText = 'En Stock';
            } else if (item.last_status === 'out_of_stock') {
                statusClass = 'status-out';
                statusText = 'Épuisé';
            } else if (item.last_status === 'error') {
                statusClass = 'status-out';
                statusText = 'Erreur';
            }

            const lastCheck = item.last_check ? new Date(item.last_check).toLocaleString() : 'Jamais';

            row.innerHTML = `
                <td><a href="${item.url}" target="_blank">${item.url}</a></td>
                <td class="${statusClass}">${statusText}</td>
                <td>${lastCheck}</td>
                <td><button class="delete-btn" data-id="${item.id}">Supprimer</button></td>
            `;
            
            const deleteBtn = row.querySelector('.delete-btn');
            deleteBtn.addEventListener('click', () => deleteUrl(item.id));

            tableBody.appendChild(row);
        });
    }
});