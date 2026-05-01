/* =====================================================
   AUTOSYNC - GOOGLE OAUTH 2.0 AUTHENTICATION
   Lógica de autenticación con Google y Email/Contraseña
   ===================================================== */

const DEFAULT_GOOGLE_CLIENT_ID = '713075227772-1gep73p4727dk2tn9o5vj14h0i2b2cd1.apps.googleusercontent.com';
let GOOGLE_CLIENT_ID = DEFAULT_GOOGLE_CLIENT_ID;
let API_BASE_URL = '';
const AUTH_STATE_KEY = 'authToken';
const USER_ID_KEY = 'userId';
const USER_NAME_KEY = 'userName';
const USER_EMAIL_KEY = 'userEmail';

const API_CANDIDATE_PATHS = [
    '/api',
    '/backend/api',
    '/frontend/api',
    '../api',
    '../backend/api',
    '../../backend/api',
    'http://localhost:8000/api',
    'http://localhost:8000/backend/api',
    'http://127.0.0.1:8000/api',
    'http://127.0.0.1:8000/backend/api'
];

// =====================================================
// 1. INICIALIZAR EN CARGA DEL DOCUMENTO
// =====================================================

document.addEventListener('DOMContentLoaded', async function() {
    // Cargar Google Client ID y inicializar
    await loadGoogleClientId();
    initializeGoogleSignIn();

    // Configurar evento del botón de Google Sign-In
    const googleSignInBtn = document.getElementById('googleSignInBtn');
    if (googleSignInBtn) {
        googleSignInBtn.addEventListener('click', function(e) {
            e.preventDefault();
            requestGoogleSignIn();
        });
    }
});

/**
 * Cargar el Google Client ID desde el backend
 */
async function loadGoogleClientId() {
    const candidateUrls = API_CANDIDATE_PATHS.map(path => {
        const cleanPath = path.replace(/\/$/, '');
        if (/^https?:\/\//.test(cleanPath)) {
            return `${cleanPath}/get-google-client-id.php`;
        }
        if (cleanPath.startsWith('/')) {
            return `${window.location.origin}${cleanPath}/get-google-client-id.php`;
        }
        return `${cleanPath}/get-google-client-id.php`;
    });

    for (const url of candidateUrls) {
        try {
            const response = await fetch(url);
            if (!response.ok) {
                continue;
            }
            const data = await response.json();
            if (data && data.client_id) {
                GOOGLE_CLIENT_ID = data.client_id;
                API_BASE_URL = url.replace('/get-google-client-id.php', '');
                console.log('API_BASE_URL detectado en:', API_BASE_URL);
                return;
            }
        } catch (error) {
            console.warn('Intento fallido de API en', url, error);
        }
    }

    console.warn('No se pudo encontrar el endpoint de Google Client ID en ninguna ruta candidata. Usando CLIENT_ID por defecto.');
    GOOGLE_CLIENT_ID = DEFAULT_GOOGLE_CLIENT_ID;
    API_BASE_URL = window.location.origin + '/backend/api';
    renderGoogleButton();
}

/**
 * Inicializar Google Sign-In cuando la librería esté disponible
 */
function initializeGoogleSignIn() {
    if (typeof google !== 'undefined' && GOOGLE_CLIENT_ID) {
        try {
            google.accounts.id.initialize({
                client_id: GOOGLE_CLIENT_ID,
                callback: handleGoogleSignIn,
                auto_select: false,
                itp_support: true
            });
            renderGoogleButton();
            console.log('Google Sign-In inicializado correctamente');
        } catch (error) {
            console.error('Error inicializando Google Sign-In:', error);
            const buttonContainer = document.getElementById('googleSignInBtn');
            renderFallbackGoogleButton(buttonContainer);
        }
    } else if (typeof google === 'undefined') {
        setTimeout(initializeGoogleSignIn, 500);
    }
}

function renderGoogleButton() {
    const buttonContainer = document.getElementById('googleSignInBtn');
    if (!buttonContainer) {
        return;
    }

    try {
        google.accounts.id.renderButton(buttonContainer, {
            theme: 'outline',
            size: 'large',
            type: 'standard',
            text: 'signin_with',
            shape: 'rectangular'
        });
        google.accounts.id.prompt();
    } catch (error) {
        console.warn('No se pudo renderizar el botón de Google:', error);
        renderFallbackGoogleButton(buttonContainer);
    }
}

function renderFallbackGoogleButton(container) {
    if (!container) {
        return;
    }

    container.innerHTML = '';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline-dark w-100 py-2 mb-4 d-flex align-items-center justify-content-center gap-2 fw-bold';
    button.style.borderRadius = '0';
    button.innerHTML = '<i class="fab fa-google"></i> Continuar con Google';
    button.addEventListener('click', function(e) {
        e.preventDefault();
        requestGoogleSignIn();
    });
    container.appendChild(button);
}

// =====================================================
// 2. MANEJAR GOOGLE SIGN-IN
// =====================================================

function requestGoogleSignIn() {
    if (typeof google === 'undefined') {
        showAlert('Google Sign-In aún no está disponible. Intenta de nuevo.', 'warning');
        return;
    }
    
    if (!GOOGLE_CLIENT_ID) {
        showAlert('Google no está configurado correctamente. Contacta con soporte.', 'error');
        return;
    }

    try {
        google.accounts.id.prompt((notification) => {
            if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
                console.log('One Tap no disponible, se intentará mostrar el botón de Google');
            }
        });
    } catch (error) {
        console.error('Error al solicitar Google Sign-In:', error);
        showAlert('No se pudo iniciar el login de Google. Intenta de nuevo.', 'error');
    }
}

/**
 * Callback que se ejecuta cuando Google Sign-In es exitoso
 */
function handleGoogleSignIn(response) {
    if (!response || !response.credential) {
        showAlert('Error: No se recibió el token de Google', 'error');
        return;
    }

    const idToken = response.credential;
    showLoadingSpinner(true);

    // Enviar el token a nuestro backend para verificación
    fetch(`${API_BASE_URL}/google-callback.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            id_token: idToken
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        showLoadingSpinner(false);

        if (data.success) {
            // Guardar datos de sesión
            saveUserSession(data.token, data.id_cliente, data.nombre, data.email);
            
            // Mostrar mensaje de éxito
            showAlert(`¡Bienvenido ${data.nombre}!`, 'success');

            // Cerrar modal de login si existe
            closeLoginModal();

            // Redirigir al dashboard
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1500);
        } else {
            showAlert(data.message || 'Error en la autenticación con Google', 'error');
        }
    })
    .catch(error => {
        showLoadingSpinner(false);
        console.error('Error en autenticación con Google:', error);
        showAlert(`Error en la autenticación: ${error.message}`, 'error');
    });
}

// =====================================================
// 3. AUTENTICACIÓN TRADICIONAL (Email/Contraseña)
// =====================================================

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleEmailLogin);
    }
});

/**
 * Manejar login con email y contraseña
 */
async function handleEmailLogin(e) {
    e.preventDefault();

    const emailInput = document.getElementById('loginEmail');
    const passwordInput = document.querySelector('input[type="password"]');

    if (!emailInput || !passwordInput) {
        showAlert('Formulario de login no encontrado', 'error');
        return;
    }

    const email = emailInput.value.trim();
    const password = passwordInput.value;

    if (!email || !password) {
        showAlert('Por favor completa todos los campos', 'warning');
        return;
    }

    if (!validateEmail(email)) {
        showAlert('Por favor ingresa un email válido', 'warning');
        return;
    }

    showLoadingSpinner(true);

    try {
        const response = await fetch(`${API_BASE_URL}/login.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                email: email,
                password: password
            })
        });

        if (!response.ok) {
            throw new Error(`Error HTTP ${response.status}`);
        }

        const data = await response.json();
        showLoadingSpinner(false);

        if (data.success || data.message === 'Login exitoso.') {
            // Guardar sesión
            saveUserSession(data.token, data.id_cliente, data.nombre, data.email || email);
            
            showAlert(`¡Bienvenido ${data.nombre}!`, 'success');

            // Limpiar formulario
            emailInput.value = '';
            passwordInput.value = '';

            // Cerrar modal
            closeLoginModal();

            // Redirigir
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1500);
        } else {
            showAlert(data.message || 'Credenciales inválidas', 'error');
        }
    } catch (error) {
        showLoadingSpinner(false);
        console.error('Error en login:', error);
        showAlert(`Error en el servidor: ${error.message}`, 'error');
    }
}

// =====================================================
// 4. FUNCIONES AUXILIARES
// =====================================================

/**
 * Guardar datos de sesión del usuario
 */
function saveUserSession(token, userId, userName, userEmail) {
    if (!token || !userId) {
        throw new Error('Token y userId son requeridos');
    }
    
    localStorage.setItem(AUTH_STATE_KEY, token);
    localStorage.setItem(USER_ID_KEY, userId);
    localStorage.setItem(USER_NAME_KEY, userName || '');
    localStorage.setItem(USER_EMAIL_KEY, userEmail || '');
    
    console.log('Sesión guardada para usuario:', userName);
}

/**
 * Cerrar el modal de login si existe
 */
function closeLoginModal() {
    try {
        const loginModal = document.getElementById('loginModal');
        if (loginModal && typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(loginModal);
            if (modal) {
                modal.hide();
            }
        }
    } catch (e) {
        console.log('No se pudo cerrar el modal:', e.message);
    }
}

/**
 * Validar formato de email
 */
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Mostrar/ocultar spinner de carga
 */
function showLoadingSpinner(show) {
    // Buscar spinner en el DOM
    let spinner = document.getElementById('loadingSpinner');
    
    if (show) {
        if (!spinner) {
            // Crear spinner si no existe
            spinner = document.createElement('div');
            spinner.id = 'loadingSpinner';
            spinner.className = 'spinner-border position-fixed';
            spinner.style.cssText = `
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999;
                display: flex;
            `;
            spinner.innerHTML = '<span class="visually-hidden">Cargando...</span>';
            document.body.appendChild(spinner);
        } else {
            spinner.style.display = 'flex';
        }
    } else if (spinner) {
        spinner.style.display = 'none';
    }
}

/**
 * Mostrar alerta al usuario
 */
function showAlert(message, type = 'info') {
    const validTypes = ['success', 'error', 'warning', 'info'];
    const alertType = validTypes.includes(type) ? type : 'info';
    
    // Mapear tipos a clases de Bootstrap
    const bootstrapType = alertType === 'error' ? 'danger' : alertType;
    
    // Crear elemento de alerta
    const alertElement = document.createElement('div');
    alertElement.className = `alert alert-${bootstrapType} alert-dismissible fade show position-fixed`;
    alertElement.style.cssText = `
        z-index: 9999;
        top: 20px;
        right: 20px;
        max-width: 400px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    `;
    alertElement.role = 'alert';
    alertElement.innerHTML = `
        ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    `;

    // Insertar al body
    document.body.appendChild(alertElement);

    // Auto-remover después de 5 segundos
    const timeout = setTimeout(() => {
        alertElement.remove();
    }, 5000);

    // Limpiar timeout si el usuario cierra la alerta manualmente
    alertElement.addEventListener('close.bs.alert', () => {
        clearTimeout(timeout);
    });
}

/**
 * Escapar caracteres HTML para evitar inyecciones
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// =====================================================
// 5. GESTIÓN DE SESIÓN
// =====================================================

/**
 * Verificar si el usuario está autenticado
 */
function checkAuthStatus() {
    const token = localStorage.getItem(AUTH_STATE_KEY);
    const userId = localStorage.getItem(USER_ID_KEY);
    const userName = localStorage.getItem(USER_NAME_KEY);

    if (token && userId && userName) {
        console.log('Usuario autenticado:', userName);
        return {
            isAuthenticated: true,
            token: token,
            userId: userId,
            userName: userName,
            email: localStorage.getItem(USER_EMAIL_KEY)
        };
    }
    
    return {
        isAuthenticated: false,
        token: null,
        userId: null,
        userName: null,
        email: null
    };
}

/**
 * Cerrar sesión del usuario
 */
function logout() {
    // Limpiar localStorage
    localStorage.removeItem(AUTH_STATE_KEY);
    localStorage.removeItem(USER_ID_KEY);
    localStorage.removeItem(USER_NAME_KEY);
    localStorage.removeItem(USER_EMAIL_KEY);

    // Si existe Google Sign-In, revocarlo
    if (typeof google !== 'undefined') {
        try {
            google.accounts.id.disableAutoSelect();
        } catch (e) {
            console.log('No se pudo revocar Google Sign-In:', e.message);
        }
    }

    console.log('Sesión cerrada');
    
    // Redirigir a home
    window.location.href = 'index.html';
}

/**
 * Redirigir a login si no está autenticado
 */
function requireAuth() {
    const auth = checkAuthStatus();
    if (!auth.isAuthenticated) {
        window.location.href = 'index.html';
    }
}

// Agregar evento a botón de logout si existe
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logout();
        });
    }

    // Mostrar nombre de usuario en navbar si está autenticado
    const userNameDisplay = document.getElementById('userNameDisplay');
    if (userNameDisplay) {
        const auth = checkAuthStatus();
        if (auth.isAuthenticated) {
            userNameDisplay.textContent = auth.userName;
        }
    }
});
