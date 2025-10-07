# API Documentation - Sistema de Registro de Veterinarias

## Descripción General

Este sistema proporciona endpoints para el registro y gestión de veterinarias con todos los campos requeridos, incluyendo manejo de archivos para logos y validación de términos y condiciones.

## Base URL
```
http://localhost:8000/api
```

## Autenticación

Este sistema utiliza **Bearer Token Authentication** para proteger los endpoints sensibles.

### Obtener Token de Acceso
**POST** `/auth/login`

**Headers:**
```
Content-Type: application/json
```

**Parámetros:**
```json
{
    "usuario": "nombre_usuario",
    "password": "contraseña"
}
```

**Respuesta Exitosa:**
```json
{
    "success": true,
    "message": "Login exitoso",
    "data": {
        "veterinaria": {
            "id": 1,
            "veterinaria": "Veterinaria Central",
            "responsable": "Dr. Juan Pérez",
            "email": "info@veterinaria.com",
            "usuario": "vet_central"
        },
        "token": "1|abcdef123456...",
        "token_type": "Bearer"
    }
}
```

### Usar Token en Requests
Para acceder a endpoints protegidos, incluye el token en el header:

```
Authorization: Bearer 1|abcdef123456...
```

### Endpoints de Autenticación Protegidos

#### Logout
**POST** `/auth/logout`
- Requiere: Token de autorización
- Revoca el token actual

#### Información del Usuario
**GET** `/auth/me`
- Requiere: Token de autorización
- Retorna información del usuario autenticado

#### Renovar Token
**POST** `/auth/refresh`
- Requiere: Token de autorización
- Genera un nuevo token y revoca el anterior

## Endpoints Disponibles

### 1. Obtener Países Válidos
**GET** `/veterinarias/paises`

Obtiene la lista de países válidos para el registro.

**Respuesta:**
```json
{
    "success": true,
    "data": [
        "GUATEMALA",
        "EL SALVADOR", 
        "NICARAGUA",
        "COSTA RICA"
    ],
    "message": "Países obtenidos exitosamente"
}
```

### 2. Registro de Veterinaria
**POST** `/veterinarias/registro`

Registra una nueva veterinaria en el sistema.

**Headers:**
```
Content-Type: application/json
```

**Parámetros:**
| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| veterinaria | string | Sí | Nombre de la veterinaria |
| responsable | string | Sí | Nombre del responsable |
| direccion | text | Sí | Dirección completa |
| telefono | string | Sí | Número de teléfono |
| email | string | Sí | Email (único) |
| registro_oficial_veterinario | string | Sí | Número de registro oficial |
| ciudad | string | Sí | Ciudad |
| provincia_departamento | string | Sí | Provincia o departamento |
| pais | string | Sí | País (debe ser uno de los válidos) |
| logo | string | No | URL de la imagen del logo (máx 500 caracteres) |
| usuario | string | Sí | Nombre de usuario (único) |
| password | string | Sí | Contraseña (mínimo 8 caracteres) |
| repetir_password | string | Sí | Confirmación de contraseña |
| acepta_terminos | boolean | Sí | Debe ser true |
| acepta_tratamiento_datos | boolean | Sí | Debe ser true |

**Ejemplo de solicitud:**
```bash
curl -X POST http://localhost:8000/api/veterinarias/registro \
  -F "veterinaria=Veterinaria San José" \
  -F "responsable=Dr. Juan Pérez" \
  -F "direccion=Av. Principal 123, San José" \
  -F "telefono=+506 2222-3333" \
  -F "email=info@veterinariasanjose.com" \
  -F "registro_oficial_veterinario=VET-2024-001" \
  -F "ciudad=San José" \
  -F "provincia_departamento=San José" \
  -F "pais=COSTA RICA" \
  -F "logo=@/path/to/logo.png" \
  -F "usuario=veterinaria_sj" \
  -F "password=password123" \
  -F "repetir_password=password123" \
  -F "acepta_terminos=true" \
  -F "acepta_tratamiento_datos=true"
```

**Respuesta exitosa:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "veterinaria": "Veterinaria San José",
        "responsable": "Dr. Juan Pérez",
        "direccion": "Av. Principal 123, San José",
        "telefono": "+506 2222-3333",
        "email": "info@veterinariasanjose.com",
        "registro_oficial_veterinario": "VET-2024-001",
        "ciudad": "San José",
        "provincia_departamento": "San José",
        "pais": "COSTA RICA",
        "logo": "1703123456_abc123.png",
        "usuario": "veterinaria_sj",
        "acepta_terminos": true,
        "acepta_tratamiento_datos": true,
        "logo_url": "http://localhost:8000/storage/logos/1703123456_abc123.png",
        "created_at": "2024-01-01T12:00:00.000000Z",
        "updated_at": "2024-01-01T12:00:00.000000Z"
    },
    "message": "Veterinaria registrada exitosamente"
}
```

### 3. Listar Veterinarias (Requiere Autenticación)
**GET** `/veterinarias`

**Headers:**
```
Authorization: Bearer {token}
```

**Parámetros de consulta opcionales:**
- `pais`: Filtrar por país
- `ciudad`: Filtrar por ciudad  
- `search`: Buscar por nombre, responsable o email
- `per_page`: Número de resultados por página (default: 15)
- `page`: Número de página

**Ejemplo:**
```bash
curl -X GET "http://localhost:8000/api/veterinarias?pais=COSTA RICA&per_page=10" \
  -H "Authorization: Bearer {token}"
```

### 4. Obtener Veterinaria Específica (Requiere Autenticación)
**GET** `/veterinarias/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

### 5. Actualizar Veterinaria (Requiere Autenticación)
**PUT** `/veterinarias/{id}`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Parámetros:** Los mismos que el registro, pero:
- `password` y `repetir_password` son opcionales
- `acepta_terminos` y `acepta_tratamiento_datos` son opcionales

### 6. Eliminar Veterinaria (Requiere Autenticación)
**DELETE** `/veterinarias/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

## Códigos de Respuesta

| Código | Descripción |
|--------|-------------|
| 200 | Operación exitosa |
| 201 | Recurso creado exitosamente |
| 404 | Recurso no encontrado |
| 422 | Error de validación |
| 401 | No autorizado |
| 500 | Error interno del servidor |

## Estructura de Errores

**Error de validación (422):**
```json
{
    "success": false,
    "errors": {
        "email": ["Este email ya está registrado."],
        "password": ["La contraseña debe tener al menos 8 caracteres."]
    },
    "message": "Error de validación"
}
```

**Error general:**
```json
{
    "success": false,
    "message": "Descripción del error"
}
```

## Validaciones Importantes

### Campos Únicos
- `email`: Debe ser único en toda la tabla
- `usuario`: Debe ser único en toda la tabla

### Validaciones de URL (Logo)
- Debe ser una URL válida
- Longitud máxima: 500 caracteres
- Extensiones recomendadas: jpg, jpeg, png, gif, svg, webp

### Países Válidos
Solo se aceptan estos países:
- GUATEMALA
- EL SALVADOR
- NICARAGUA
- COSTA RICA

### Términos y Condiciones
- `acepta_terminos`: Debe ser `true`
- `acepta_tratamiento_datos`: Debe ser `true`

## Notas de Implementación

1. **Autenticación**: Los endpoints protegidos requieren autenticación con Sanctum
2. **URLs de Logo**: Se almacenan como URLs externas, no como archivos locales
3. **Validación de URLs**: Se valida formato y longitud, opcionalmente extensión
4. **Paginación**: Las listas utilizan paginación de Laravel
5. **Filtros**: Se pueden combinar múltiples filtros en las consultas

## Configuración Requerida

Antes de usar la API, asegúrate de:

1. Ejecutar las migraciones:
```bash
php artisan migrate
```

2. Crear el enlace simbólico de storage:
```bash
php artisan storage:link
```

3. Configurar las variables de entorno necesarias en `.env`