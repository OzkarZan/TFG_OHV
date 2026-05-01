/* =====================================================
   AUTOSYNC - GOOGLE OAUTH 2.0 AUTHENTICATION
   Lógica de autenticación con Google
   ===================================================== */

let GOOGLE_CLIENT_ID = '';
const API_BASE_URL = 'http://localhost:5500/api'; // Cambiar según tu configuración

// =====================================================
// 1. INICIALIZAR GOOGLE SIGN-IN
// =====================================================

document.addEventListener('DOMContentLoaded', function() {
    loadGoogleClientId().then(() => {
        initializeGoogleSignIn();
    });

    // Agregar evento al botón de Google Sign-In
    const googleSignInBtn = document.getElementById('googleSignInBtn');
    if (googleSignInBtn) {
        googleSignInBtn.addEventListener('click', function(e) {
            e.preventDefault();
            requestGoogleSignIn();
        });
    }
});

async function loadGoogleClientId() {
    try {
        const response = await fetch(`${API_BASE_URL}/get-google-client-id.php`);
        const data = await response.json();
        GOOGLE_CLIENT_ID = data.client_id || '';
    } catch (error) {
        console.error('Error cargando Google client ID:', error);
    }
}

function initializeGoogleSignIn() {
    if (typeof google !== 'undefined' && GOOGLE_CLIENT_ID) {
        google.accounts.id.initialize({
            client_id: GOOGLE_CLIENT_ID,
            callback: handleGoogleSignIn
        });
    } else if (typeof google === 'undefined') {
        setTimeout(initializeGoogleSignIn, 250);
    }
}

// =====================================================
// 2. FLUJO DE GOOGLE SIGN-IN
// =====================================================

function requestGoogleSignIn() {
    if (typeof google !== 'undefined') {
        // Mostrar el selector de cuenta de Google
        google.accounts.id.prompt((notification) => {
            if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
                // Si One Tap no se puede mostrar, renderizar botón alternativo
                console.log('One Tap no disponible, usando flujo alternativo');
            }
        });
    }
}

// =====================================================
// 3. MANEJAR LA RESPUESTA DE GOOGLE SIGN-IN
// =====================================================

function handleGoogleSignIn(response) {
    const idToken = response.credential;

    // Mostrar un spinner de carga
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
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        showLoadingSpinner(false);

        if (data.success) {
            // Guardar el token de autenticación en localStorage
            localStorage.setItem('authToken', data.token);
            localStorage.setItem('userId', data.id_cliente);
            localStorage.setItem('userName', data.nombre);
            localStorage.setItem('userEmail', data.email);

            // Mostrar mensaje de éxito
            showAlert('¡Bienvenido ' + data.nombre + '!', 'success');

            // Cerrar el modal de login
            try {
                const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                if (loginModal) {
                    loginModal.hide();
                }
            } catch (e) {
                console.log('Modal no encontrado');
            }

            // Redirigir al dashboard después de 1.5 segundos
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1500);
        } else {
            showAlert(data.message || 'Error en la autenticación', 'error');
        }
    })
    .catch(error => {
        showLoadingSpinner(false);
        console.error('Error:', error);
        showAlert('Error en la autenticación: ' + error.message, 'error');
    });
}

// =====================================================
// 4. AUTENTICACIÓN TRADICIONAL (Email/Contraseña)
// =====================================================

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const email = document.getElementById('loginEmail').value;
            const password = document.querySelector('input[type="password"]').value;

            if (!email || !password) {
                showAlert('Por favor completa todos los campos', 'warning');
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

                const data = await response.json();
                showLoadingSpinner(false);

                if (data.success) {
                    // Guardar datos de sesión
                    localStorage.setItem('authToken', data.token);
                    localStorage.setItem('userId', data.id_cliente);
                    localStorage.setItem('userName', data.nombre);

                    showAlert('¡Bienvenido ' + data.nombre + '!', 'success');

                    // Cerrar modal
                    try {
                        const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                        if (loginModal) {
                            loginModal.hide();
                        }
                    } catch (e) {
                        console.log('Modal no encontrado');
                    }

                    // Redirigir
                    setTimeout(() => {
                        window.location.href = 'dashboard.html';
                    }, 1500);
                } else {
                    showAlert(data.message || 'Credenciales inválidas', 'error');
                }
            } catch (error) {
                showLoadingSpinner(false);
                console.error('Error:', error);
                showAlert('Error en el servidor: ' + error.message, 'error');
            }
        });
    }
});

// =====================================================
// 5. FUNCIONES AUXILIARES
// =====================================================

function showLoadingSpinner(show) {
    if (show) {
        console.log('Cargando...');
    } else {
        console.log('Carga completada');
    }
}

function showAlert(message, type = 'info') {
    // Crear una alerta usando Bootstrap
    const alertElement = document.createElement('div');
    alertElement.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertElement.style.zIndex = '9999';
    alertElement.style.top = '20px';
    alertElement.style.right = '20px';
    alertElement.style.maxWidth = '400px';
    alertElement.role = 'alert';
    alertElement.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    // Insertar al body
    document.body.appendChild(alertElement);

    // Auto-remover después de 4 segundos
    setTimeout(() => {
        alertElement.remove();
    }, 4000);
}

// =====================================================
// 6. VERIFICAR SESIÓN AL CARGAR PÁGINA
// =====================================================

function checkAuthStatus() {
    const token = localStorage.getItem('authToken');
    const userName = localStorage.getItem('userName');

    if (token && userName) {
        console.log('Usuario autenticado:', userName);
        return true;
    } else {
        return false;
    }
}

// =====================================================
// 7. CERRAR SESIÓN
// =====================================================

function logout() {
    // Limpiar localStorage
    localStorage.removeItem('authToken');
    localStorage.removeItem('userId');
    localStorage.removeItem('userName');
    localStorage.removeItem('userEmail');

    // Redirigir a home
    window.location.href = 'index.html';
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
});
