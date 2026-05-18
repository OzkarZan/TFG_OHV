# Archivos para Botpress - Guía de Instalación

## 📁 Archivos de Acciones (Copiar a Botpress)

Copiar estos archivos a tu bot de Botpress en la carpeta de acciones:
```
data/global/actions/
```

### Acciones disponibles:

1. **botpress_get_repuestos.js**
   - Obtiene lista completa de repuestos
   - Variables resultado: `workflow.repuestos`, `workflow.respuesta`

2. **botpress_search_repuestos.js**
   - Busca repuestos por nombre o marca
   - Parámetro: `workflow.searchTerm`
   - Variables resultado: `workflow.search_results`, `workflow.respuesta`

3. **botpress_low_stock_repuestos.js**
   - Obtiene repuestos con stock bajo
   - Parámetro: `workflow.threshold` (default: 5)
   - Variables resultado: `workflow.low_stock_items`, `workflow.respuesta`

4. **botpress_get_cliente.js**
   - Obtiene datos de un cliente por email
   - Parámetro: `workflow.email`
   - Variables resultado: `workflow.cliente`, `workflow.respuesta`

5. **botpress_get_clientes.js**
   - Obtiene lista de todos los clientes
   - Variables resultado: `workflow.clientes`, `workflow.respuesta`

## 🔧 Archivos de Backend (Copiar al servidor)

Copiar a la raíz del proyecto o carpeta `chatbot/`:

- **gestionar_repuestos.php**
  - API para repuestos
  - Acciones: listar, buscar, bajo_stock, obtener

- **gestionar_clientes.php**
  - API para clientes
  - Acciones: listar, obtener

## 🌐 URLs de API (desde Botpress)

```
http://autosynctfg.site/chatbot/gestionar_repuestos.php?action=listar
http://autosynctfg.site/chatbot/gestionar_repuestos.php?action=buscar&term=freno
http://autosynctfg.site/chatbot/gestionar_repuestos.php?action=bajo_stock&threshold=5
http://autosynctfg.site/chatbot/gestionar_clientes.php?action=listar
http://autosynctfg.site/chatbot/gestionar_clientes.php?action=obtener&email=usuario@example.com
```

## 📝 Ejemplo de Uso en un Flujo Botpress

```
1. User says: "¿Qué repuestos tienes disponibles?"
   ↓
2. Action: botpress_get_repuestos
   ↓
3. Message: "{{ workflow.respuesta }}"
   (muestra: "Encontré 12 repuestos en la base de datos.")
```

## 🔐 Variables en Workflow

| Acción | Resultado Variable | Descripción |
|--------|-------------------|-------------|
| get_repuestos | `workflow.repuestos` | Array de todos los repuestos |
| get_repuestos | `workflow.respuesta` | Mensaje de resultado |
| search_repuestos | `workflow.search_results` | Array de resultados de búsqueda |
| low_stock_repuestos | `workflow.low_stock_items` | Array de repuestos con bajo stock |
| get_cliente | `workflow.cliente` | Objeto con datos del cliente |
| get_clientes | `workflow.clientes` | Array de todos los clientes |

## ⚙️ Configuración de Variables de Entorno (Opcional)

En el .env de Botpress, puedes definir:
```
BOTPRESS_API_URL=http://autosynctfg.site/chatbot
```

Si no se define, por defecto usa esa URL.

## 🚀 Pasos de Instalación

1. **Copiar archivos de acciones a Botpress:**
   ```
   Descargar: botpress_*.js
   Copiar a: data/global/actions/
   ```

2. **Copiar archivos PHP al servidor:**
   ```
   gestionar_repuestos.php → /home/hugo/tfg/chatbot/
   gestionar_clientes.php → /home/hugo/tfg/chatbot/
   ```

3. **Verificar conexión:**
   - Abre en el navegador: `http://autosynctfg.site/chatbot/gestionar_repuestos.php?action=listar`
   - Deberías ver un array JSON con los repuestos

4. **En Botpress:**
   - Crea un nuevo flujo
   - Añade una acción "botpress_get_repuestos"
   - Usa `{{ workflow.repuestos }}` en mensajes
   - Prueba el flujo

## 📦 Dependencias

- Node.js (para Botpress)
- PHP 7.4+ con PDO
- MySQL/MariaDB
- Axios (incluido en Botpress por defecto)

## 🆘 Troubleshooting

### Error: "Cannot find module 'axios'"
- Botpress incluye axios por defecto
- Si falta, instala: `npm install axios`

### Error: "Connection refused"
- Verifica que el servidor PHP está corriendo
- Comprueba la URL en BOTPRESS_API_URL
- Verifica CORS headers en gestionar_*.php

### Error 500 en PHP
- Verifica conexión a MySQL
- Comprueba variables de entorno DB_*
- Revisa logs: `docker logs [container_name]`
