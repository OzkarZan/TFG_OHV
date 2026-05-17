// ===== GOOGLE SIGN-IN =====
function redirectByRole(rol) {
    if (rol === 'admin' || rol === 'empleado' || rol === 'mecanico') {
        window.location.href = 'dashboard.html';
    } else {
        window.location.href = 'client.html';
    }
}

async function enviarTokenGoogle(body) {
    const btn = document.getElementById('btnGoogleLogin');
    try {
        const res  = await fetch('/api/auth/google', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (res.ok) {
            redirectByRole(data.rol);
        } else {
            alert('Error con Google: ' + data.message);
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fab fa-google me-2"></i> Continuar con Google'; }
        }
    } catch (e) {
        console.error('Error enviando token Google:', e);
        alert('Error de conexión con el servidor.');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fab fa-google me-2"></i> Continuar con Google'; }
    }
}

// El listener del botón se registra en DOMContentLoaded de forma garantizada.
// Google se inicializa de forma lazy en el primer clic, evitando carreras de carga.
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btnGoogleLogin');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Cargando...';

        // Esperar a que el script de Google esté disponible (máx. 5s)
        let intentos = 0;
        while (!window.google?.accounts && intentos < 50) {
            await new Promise(r => setTimeout(r, 100));
            intentos++;
        }

        if (!window.google?.accounts) {
            alert('No se pudo cargar Google Sign-In. Comprueba tu conexión.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fab fa-google me-2"></i> Continuar con Google';
            return;
        }

        let clientId = '';
        try {
            const cfgRes = await fetch('/api/auth/config', { credentials: 'include' });
            const cfg    = await cfgRes.json();
            clientId     = cfg.google_client_id || '';
        } catch (e) {
            console.error('Error al obtener configuración de Google:', e);
        }

        if (!clientId) {
            alert('Google Sign-In no está configurado en el servidor.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fab fa-google me-2"></i> Continuar con Google';
            return;
        }

        // Usar oauth2.initTokenClient si está disponible (abre popup directo)
        if (window.google.accounts.oauth2) {
            const tokenClient = window.google.accounts.oauth2.initTokenClient({
                client_id: clientId,
                scope:     'openid email profile',
                callback:  (resp) => {
                    if (resp.error) {
                        console.error('Google OAuth error:', resp.error);
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fab fa-google me-2"></i> Continuar con Google';
                        return;
                    }
                    enviarTokenGoogle({ access_token: resp.access_token });
                },
                error_callback: (err) => {
                    console.error('Google OAuth error_callback:', err);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fab fa-google me-2"></i> Continuar con Google';
                },
            });
            tokenClient.requestAccessToken({ prompt: 'select_account' });
        } else {
            // Fallback: google.accounts.id con ux_mode popup
            window.google.accounts.id.initialize({
                client_id:   clientId,
                callback:    (resp) => enviarTokenGoogle({ credential: resp.credential }),
                ux_mode:     'popup',
                auto_select: false,
            });
            window.google.accounts.id.prompt();
        }
    });
});

// Integración de Login con la API del Backend (index.html)
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const empleadoLoginToggle = document.getElementById('empleadoLoginToggle');
    const submitBtn = loginForm ? loginForm.querySelector('button[type="submit"]') : null;
    
    let isEmployee = false; // Modo cliente por defecto (login)
    let isEmployeeRegister = false; // Modo cliente por defecto (registro)

    // Toggle para login (empleado/cliente)
    if (empleadoLoginToggle && submitBtn) {
        empleadoLoginToggle.addEventListener('click', (e) => {
            e.preventDefault();
            isEmployee = !isEmployee; // alternar estado
            if (isEmployee) {
                submitBtn.innerText = "ACCEDER AL TALLER (ADMIN)";
                submitBtn.classList.add('text-primary');
                empleadoLoginToggle.innerHTML = '<i class="fas fa-user me-1"></i>Volver a acceso de Cliente';
            } else {
                submitBtn.innerText = "Registrarse en AutoSync";
                submitBtn.classList.remove('text-primary');
                empleadoLoginToggle.innerHTML = '<i class="fas fa-lock me-1"></i>¿Eres empleado de AutoSync? Ingresa aquí';
            }
        });
    }

    // Toggle para registro de empleado
    const empleadoRegisterToggle = document.getElementById('empleadoRegisterToggle');
    if (empleadoRegisterToggle) {
        empleadoRegisterToggle.addEventListener('click', (e) => {
            e.preventDefault();
            isEmployeeRegister = !isEmployeeRegister;
            if (isEmployeeRegister) {
                empleadoRegisterToggle.innerHTML = '<i class="fas fa-user me-1"></i>Registrarse como cliente';
            } else {
                empleadoRegisterToggle.innerHTML = '<i class="fas fa-lock me-1"></i>¿Eres empleado? Regístrate aquí';
            }
        });
    }







    if(loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault(); // Evitamos recarga HTML
            
            const email = document.getElementById('loginEmail').value;
            const originalText = submitBtn.innerText;
            
            submitBtn.innerText = "Conectando con Backend...";
            submitBtn.disabled = true;

            try {
                // Usar ruta relativa para aprovechar el proxy inverso de Nginx
                const API_BASE = '/api';
                const response = await fetch(`${API_BASE}/auth/login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include', // Send session cookies
                    body: JSON.stringify({
                        email: email,
                        password: document.querySelector('input[type="password"]').value
                    })
                });

                const data = await response.json();

                if(response.ok) {
                    redirectByRole(data.rol);
                } else {
                    alert("Error lógico del Backend: " + data.message);
                    submitBtn.innerText = "Reintentar";
                    submitBtn.disabled = false;
                }

            } catch (error) {
                console.error("Error en Fetch:", error);
                alert("Error crítico conectando a la API: " + error.message);
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
            }
        });
    }

    // ===== GESTOR DE REGISTRO (registerModal) =====
    const registerForm = document.getElementById('registerForm');

    if(registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btnReg = registerForm.querySelector('button[type="submit"]');
            const originalText = btnReg.innerText;
            btnReg.innerText = "Creando cuenta...";
            btnReg.disabled = true;

            const rName = document.getElementById('regNombre').value;
            const rEmail = document.getElementById('regEmail').value;
            const rPass = document.getElementById('regPassword').value;

            try {
                const API_BASE = '/api';
                
                // Si el toggle de empleado está activo, enviar el rol y el access_code
                // Determinar rol según toggle de registro
                const rol = isEmployeeRegister ? 'empleado' : 'cliente';
                let bodyData = { nombre: rName, email: rEmail, password: rPass, rol: rol };
                
                if (isEmployeeRegister) {
                    const code = prompt("Introduce el código de acceso del taller:");
                    if (!code) {
                        btnReg.innerText = originalText;
                        btnReg.disabled = false;
                        return;
                    }
                    bodyData.access_code = code;
                }

                const response = await fetch(`${API_BASE}/auth/register`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(bodyData)
                });

                const data = await response.json();

                if (response.ok) {
                    alert(data.message);
                    // Los nuevos siempre son clientes inicialmente (o empleados si se registraron así)
                    if (rol === 'empleado') {
                        window.location.href = 'dashboard.html';
                    } else {
                        window.location.href = 'client.html';
                    }
                } else {
                    alert("No se pudo registrar: " + data.message);
                    btnReg.innerText = originalText;
                    btnReg.disabled = false;
                }
            } catch (e) {
                console.error(e);
                alert("Error crítico de servidor al intentar registrar.");
                btnReg.innerText = originalText;
                btnReg.disabled = false;
            }
        });
    }

    // ── Botones hero / CTA: si hay sesión redirige, si no abre login ──
    async function loginOrRedirect() {
        try {
            const res = await fetch('/api/auth/me', { credentials: 'include' });
            if (res.ok) {
                const data = await res.json();
                redirectByRole(data.rol);
            } else {
                new bootstrap.Modal(document.getElementById('loginModal')).show();
            }
        } catch {
            new bootstrap.Modal(document.getElementById('loginModal')).show();
        }
    }

    document.getElementById('btnReservar')?.addEventListener('click', loginOrRedirect);
    document.getElementById('btnConsultar')?.addEventListener('click', loginOrRedirect);
    document.getElementById('btnEmpezar')?.addEventListener('click', () => {
        new bootstrap.Modal(document.getElementById('loginModal')).show();
    });
    document.getElementById('btnContactarVentas')?.addEventListener('click', () => {
        document.getElementById('contacto').scrollIntoView({ behavior: 'smooth' });
    });

    // ── Formulario de contacto ──
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnContactoSubmit');
            const originalLabel = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Enviando...';

            try {
                const res = await fetch('/api/contacto', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nombre:  document.getElementById('contactNombre').value,
                        email:   document.getElementById('contactEmail').value,
                        asunto:  document.getElementById('contactAsunto').value,
                        mensaje: document.getElementById('contactMensaje').value
                    })
                });

                if (res.ok) {
                    contactForm.classList.add('d-none');
                    document.getElementById('contactSuccess').classList.remove('d-none');
                } else {
                    const data = await res.json();
                    alert('Error: ' + (data.message || 'No se pudo enviar el mensaje.'));
                    btn.disabled = false;
                    btn.textContent = originalLabel;
                }
            } catch {
                alert('Error de conexión. Escríbenos directamente a autosyncohv@gmail.com');
                btn.disabled = false;
                btn.textContent = originalLabel;
            }
        });
    }
});
