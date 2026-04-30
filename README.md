# AutoSync - Gestor de Talleres 🚗💨

**AutoSync** es una plataforma web enfocada en la gestión comunicativa y de stock de talleres mecánicos. El proyecto surge de la necesidad de modernizar los procesos manuales —como las agendas de papel y las llamadas telefónicas— que aún predominan en el sector.

---

## 📋 Descripción del Proyecto
La plataforma permite administrar el stock de piezas y el calendario de citas mediante un panel de control centralizado. Además, integra un chatbot inteligente para automatizar notificaciones y la atención al cliente.

### 🎯 Objetivos Principales
* **Digitalización:** Eliminar el uso de agendas físicas mediante un panel de control para stock y tareas.
* **Automatización:** Implementación de un chatbot (Botpress) con respuesta inmediata 24/7.
* **Optimización de Citas:** Sistema de reserva online para agilizar el flujo de trabajo y reducir llamadas perdidas.
* **Control de Inventario:** Supervisión exacta de entradas y salidas de materiales.

---

## 🛠️ Stack Tecnológico
El proyecto se desarrolla integrando conocimientos del ciclo con herramientas de IA y DevOps modernas:

* **Frontend:** HTML5, CSS3 (Vanilla), Bootstrap 5 y JavaScript (ES6+).
* **Backend:** API REST construida en PHP (Arquitectura MVC simplificada).
* **Base de Datos:** MySQL 8.0.
* **Infraestructura:** Docker & Docker Compose para contenerización.
* **Servidor Web:** Nginx (Proxy inverso).
* **CI/CD:** GitHub Actions para despliegue automatizado.
* **IA/Automatización:** Botpress para el sistema de chatbot.

---

## 📅 Planificación (Roadmap)
Utilizamos una metodología **Scrum** con ciclos de vida repetitivos para construir la aplicación por bloques funcionales.

| Fase | Tareas Principales | Fechas | Estado |
| :--- | :--- | :--- | :--- |
| **Análisis** | Requisitos e historias de usuario. | 02/03 - 15/03 | ✅ Finalizado |
| **Diseño** | Wireframes y arquitectura del sistema. | 16/03 - 23/03 | ✅ Finalizado |
| **Core Dev I** | Registro dual (Cliente/Empleado), Login y Auth. | 24/03 - 19/04 | ✅ Finalizado |
| **Core Dev II** | Gestión de stock, calendario y Botpress. | 20/04 - 03/05 | 🏗️ En curso |
| **Pruebas** | Plan de pruebas y memoria final. | 04/05 - 18/05 | 📅 Pendiente |

---

## 👥 Equipo y Autores
* **Desarrolladores:** Oscar Manuel Malave Ramirez, Hugo Cortés Rosado y Victor Manuel Mariños La Puente.
* **Tutor:** Amparo Marin Velasco.
* **Centro:** IES Enrique Tierno Galván (Madrid).
* **Ciclo:** Desarrollo de Aplicaciones Web (DAW).

---

## 🚀 Instalación y Despliegue
El proyecto está completamente dockerizado para facilitar su despliegue en cualquier entorno.

### Requisitos Previos
* Docker y Docker Compose instalados.
* Git.

### Pasos para ejecución local
1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/tu-usuario/AutoSync.git
   cd AutoSync/TFG_OHV
   ```
2. **Configurar variables de entorno:**
   Crea un archivo `.env` basado en las necesidades de tu base de datos (ver ejemplo en el código).
3. **Levantar los servicios:**
   ```bash
   docker compose up -d --build
   ```
4. **Acceso:**
   Abre tu navegador en `http://localhost:8080`.

### Despliegue Automático
El proyecto cuenta con un flujo de **GitHub Actions** (`deploy.yml`) que despliega automáticamente los cambios en la rama `master` al servidor de producción.

---

## 📄 Licencia y Cofinanciación
Este proyecto está cofinanciado por la **Unión Europea** a través del **Fondo Social Europeo Plus (FSE+)**.