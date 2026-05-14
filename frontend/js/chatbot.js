// Chatbot widget — consulta estado de reparación por matrícula
document.addEventListener('DOMContentLoaded', () => {
    const toggle  = document.getElementById('chatbot-toggle');
    const window_ = document.getElementById('chatbot-window');
    const closeBtn = document.getElementById('chatbot-close');
    const input   = document.querySelector('.chatbot-input');
    const sendBtn = document.querySelector('.chatbot-send');
    const body    = document.querySelector('.chatbot-body');

    if (!toggle || !window_) return;

    // Abrir / cerrar
    toggle.addEventListener('click', () => {
        window_.style.display = window_.style.display === 'flex' ? 'none' : 'flex';
        window_.style.flexDirection = 'column';
        if (window_.style.display === 'flex' && input) input.focus();
    });
    if (closeBtn) {
        closeBtn.addEventListener('click', () => { window_.style.display = 'none'; });
    }

    function addMessage(html, isBot) {
        const div = document.createElement('div');
        div.className = 'chatbot-message ' + (isBot ? 'bot-message' : 'user-message');
        div.innerHTML = `<p>${html}</p>`;
        // Insertar antes del área de inputs
        const inputsEl = body.querySelector('.chatbot-inputs');
        body.insertBefore(div, inputsEl);
        body.scrollTop = body.scrollHeight;
    }

    async function enviar() {
        const matricula = input.value.trim();
        if (!matricula) return;

        addMessage(matricula, false);
        input.value = '';
        sendBtn.disabled = true;
        addMessage('<em>Consultando...</em>', true);

        try {
            const res  = await fetch('/api/chatbot', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ matricula })
            });
            const data = await res.json();

            // Quitar el "Consultando..."
            const consulting = body.querySelectorAll('.bot-message');
            consulting[consulting.length - 1].remove();

            addMessage(data.respuesta || 'No se pudo obtener respuesta.', true);
        } catch {
            const consulting = body.querySelectorAll('.bot-message');
            consulting[consulting.length - 1].remove();
            addMessage('Error de conexión. Inténtalo de nuevo.', true);
        } finally {
            sendBtn.disabled = false;
        }
    }

    sendBtn.addEventListener('click', enviar);
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') enviar(); });
});
