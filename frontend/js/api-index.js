// Integración de Login con la API del Backend (index.html)
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const empleadoLoginToggle = document.getElementById('empleadoLoginToggle');
    const submitBtn = loginForm ? loginForm.querySelector('button[type="submit"]') : null;
    
    let isEmployee = false; // Modo cliente por defecto (va a client.html)

    if (empleadoLoginToggle && submitBtn) {
        empleadoLoginToggle.addEventListener('click', (e) => {
            e.preventDefault();
            isEmployee = !isEmployee; // alternar estado
            
            if (isEmployee) {
                // UI de Empleado
                submitBtn.innerText = "ACCEDER AL TALLER (ADMIN)";
                submitBtn.classList.add('text-primary');
                empleadoLoginToggle.innerHTML = '<i class="fas fa-user me-1"></i>Volver a acceso de Cliente';
            } else {
                // UI de Cliente
                submitBtn.innerText = "Registrarse en AutoSync";
                submitBtn.classList.remove('text-primary');
                empleadoLoginToggle.innerHTML = '<i class="fas fa-lock me-1"></i>¿Eres empleado de AutoSync? Ingresa aquí';
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
                // POST usando IP dinámica para funcionar tanto en local como en producción
                const API_BASE = `${window.location.protocol}//${window.location.hostname}:8000/api`;
                const response = await fetch(`${API_BASE}/login.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: email,
                        google_id: "form_" + new Date().getTime(),
                        nombre: email.split('@')[0]
                    })
                });

                const data = await response.json();

                if(response.ok) {
                    localStorage.setItem('autosync_token', data.token);
                    
                    // Lógica fundamental de roles
                    if (isEmployee) {
                        window.location.href = 'dashboard.html'; // Taller
                    } else {
                        window.location.href = 'client.html'; // Visión Cliente
                    }
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
                const API_BASE = `${window.location.protocol}//${window.location.hostname}:8000/api`;
                const response = await fetch(`${API_BASE}/register.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nombre: rName, email: rEmail, password: rPass })
                });

                const data = await response.json();

                if (response.ok) {
                    alert(data.message);
                    localStorage.setItem('autosync_token', data.token);
                    // Los nuevos siempre son clientes inicialmente
                    window.location.href = 'client.html';
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
});
