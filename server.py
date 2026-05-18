#!/usr/bin/env python3
"""
Servidor Mock Python para simular endpoints PHP del chatbot AutoSync
Simula gestionar_citas.php y gestionar_faqs.php
"""

from flask import Flask, request, jsonify
from datetime import datetime
from typing import Dict, Any
import uuid
import json
import os

app = Flask(__name__)

# Datos en memoria (simula base de datos)
CITAS = {}  # {id: {email, matricula, fecha_hora, motivo, marca, modelo, estado}}
CLIENTES = {
    "test@test.com": {
        "id_cliente": 1,
        "email": "test@test.com",
        "nombre": "Usuario Test",
        "telefono": "123456789"
    }
}
VEHICULOS = {
    "ABC123": {
        "matricula": "ABC123",
        "marca": "Toyota",
        "modelo": "Corolla",
        "año": 2020,
        "email": "test@test.com"
    },
    "XYZ789": {
        "matricula": "XYZ789",
        "marca": "Honda",
        "modelo": "Civic",
        "año": 2021,
        "email": "test@test.com"
    }
}

FAQS = [
    {
        "pregunta": "¿Cuál es el horario de atención?",
        "respuesta": "Atendemos de lunes a viernes de 9:00 a 18:00 horas."
    },
    {
        "pregunta": "¿Cómo reservar una cita?",
        "respuesta": "Puedes reservar una cita indicando tu matrícula y la fecha deseada. El taller confirmará disponibilidad."
    },
    {
        "pregunta": "¿Cuánto cuesta una revisión?",
        "respuesta": "La revisión básica cuesta €50. Otros servicios tienen precios según el tipo de reparación."
    },
    {
        "pregunta": "¿Puedo modificar mi cita?",
        "respuesta": "Sí, puedes modificar tu cita hasta 24 horas antes. Contacta con nosotros."
    },
    {
        "pregunta": "¿Dónde está el taller?",
        "respuesta": "Estamos en Calle Principal 123, Madrid. Tel: 91-234-5678"
    }
]


def generar_id_cita():
    """Genera un ID único para la cita"""
    return str(uuid.uuid4())[:8].upper()


def respuesta_json(datos: Dict[str, Any], status: int = 200) -> tuple:
    """Formatea respuesta JSON con headers CORS"""
    response = jsonify(datos)
    response.headers['Content-Type'] = 'application/json'
    response.headers['Access-Control-Allow-Origin'] = '*'
    return response, status


@app.before_request
def handle_preflight():
    """Manejo CORS preflight"""
    if request.method == 'OPTIONS':
        response = jsonify({})
        response.headers['Access-Control-Allow-Origin'] = '*'
        response.headers['Access-Control-Allow-Methods'] = 'GET, POST, PUT, DELETE, OPTIONS'
        response.headers['Access-Control-Allow-Headers'] = 'Content-Type'
        return response, 200


@app.route('/chatbot/gestionar_citas.php', methods=['GET', 'POST', 'OPTIONS'])
def gestionar_citas():
    """Endpoint principal para gestionar citas"""
    action = request.args.get('action', 'listar')
    
    if action == 'reservar':
        return handle_reservar()
    elif action == 'ver_estado_coche':
        return handle_ver_estado_coche()
    elif action == 'modificar':
        return handle_modificar()
    elif action == 'cancelar':
        return handle_cancelar()
    elif action == 'listar_citas':
        return handle_listar_citas()
    elif action == 'buscar_cita':
        return handle_buscar_cita()
    elif action == 'faqs':
        return handle_faqs()
    else:
        return respuesta_json({
            "error": "Acción no reconocida",
            "acciones_disponibles": [
                "reservar", "ver_estado_coche", "modificar", 
                "cancelar", "listar_citas", "buscar_cita", "faqs"
            ]
        }, 400)


def handle_reservar():
    """Reservar una cita"""
    try:
        # Obtener datos del POST
        data = request.get_json() or {}
        email = data.get('email', '').strip()
        matricula = data.get('matricula', '').strip().upper()
        fecha_hora = data.get('fecha_hora', '').strip()
        motivo = data.get('motivo', 'Revisión general').strip()
        marca = data.get('marca', '').strip()
        modelo = data.get('modelo', '').strip()

        # Validar datos
        if not email or not matricula or not fecha_hora:
            return respuesta_json({
                "success": False,
                "error": "Faltan datos requeridos (email, matricula, fecha_hora)",
                "email": email,
                "matricula": matricula,
                "fecha_hora": fecha_hora
            }, 400)

        # Verificar que el vehículo existe
        if matricula not in VEHICULOS:
            return respuesta_json({
                "success": False,
                "error": f"Vehículo con matrícula {matricula} no encontrado en el sistema"
            }, 404)

        # Crear cita
        id_cita = generar_id_cita()
        cita = {
            "id_cita": id_cita,
            "email": email,
            "matricula": matricula,
            "fecha_hora": fecha_hora,
            "motivo": motivo,
            "marca": marca or VEHICULOS[matricula].get('marca', ''),
            "modelo": modelo or VEHICULOS[matricula].get('modelo', ''),
            "estado": "pendiente",
            "fecha_creacion": datetime.now().isoformat()
        }

        CITAS[id_cita] = cita

        return respuesta_json({
            "success": True,
            "message": f"Cita reservada correctamente. Número: {id_cita}",
            "id_cita": id_cita,
            "email": email,
            "matricula": matricula,
            "fecha_hora": fecha_hora,
            "motivo": motivo,
            "estado": "pendiente"
        }, 201)

    except Exception as e:
        return respuesta_json({
            "success": False,
            "error": str(e)
        }, 500)


def handle_ver_estado_coche():
    """Ver estado del coche"""
    try:
        matricula = request.args.get('matricula', '').strip().upper()
        email = request.args.get('email', '').strip()

        if not matricula and not email:
            return respuesta_json({
                "success": False,
                "error": "Se requiere matrícula o email"
            }, 400)

        # Buscar vehículo
        vehiculo = None
        if matricula:
            vehiculo = VEHICULOS.get(matricula)
        elif email:
            for v in VEHICULOS.values():
                if v.get('email') == email:
                    vehiculo = v
                    matricula = v['matricula']
                    break

        if not vehiculo:
            return respuesta_json({
                "success": False,
                "error": "Vehículo no encontrado"
            }, 404)

        # Buscar citas del vehículo
        citas = [c for c in CITAS.values() if c['matricula'] == matricula]

        return respuesta_json({
            "success": True,
            "vehiculo": vehiculo,
            "citas": citas,
            "cita_proxima": citas[0] if citas else None
        }, 200)

    except Exception as e:
        return respuesta_json({
            "success": False,
            "error": str(e)
        }, 500)


def handle_modificar():
    """Modificar una cita"""
    try:
        data = request.get_json() or {}
        id_cita = data.get('id_cita', '').strip().upper()
        nueva_fecha = data.get('fecha_hora', '').strip()

        if not id_cita or not nueva_fecha:
            return respuesta_json({
                "success": False,
                "error": "Se requiere id_cita y fecha_hora"
            }, 400)

        if id_cita not in CITAS:
            return respuesta_json({
                "success": False,
                "error": f"Cita {id_cita} no encontrada"
            }, 404)

        CITAS[id_cita]['fecha_hora'] = nueva_fecha
        CITAS[id_cita]['fecha_modificacion'] = datetime.now().isoformat()

        return respuesta_json({
            "success": True,
            "message": f"Cita {id_cita} modificada correctamente",
            "cita": CITAS[id_cita]
        }, 200)

    except Exception as e:
        return respuesta_json({
            "success": False,
            "error": str(e)
        }, 500)


def handle_cancelar():
    """Cancelar una cita"""
    try:
        id_cita = request.args.get('id_cita', '').strip().upper()

        if not id_cita:
            return respuesta_json({
                "success": False,
                "error": "Se requiere id_cita"
            }, 400)

        if id_cita not in CITAS:
            return respuesta_json({
                "success": False,
                "error": f"Cita {id_cita} no encontrada"
            }, 404)

        CITAS[id_cita]['estado'] = 'cancelada'
        CITAS[id_cita]['fecha_cancelacion'] = datetime.now().isoformat()

        return respuesta_json({
            "success": True,
            "message": f"Cita {id_cita} cancelada correctamente",
            "cita": CITAS[id_cita]
        }, 200)

    except Exception as e:
        return respuesta_json({
            "success": False,
            "error": str(e)
        }, 500)


def handle_listar_citas():
    """Listar todas las citas"""
    try:
        email = request.args.get('email', '').strip()
        
        if email:
            citas = [c for c in CITAS.values() if c['email'] == email]
        else:
            citas = list(CITAS.values())

        return respuesta_json({
            "success": True,
            "total": len(citas),
            "citas": citas
        }, 200)

    except Exception as e:
        return respuesta_json({
            "success": False,
            "error": str(e)
        }, 500)


def handle_buscar_cita():
    """Buscar una cita específica"""
    try:
        id_cita = request.args.get('id_cita', '').strip().upper()

        if not id_cita:
            return respuesta_json({
                "success": False,
                "error": "Se requiere id_cita"
            }, 400)

        if id_cita not in CITAS:
            return respuesta_json({
                "success": False,
                "error": f"Cita {id_cita} no encontrada"
            }, 404)

        return respuesta_json({
            "success": True,
            "cita": CITAS[id_cita]
        }, 200)

    except Exception as e:
        return respuesta_json({
            "success": False,
            "error": str(e)
        }, 500)


def handle_faqs():
    """Obtener preguntas frecuentes"""
    try:
        return respuesta_json({
            "success": True,
            "faqs": FAQS,
            "total": len(FAQS)
        }, 200)

    except Exception as e:
        return respuesta_json({
            "success": False,
            "error": str(e)
        }, 500)


@app.route('/chatbot/gestionar_faqs.php', methods=['GET', 'POST', 'OPTIONS'])
def gestionar_faqs():
    """Endpoint para FAQs"""
    return respuesta_json({
        "success": True,
        "faqs": FAQS,
        "total": len(FAQS)
    }, 200)


@app.route('/health', methods=['GET'])
def health():
    """Health check"""
    return respuesta_json({
        "status": "ok",
        "server": "AutoSync Chatbot Mock Server",
        "citas_totales": len(CITAS),
        "vehiculos": len(VEHICULOS)
    }, 200)


@app.errorhandler(404)
def not_found(error):
    """Manejo de rutas no encontradas"""
    return respuesta_json({
        "success": False,
        "error": "Ruta no encontrada"
    }, 404)


if __name__ == '__main__':
    print("=" * 60)
    print("🚀 AutoSync Chatbot Mock Server")
    print("=" * 60)
    print("\n✅ Servidor escuchando en: http://127.0.0.1:8888")
    print("\n📍 Endpoints disponibles:")
    print("  - POST   /chatbot/gestionar_citas.php?action=reservar")
    print("  - GET    /chatbot/gestionar_citas.php?action=ver_estado_coche&matricula=ABC123")
    print("  - POST   /chatbot/gestionar_citas.php?action=modificar")
    print("  - GET    /chatbot/gestionar_citas.php?action=cancelar&id_cita=ABC123")
    print("  - GET    /chatbot/gestionar_citas.php?action=listar_citas")
    print("  - GET    /chatbot/gestionar_citas.php?action=buscar_cita&id_cita=ABC123")
    print("  - GET    /chatbot/gestionar_citas.php?action=faqs")
    print("  - GET    /chatbot/gestionar_faqs.php")
    print("  - GET    /health")
    print("\n🚗 Vehículos de prueba:")
    print("  - ABC123 (Toyota Corolla)")
    print("  - XYZ789 (Honda Civic)")
    print("\n📧 Email de prueba: test@test.com")
    print("\n💡 Presiona Ctrl+C para detener\n")
    
    app.run(
        host='127.0.0.1',
        port=8888,
        debug=True,
        use_reloader=False
    )
