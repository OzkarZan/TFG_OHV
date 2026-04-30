/* =====================================================
   AUTOSYNC - JAVASCRIPT NATIVO
   Lógica de interactividad principal
   ===================================================== */

// =====================================================
// 1. NAVBAR MOBILE TOGGLE
// =====================================================

document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
        const navbarToggle = document.querySelector('.navbar-toggler');
        // Cerrar menú hamburguesa en mobile si está abierto
        if (navbarToggle && navbarToggle.offsetParent !== null) {
            navbarToggle.click();
        }
    });
});

// =====================================================
// 2. CHATBOT WIDGET (Floating)
// =====================================================

const chatbotToggle = document.getElementById('chatbot-toggle');
const chatbotWindow = document.getElementById('chatbot-window');
const chatbotClose = document.getElementById('chatbot-close');
const chatbotInput = document.querySelector('.chatbot-input');
const chatbotSend = document.querySelector('.chatbot-send');

// Abrir/cerrar chatbot al hacer clic en el botón flotante
if (chatbotToggle) {
    chatbotToggle.addEventListener('click', () => {
        chatbotWindow.classList.toggle('active');
    });
}

// Cerrar chatbot al hacer clic en el botón de cerrar
if (chatbotClose) {
    chatbotClose.addEventListener('click', () => {
        chatbotWindow.classList.remove('active');
    });
}

// Enviar mensaje al hacer clic en "Enviar" o presionar Enter
if (chatbotSend) {
    chatbotSend.addEventListener('click', sendChatbotMessage);
}

if (chatbotInput) {
    chatbotInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendChatbotMessage();
        }
    });
}

function sendChatbotMessage() {
    const input = document.querySelector('.chatbot-input');
    const message = input.value.trim();

    if (!message) return;

    // Agregar mensaje del usuario
    const messageBody = document.querySelector('.chatbot-body');
    const userMessageDiv = document.createElement('div');
    userMessageDiv.classList.add('chatbot-message', 'user-message');
    userMessageDiv.innerHTML = `<p style="text-align: right; color: #0062a0;">${message}</p>`;
    messageBody.appendChild(userMessageDiv);

    // Simular respuesta del bot
    setTimeout(() => {
        const botMessageDiv = document.createElement('div');
        botMessageDiv.classList.add('chatbot-message', 'bot-message');

        // Respuestas simuladas según palabras clave
        let response = 'Gracias por tu consulta. Un agente se pondrá en contacto pronto.';

        if (message.toLowerCase().includes('referencia') || message.toLowerCase().includes('número')) {
            response = 'Para consultar el estado, por favor ingresa tu número de referencia de reparación.';
        } else if (message.toLowerCase().includes('precio') || message.toLowerCase().includes('costo')) {
            response = 'Los precios varían según el servicio. ¿Cuál es el tipo de reparación que necesitas?';
        } else if (message.toLowerCase().includes('contacto') || message.toLowerCase().includes('teléfono')) {
            response = 'Puedes contactarnos por teléfono o visitando nuestro formulario de contacto en la página.';
        }

        botMessageDiv.innerHTML = `<p>${response}</p>`;
        messageBody.appendChild(botMessageDiv);

        // Scroll al último mensaje
        messageBody.scrollTop = messageBody.scrollHeight;
    }, 500);

    // Limpiar input
    input.value = '';
}

// =====================================================
// 3. CTA BUTTONS (Event Listeners)
// =====================================================

// Botón "Reservar en línea"
const reservarBtn = document.querySelectorAll('.btn-primary-custom');
reservarBtn.forEach(btn => {
    if (btn.textContent.includes('Reservar') || btn.textContent.includes('Empezar')) {
        btn.addEventListener('click', () => {
            // Simulación: ir a sección de contacto o abrir modal de reserva
            const contactoSection = document.querySelector('#contacto');
            if (contactoSection) {
                contactoSection.scrollIntoView({ behavior: 'smooth' });
            } else {
                alert('¡Gracias por tu interés! Pronto habilitaremos el sistema de reservas.');
            }
        });
    }
});

// Botón "Contactar a ventas"
const contactarBtn = document.querySelectorAll('.btn-secondary-custom');
contactarBtn.forEach(btn => {
    if (btn.textContent.includes('Contactar')) {
        btn.addEventListener('click', () => {
            alert('¡Pronto nos pondremos en contacto contigo!');
        });
    }
});

// =====================================================
// 4. SMOOTH SCROLL PARA ENLACES INTERNOS
// =====================================================

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href !== '#' && href !== '#login') {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }
    });
});

// =====================================================
// 5. INICIALIZACIÓN AL CARGAR LA PÁGINA
// =====================================================

document.addEventListener('DOMContentLoaded', async () => {
    console.log('✅ AutoSync Landing Page Cargada');

    // Comprobar sesión activa
    try {
        const response = await fetch('/api/auth/me', { credentials: 'include' });
        const navAuthBtnContainer = document.querySelector('li.nav-item button[data-bs-target="#loginModal"]')?.parentElement;
        
        if (response.ok) {
            const data = await response.json();
            if (navAuthBtnContainer) {
                const dashboardLink = data.rol === 'empleado' || data.rol === 'admin' ? 'dashboard.html' : 'client.html';
                navAuthBtnContainer.innerHTML = `
                    <div class="dropdown">
                        <button class="btn btn-login dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> ${data.nombre_completo} <small>(${data.rol})</small>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownMenuButton">
                            <li><a class="dropdown-item fw-bold" href="${dashboardLink}">Ir a mi panel</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger fw-bold" href="#" id="btnLogout">Cerrar Sesión</a></li>
                        </ul>
                    </div>
                `;

                document.getElementById('btnLogout').addEventListener('click', async (e) => {
                    e.preventDefault();
                    await fetch('/api/auth/logout', { method: 'POST', credentials: 'include' });
                    window.location.reload();
                });
            }
        }
    } catch (e) {
        console.error('Error verificando sesión:', e);
    }
});
