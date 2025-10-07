# Sistema de Registro de Veterinarias - BioNote

## Descripción General

Este sistema permite el registro y gestión de veterinarias en países de Centroamérica, proporcionando una API completa para operaciones CRUD y manejo de URLs de logos.

## Características Principales

- ✅ Registro de veterinarias con validación completa
- ✅ Gestión de URLs de logos
- ✅ Soporte para múltiples países de Centroamérica
- ✅ API RESTful con autenticación
- ✅ Validaciones robustas de datos
- ✅ Comandos de consola para gestión
- ✅ Seeders de ejemplo
- ✅ Configuración centralizada

## Países Soportados

- Guatemala
- El Salvador
- Honduras
- Nicaragua
- Costa Rica
- Panamá
- Belice

## Instalación y Configuración

### 1. Ejecutar Migraciones

```bash
php artisan migrate
```

### 2. Ejecutar Seeders (Opcional)

```bash
php artisan db:seed --class=VeterinariaSeeder
```

### 3. Configuración Adicional

El sistema está configurado para manejar URLs de logos externos, no requiere configuración adicional de storage.

## Estructura de Archivos

```
app/
├── Models/
│   └── Veterinaria.php              # Modelo principal
├── Http/
│   ├── Controllers/
│   │   └── VeterinariaController.php # Controlador CRUD
│   └── Requests/
│       ├── StoreVeterinariaRequest.php
│       └── UpdateVeterinariaRequest.php
├── Services/
│   └── FileUploadService.php        # Servicio de archivos
└── Console/
    └── Commands/
        └── VeterinariaCommand.php    # Comandos CLI

database/
├── migrations/
│   └── 2024_01_01_000003_create_veterinarias_table.php
└── seeders/
    └── VeterinariaSeeder.php

config/
└── veterinarias.php                 # Configuración del sistema

routes/
└── api.php                          # Rutas API
```

## API Endpoints

### Públicos

- `GET /api/veterinarias/paises` - Obtener países válidos
- `POST /api/veterinarias/register` - Registrar nueva veterinaria

### Protegidos (requieren autenticación)

- `GET /api/veterinarias` - Listar veterinarias
- `GET /api/veterinarias/{id}` - Obtener veterinaria específica
- `PUT /api/veterinarias/{id}` - Actualizar veterinaria
- `DELETE /api/veterinarias/{id}` - Eliminar veterinaria

## Comandos de Consola

### Listar Veterinarias

```bash
# Listar todas
php artisan veterinaria:manage list

# Filtrar por país
php artisan veterinaria:manage list --pais=GUATEMALA

# Filtrar por ciudad
php artisan veterinaria:manage list --ciudad="Ciudad de Guatemala"
```

### Crear Veterinaria

```bash
php artisan veterinaria:manage create
```

### Ver Veterinaria

```bash
php artisan veterinaria:manage show --id=1
```

### Eliminar Veterinaria

```bash
php artisan veterinaria:manage delete --id=1
```

## Configuración

El archivo `config/veterinarias.php` contiene todas las configuraciones del sistema:

```php
'logo' => [
    'max_url_length' => 500,         // Caracteres máximos
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
    'validate_accessibility' => false,
    'default_placeholder' => 'https://via.placeholder.com/150x150/cccccc/666666?text=Logo',
],

'paises_validos' => [
    'GUATEMALA', 'EL SALVADOR', 'HONDURAS',
    'NICARAGUA', 'COSTA RICA', 'PANAMA', 'BELICE'
],
```

## Validaciones

### Campos Requeridos

- Nombre de la veterinaria
- Responsable
- Dirección
- Teléfono
- Email (único)
- Registro oficial veterinario
- Ciudad
- Provincia/Departamento
- País (debe estar en la lista válida)
- Usuario (único)
- Contraseña
- Aceptación de términos y condiciones
- Aceptación de tratamiento de datos

### Validaciones de Logo

- Debe ser una URL válida
- Longitud máxima: 500 caracteres
- Extensiones recomendadas: JPG, JPEG, PNG, GIF, SVG, WEBP

## Ejemplos de Uso

### Registro de Veterinaria (cURL)

```bash
curl -X POST http://localhost:8000/api/veterinarias/register \
  -H "Content-Type: application/json" \
  -d '{
    "veterinaria": "Veterinaria Central",
    "responsable": "Dr. Juan Pérez",
    "direccion": "Zona 1, Ciudad de Guatemala",
    "telefono": "+502 2234-5678",
    "email": "info@vetcentral.gt",
    "registro_oficial_veterinario": "VET-GT-2024-001",
    "ciudad": "Ciudad de Guatemala",
    "provincia_departamento": "Guatemala",
    "pais": "GUATEMALA",
    "usuario": "vet_central",
    "password": "password123",
    "repetir_password": "password123",
    "acepta_terminos": true,
    "acepta_tratamiento_datos": true,
    "logo": "https://example.com/logo.png"
  }'
```

### Obtener Países Válidos

```bash
curl -X GET http://localhost:8000/api/veterinarias/paises
```

## Seguridad

- Las contraseñas se almacenan hasheadas usando bcrypt
- Los emails deben ser únicos en el sistema
- Los usuarios deben ser únicos en el sistema
- Validación de tipos de archivo para prevenir uploads maliciosos
- Sanitización de datos de entrada

## Mantenimiento

### Limpiar Logos Huérfanos

```bash
# Comando personalizado para limpiar archivos no utilizados
php artisan storage:clean-logos
```

### Backup de Datos

```bash
# Exportar datos de veterinarias
php artisan veterinaria:export --format=json
```

## Troubleshooting

### Error: "Storage link not found"

```bash
php artisan storage:link
```

### Error: "Directory not writable"

```bash
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

### Error: "Class not found"

```bash
composer dump-autoload
```

## Contribución

1. Seguir las convenciones de código de Laravel
2. Escribir tests para nuevas funcionalidades
3. Actualizar documentación cuando sea necesario
4. Usar los Form Requests existentes para validaciones

## Licencia

Este sistema es parte del proyecto BioNote y está sujeto a las mismas condiciones de licencia del proyecto principal.