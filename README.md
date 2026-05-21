# AutoSync - Gestor de Talleres 🚗💨

**AutoSync** es una plataforma integral diseñada para transformar la operativa de los talleres mecánicos tradicionales en un ecosistema digital eficiente. El proyecto centraliza la gestión de clientes, empleados, citas y stock en una única interfaz moderna y accesible.

---

## 📋 ¿Qué hace AutoSync?
AutoSync es una plataforma integral de gestión para talleres mecánicos que centraliza todas las operaciones en un único ecosistema digital. Actúa como núcleo operativo del taller, facilitando la comunicación y organización mediante funcionalidades clave:

---

## ✨ Funcionalidades Principales

### 🔐 Autenticación y Seguridad
*   **Login con Email/Contraseña:** Autenticación tradicional con recuperación de contraseña
*   **Google OAuth 2.0:** Inicio de sesión rápido con cuenta de Google
*   **Control de Acceso por Roles:** Admin, Cliente y Mecánico con permisos diferenciados
*   **Sesiones Seguras:** Validación de tokens para proteger datos sensibles

### 👥 Gestión de Usuarios Dual

#### Portal del Cliente
*   **Registro de Clientes:** Alta en la plataforma con datos personales
*   **Gestión de Vehículos:** Registrar y almacenar información de vehículos personales
*   **Reserva de Citas:** Solicitar citas online directamente desde la web
*   **Consulta de Estado:** Ver el estado actual de sus vehículos en reparación
*   **Historial de Citas:** Acceder a citas próximas y pasadas
*   **Cancelación de Citas:** Cancelar citas no deseadas
*   **Portal Responsivo:** Acceso desde desktop y dispositivos móviles

#### Panel de Control Admin/Mecánico
*   **Dashboard Completo:** Vista general de estadísticas y actividad del taller
*   **Gestión de Clientes:** CRUD completo (Crear, Leer, Actualizar, Eliminar)
*   **Gestión de Vehículos:** Control de vehículos asociados a clientes
*   **Gestión de Citas:** Programar, modificar y seguimiento de citas
*   **Gestión de Reparaciones:** Registro y seguimiento de trabajos en curso
*   **Gestión de Presupuestos:** Crear y modificar presupuestos
*   **Gestión de Mecánicos:** Asignar personal a trabajos específicos
*   **Panel Responsivo:** Interfaz adaptada para manejo en taller

### 📅 Sistema de Citas Inteligente
*   **Reserva Online:** Clientes pueden reservar citas directamente desde la web
*   **Horarios Programados:** Horario de atención (Lun-Vie 08:00-18:00, Sáb 09:00-13:00, Cerrado Dom)
*   **Limitación de Capacidad:** Máximo 3 citas por franja horaria para evitar saturación
*   **Validación de Fechas:** Sistema solo permite reservar en horarios disponibles y futuras
*   **Confirmación por Email:** Notificación automática al cliente cuando se reserva una cita
*   **Visualización de Citas:** Clientes ven sus citas próximas y pasadas
*   **Cancelación de Citas:** Posibilidad de cancelar citas no deseadas

### 🔧 Gestión de Reparaciones
*   **Registro de Trabajos:** Alta de nuevas reparaciones en el sistema
*   **Seguimiento de Estado:** Actualizar progreso (En Proceso, Esperando Piezas, Finalizada)
*   **Información de Diagnóstico:** Guardado del diagnóstico técnico por reparación
*   **Fechas de Entrada/Salida:** Registro de cuándo entra y sale el vehículo del taller
*   **Consulta por Matrícula:** Búsqueda rápida de reparaciones por matrícula del vehículo

### 💰 Gestión de Presupuestos
*   **Creación de Presupuestos:** Generar presupuestos para trabajos específicos
*   **Cambio de Estado:** Modificar estado (Pendiente → Aprobado → Rechazado)
*   **Generación de PDF:** Exportar presupuestos a documento PDF descargable
*   **Asociación a Trabajos:** Vincular presupuestos a reparaciones específicas

### 📧 Gestión de Emails
*   **Confirmación de Citas:** Email automático cuando se reserva una cita
*   **Notificaciones:** Envío de notificaciones a clientes sobre estado de trabajos
*   **Formulario de Contacto:** Formulario web para que clientes se comuniquen con el taller
*   **Integración SMTP:** Gmail (producción) / Mailhog (desarrollo)

### 🤖 Chatbot Inteligente 24/7 (Botpress)
El chatbot integrado permite a clientes interactuar sin necesidad de contacto directo:

*   **Ver Estado del Coche:** Consultar el estado actual de reparación por matrícula
*   **Reservar Cita:** Agendar una cita conversacionalmente
*   **Validación de Email:** Verificar que el cliente está registrado antes de hacer reserva
*   **Mis Citas:** Ver todas las citas próximas del cliente
*   **Cancelar Cita:** Cancelar citas desde el chat
*   **Contacto:** Enviar mensajes directos al taller
*   **Disponibilidad 24/7:** Responde automáticamente fuera de horario laboral
*   **Integración Segura:** Validación de token webhook para seguridad

### 📊 Base de Datos Completa
Sistema persistente con las siguientes tablas:
*   **USUARIOS:** Datos de todos los usuarios del sistema
*   **CLIENTES:** Información de clientes del taller
*   **VEHICULOS:** Vehículos registrados con marca, modelo, matrícula
*   **CITAS:** Citas programadas con fecha, hora, estado
*   **REPARACIONES:** Trabajos en proceso con diagnóstico y estado
*   **PRESUPUESTOS:** Presupuestos de trabajos con monto y estado
*   **MECANICOS:** Personal del taller con datos de contacto
*   **CONTACTO:** Mensajes de clientes desde formulario

### 🔌 API REST Completa
Sistema de endpoints para todas las operaciones:
*   **+50 endpoints** organizados por recurso (clientes, citas, reparaciones, etc)
*   **CRUD Completo:** Crear, leer, actualizar y eliminar para cada entidad
*   **Webhook Botpress:** Endpoint especial para integración con chatbot
*   **JSON Responses:** Respuestas estructuradas en JSON
*   **Validación de Datos:** Validación en servidor antes de guardar
*   **Manejo de Errores:** Mensajes claros en caso de error

---

## 🛠️ Stack Tecnológico

### Backend
*   **PHP 8.1** - Lenguaje de programación servidor
*   **PDO** - Acceso seguro a base de datos
*   **Apache** - Servidor web
*   **Architecture:** API REST con arquitectura MVC simplificada

### Frontend
*   **HTML5** - Estructura semántica
*   **CSS3** - Estilos responsivos
*   **JavaScript (Vanilla)** - Lógica cliente sin dependencias
*   **Bootstrap 5** - Framework CSS para componentes
*   **Font Awesome** - Iconografía profesional

### Base de Datos
*   **MySQL 8.0** - Sistema de gestión relacional
*   **PDO Prepared Statements** - Prevención de SQL Injection

### Infraestructura & DevOps
*   **Docker** - Containerización de servicios
*   **Docker Compose** - Orquestación de contenedores
*   **Nginx** - Proxy inverso y balanceador
*   **Cloudflare Tunnel** - Tunelización HTTPS segura
*   **GitHub Actions** - CI/CD automatizado

### Integraciones Externas
*   **Google OAuth 2.0** - Autenticación con Google
*   **Botpress Cloud** - Chatbot inteligente con LLMz
*   **Gmail SMTP** - Envío de emails en producción
*   **Mailhog** - Visualización de emails en desarrollo
*   **FPDF** - Generación de presupuestos en PDF

---

## 🚀 Instalación y Uso

### Requisitos Previos
*   Docker y Docker Compose instalados
*   Git para clonar el repositorio
*   Variables de entorno configuradas (.env)

### Configuración de Entorno

```bash
# Copiar archivo de configuración
cp .env.example .env

# Editar .env con:
# - Credenciales de BD
# - Configuración SMTP (Gmail/Mailhog)
# - Token de Botpress
# - Google Client ID
```

### Iniciar la Aplicación

```bash
# Construir y levantar contenedores
docker-compose up -d

# La aplicación estará disponible en:
# - Frontend: http://localhost:8080
# - Backend API: http://localhost:8000
# - Mailhog: http://localhost:8025 (desarrollo)
```

### Acceso a la Aplicación

#### Cliente
1. Ir a http://localhost:8080
2. Hacer clic en "Registrarse"
3. Completar datos personales
4. Acceder al portal cliente
5. Registrar vehículos y reservar citas

#### Admin/Mecánico
1. Ir a http://localhost:8080
2. Login con credenciales de admin (creadas en seed)
3. Acceder al dashboard
4. Gestionar clientes, citas y reparaciones

#### Chatbot
1. El widget de Botpress está disponible en http://localhost:8080
2. Hacer clic en el ícono de chat
3. Interactuar con el bot para:
   - Ver estado de vehículos
   - Reservar citas
   - Consultar mis citas
   - Cancelar citas

### Variables de Entorno Necesarias

```env
# Aplicación
APP_ENV=production
APP_URL=https://autosynctfg.site

# Base de Datos
DB_HOST=db
DB_NAME=autosync_db
DB_USER=autosync_user
DB_PASSWORD=***

# Email (Producción: Gmail)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=tu_email@gmail.com
SMTP_PASS=tu_app_password
SMTP_ENCRYPTION=tls

# Email (Desarrollo: Mailhog)
SMTP_HOST=mailhog
SMTP_PORT=1025
SMTP_ENCRYPTION=none

# Integraciones
GOOGLE_CLIENT_ID=***
BOTPRESS_WEBHOOK_TOKEN=***
BACKEND_URL=https://autosynctfg.site
```

---

## 📚 Documentación Adicional

*   **API Endpoints:** Ver `Documentation/API.md`
*   **Arquitectura:** Ver `ARQUITECTURA.md`
*   **Estructura del Proyecto:** Ver `ESTRUCTURA_ACTUAL.txt`

---

## 🔗 Acceso en Producción

La aplicación está disponible en producción en:
*   **URL Principal:** https://autosynctfg.site
*   **Portal Cliente:** https://autosynctfg.site (Acceso público)
*   **Dashboard Admin:** https://autosynctfg.site (Con login)
*   **API REST:** https://autosynctfg.site/api
*   **Chatbot:** Widget integrado en https://autosynctfg.site

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

## 📄 Licencia y Cofinanciación
Este proyecto está cofinanciado por la **Unión Europea** a través del **Fondo Social Europeo Plus (FSE+)**.