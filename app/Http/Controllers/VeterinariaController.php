<?php

namespace App\Http\Controllers;

use App\Models\Veterinaria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VeterinariaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Veterinaria::query();

        // Control de acceso por rol/guard:
        $actor = $request->user();
        if ($actor instanceof User) {
            // Admin: acceso total (verifica rol por seguridad)
            if (!$actor->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado'
                ], 403);
            }
        } else {
            // Veterinaria: solo su propio registro
            $query->where('id', $actor->id);
        }

        // Filtros opcionales
        if ($request->has('pais')) {
            $query->porPais($request->pais);
        }

        if ($request->has('ciudad')) {
            $query->porCiudad($request->ciudad);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('veterinaria', 'like', "%{$search}%")
                  ->orWhere('responsable', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $veterinarias = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $veterinarias,
            'message' => 'Veterinarias obtenidas exitosamente'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        // Log de inicio del registro
        \Log::info('=== INICIO REGISTRO VETERINARIA ===', [
            'timestamp' => now(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method(),
        ]);

        // Log de todos los datos recibidos (sin contraseñas)
        $requestData = $request->except(['password', 'repetir_password']);
        \Log::info('Datos recibidos en registro:', [
            'datos' => $requestData,
            'has_password' => $request->has('password'),
            'has_repetir_password' => $request->has('repetir_password'),
            'acepta_terminos' => $request->input('acepta_terminos'),
            'acepta_tratamiento_datos' => $request->input('acepta_tratamiento_datos'),
        ]);

        $validator = $this->validateVeterinaria($request);

        if ($validator->fails()) {
            // Log detallado de errores de validación
            $errors = $validator->errors();
            \Log::error('ERROR DE VALIDACIÓN EN REGISTRO VETERINARIA:', [
                'errors' => $errors->toArray(),
                'errors_count' => $errors->count(),
                'failed_rules' => $errors->all(),
                'datos_enviados' => $requestData,
            ]);

            return response()->json([
                'success' => false,
                'errors' => $errors,
                'message' => 'Error de validación'
            ], 422);
        }

        // Verificar que las contraseñas coincidan
        if ($request->password !== $request->repetir_password) {
            \Log::warning('ERROR: Las contraseñas no coinciden en registro', [
                'password_length' => strlen($request->password ?? ''),
                'repetir_password_length' => strlen($request->repetir_password ?? ''),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Las contraseñas no coinciden'
            ], 422);
        }

        // Verificar términos y condiciones
        if (!$request->acepta_terminos || !$request->acepta_tratamiento_datos) {
            \Log::warning('ERROR: Términos y condiciones no aceptados', [
                'acepta_terminos' => $request->acepta_terminos,
                'acepta_tratamiento_datos' => $request->acepta_tratamiento_datos,
                'acepta_terminos_type' => gettype($request->acepta_terminos),
                'acepta_tratamiento_datos_type' => gettype($request->acepta_tratamiento_datos),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Debe aceptar los términos y condiciones y el tratamiento de datos'
            ], 422);
        }

        $data = $request->except(['repetir_password']);
        $hashedPassword = Hash::make($request->password);
        $data['password'] = $hashedPassword;
        
        // Manejo mejorado de logo: aceptar archivo o URL
        // Verificar múltiples formas de detectar archivos desde Flutter
        $hasLogoFile = $request->hasFile('logo') || 
                       (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK);
        
        \Log::info('Logo file detection:', [
            'hasFile_logo' => $request->hasFile('logo'),
            'files_logo_exists' => isset($_FILES['logo']),
            'files_logo_error' => $_FILES['logo']['error'] ?? 'not_set',
            'hasLogoFile' => $hasLogoFile
        ]);
        
        if ($hasLogoFile && ($request->hasFile('logo') ? $request->file('logo')->isValid() : true)) {
            try {
                // Obtener archivo de diferentes maneras según como lo envíe Flutter
                $logoFile = null;
                $originalName = '';
                $mimeType = '';
                $fileSize = 0;
                $tempPath = '';
                
                if ($request->hasFile('logo')) {
                    // Método estándar de Laravel
                    $logoFile = $request->file('logo');
                    $originalName = $logoFile->getClientOriginalName();
                    $mimeType = $logoFile->getMimeType();
                    $fileSize = $logoFile->getSize();
                    $tempPath = $logoFile->getPathname();
                } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    // Método directo de PHP para casos donde Laravel no detecta el archivo
                    $originalName = $_FILES['logo']['name'];
                    $mimeType = $_FILES['logo']['type'];
                    $fileSize = $_FILES['logo']['size'];
                    $tempPath = $_FILES['logo']['tmp_name'];
                }
                
                // Log adicional para debugging
                \Log::info('File detected for update:', [
                    'original_name' => $originalName,
                    'mime_type' => $mimeType,
                    'size' => $fileSize,
                    'temp_path' => $tempPath,
                    'method' => $logoFile ? 'laravel' : 'php_direct'
                ]);
                
                // Verificar que tenemos un archivo válido
                if (empty($tempPath) || !file_exists($tempPath)) {
                    throw new \Exception('No se pudo acceder al archivo subido');
                }
                
                // Verificar tamaño del archivo (5MB máximo)
                if ($fileSize > 5120 * 1024) {
                    throw new \Exception('El archivo es demasiado grande. Máximo 5MB permitido');
                }
                
                if ($fileSize === 0) {
                    throw new \Exception('El archivo está vacío');
                }
                
                // Validación adicional del tipo de archivo basada en extensión
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
                $allowedMimeTypes = [
                    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 
                    'image/svg+xml', 'image/webp', 'application/octet-stream'
                ];
                
                // Obtener extensión del nombre original
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                
                // Verificar extensión
                if (!in_array($extension, $allowedExtensions)) {
                    throw new \Exception('Tipo de archivo no permitido. Solo se permiten: ' . implode(', ', $allowedExtensions));
                }
                
                // Verificar MIME type (más flexible)
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    throw new \Exception('Tipo MIME no permitido: ' . $mimeType);
                }
                
                // Debug: Verificar propiedades del archivo
                \Log::info('Logo file info:', [
                    'original_name' => $originalName,
                    'mime_type' => $mimeType,
                    'size' => $fileSize,
                    'temp_path' => $tempPath
                ]);
                
                // Obtener extensión de forma segura
                if (empty($extension)) {
                    // Fallback: obtener extensión desde el mime type
                    $extension = match($mimeType) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/svg+xml' => 'svg',
                        'image/webp' => 'webp',
                        default => 'jpg'
                    };
                }
                
                // Generar nombre único para el archivo
                $filename = time() . '_' . uniqid() . '.' . $extension;
                
                // Crear directorio si no existe
                Storage::disk('public')->makeDirectory('logos');
                
                // Método alternativo: usar move en lugar de putFileAs
                $destinationPath = storage_path('app/public/logos');
                
                // Asegurar que el directorio existe
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                // Mover el archivo manualmente
                $fullPath = $destinationPath . DIRECTORY_SEPARATOR . $filename;
                
                if (move_uploaded_file($tempPath, $fullPath)) {
                    $path = 'logos/' . $filename;
                    \Log::info('File moved successfully to: ' . $fullPath);
                } else {
                    throw new \Exception('Error al mover el archivo de logo');
                }
                
                // Verificar que el archivo se guardó correctamente
                if (!file_exists($fullPath)) {
                    throw new \Exception('Error: el archivo no se encontró después de guardarlo en: ' . $fullPath);
                }
                
                // Guardar solo el nombre del archivo para compatibilidad con el accessor
                $data['logo'] = $filename;
                
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Error de validación del archivo'
                ], 422);
            } catch (\Exception $e) {
                \Log::error('Error uploading logo: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'errors' => ['logo' => ['Error al procesar el archivo de logo: ' . $e->getMessage()]],
                    'message' => 'Error al subir el logo'
                ], 422);
            }
        } elseif ($request->filled('logo')) {
            // Validar URL remota
            if (!filter_var($request->logo, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['logo' => ['La URL del logo no es válida']],
                    'message' => 'Error en la URL del logo'
                ], 422);
            }
            $data['logo'] = $request->logo;
        }

        // Usar transacción para asegurar que ambos registros se creen correctamente
        DB::beginTransaction();
        
        try {
            \Log::info('Intentando crear veterinaria en base de datos...', [
                'email' => $request->email,
                'usuario' => $request->usuario,
            ]);

            // Crear la veterinaria
            $veterinaria = Veterinaria::create($data);
            \Log::info('Veterinaria creada exitosamente', [
                'veterinaria_id' => $veterinaria->id,
                'email' => $veterinaria->email,
            ]);

            // Crear el usuario correspondiente en la tabla users
            \Log::info('Intentando crear usuario en tabla users...');
            $user = User::create([
                'name' => $request->responsable,
                'email' => $request->email,
                'password' => $hashedPassword,
            ]);
            \Log::info('Usuario creado exitosamente', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            DB::commit();

            \Log::info('=== REGISTRO VETERINARIA EXITOSO ===', [
                'veterinaria_id' => $veterinaria->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $veterinaria,
                'message' => 'Veterinaria y usuario registrados exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            // Log detallado del error
            \Log::error('ERROR AL REGISTRAR VETERINARIA:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? 'no_provided',
                'usuario' => $request->usuario ?? 'no_provided',
            ]);
            
            // Si hubo error y se subió un archivo, eliminarlo
            if (isset($data['logo']) && !filter_var($data['logo'], FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete('logos/' . $data['logo']);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la veterinaria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $actor = request()->user();
        if ($actor instanceof User) {
            if (!$actor->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado'
                ], 403);
            }
        } else {
            if ((int) $id !== (int) $actor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado'
                ], 403);
            }
        }

        $veterinaria = Veterinaria::find($id);

        if (!$veterinaria) {
            return response()->json([
                'success' => false,
                'message' => 'Veterinaria no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $veterinaria,
            'message' => 'Veterinaria obtenida exitosamente'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // Log de entrada para verificar que la request llega al método
        \Log::info('=== UPDATE METHOD CALLED ===', [
            'veterinaria_id' => $id,
            'timestamp' => now(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent')
        ]);
        
        $actor = $request->user();
        if ($actor instanceof User) {
            if (!$actor->isAdmin()) {
                \Log::warning('Update failed: User not admin');
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado'
                ], 403);
            }
        } else {
            if ((int) $id !== (int) $actor->id) {
                \Log::warning('Update failed: Veterinaria ID mismatch');
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado'
                ], 403);
            }
        }

        $veterinaria = Veterinaria::find($id);

        if (!$veterinaria) {
            return response()->json([
                'success' => false,
                'message' => 'Veterinaria no encontrada'
            ], 404);
        }

        $validator = $this->validateVeterinaria($request, $id);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Error de validación'
            ], 422);
        }

        $data = $request->except(['repetir_password', 'password']);
        $hashedPassword = null;

        // Debug: Log de información detallada del request
        \Log::info('=== UPDATE REQUEST DEBUG START ===');
        \Log::info('Update request debug info:', [
            'veterinaria_id' => $id,
            'has_file_logo' => $request->hasFile('logo'),
            'filled_logo' => $request->filled('logo'),
            'all_files' => $request->allFiles(),
            'all_input' => $request->except(['password', 'repetir_password']),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method(),
            'raw_files' => $_FILES,
            'request_size' => $request->header('Content-Length'),
            'user_agent' => $request->header('User-Agent')
        ]);
        
        // Log específico de $_FILES para debugging
        if (isset($_FILES['logo'])) {
            \Log::info('$_FILES[logo] details:', [
                'name' => $_FILES['logo']['name'] ?? 'not_set',
                'type' => $_FILES['logo']['type'] ?? 'not_set', 
                'size' => $_FILES['logo']['size'] ?? 'not_set',
                'tmp_name' => $_FILES['logo']['tmp_name'] ?? 'not_set',
                'error' => $_FILES['logo']['error'] ?? 'not_set',
                'error_message' => $this->getUploadErrorMessage($_FILES['logo']['error'] ?? -1)
            ]);
        } else {
            \Log::info('$_FILES[logo] not found in request');
        }
        \Log::info('=== UPDATE REQUEST DEBUG END ===');

        // Solo actualizar contraseña si se proporciona
        if ($request->filled('password')) {
            if ($request->password !== $request->repetir_password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Las contraseñas no coinciden'
                ], 422);
            }
            $hashedPassword = Hash::make($request->password);
            $data['password'] = $hashedPassword;
        }

        // Manejo mejorado de logo: aceptar archivo o URL
        // Verificar múltiples formas de detectar archivos desde Flutter
        $hasLogoFile = $request->hasFile('logo') || 
                       (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK);
        
        \Log::info('Logo file detection (update):', [
            'hasFile_logo' => $request->hasFile('logo'),
            'files_logo_exists' => isset($_FILES['logo']),
            'files_logo_error' => $_FILES['logo']['error'] ?? 'not_set',
            'hasLogoFile' => $hasLogoFile
        ]);
        
        if ($hasLogoFile && ($request->hasFile('logo') ? $request->file('logo')->isValid() : true)) {
            try {
                // Obtener archivo de diferentes maneras según como lo envíe Flutter
                $logoFile = null;
                $originalName = '';
                $mimeType = '';
                $fileSize = 0;
                $tempPath = '';
                
                if ($request->hasFile('logo')) {
                    // Método estándar de Laravel
                    $logoFile = $request->file('logo');
                    $originalName = $logoFile->getClientOriginalName();
                    $mimeType = $logoFile->getMimeType();
                    $fileSize = $logoFile->getSize();
                    $tempPath = $logoFile->getPathname();
                } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    // Método directo de PHP para casos donde Laravel no detecta el archivo
                    $originalName = $_FILES['logo']['name'];
                    $mimeType = $_FILES['logo']['type'];
                    $fileSize = $_FILES['logo']['size'];
                    $tempPath = $_FILES['logo']['tmp_name'];
                }
                
                // Log adicional para debugging
                \Log::info('File detected for update:', [
                    'original_name' => $originalName,
                    'mime_type' => $mimeType,
                    'size' => $fileSize,
                    'temp_path' => $tempPath,
                    'method' => $logoFile ? 'laravel' : 'php_direct'
                ]);
                
                // Verificar que tenemos un archivo válido
                if (empty($tempPath) || !file_exists($tempPath)) {
                    throw new \Exception('No se pudo acceder al archivo subido');
                }
                
                // Verificar tamaño del archivo (5MB máximo)
                if ($fileSize > 5120 * 1024) {
                    throw new \Exception('El archivo es demasiado grande. Máximo 5MB permitido');
                }
                
                if ($fileSize === 0) {
                    throw new \Exception('El archivo está vacío');
                }
                
                // Validación adicional del tipo de archivo basada en extensión
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
                $allowedMimeTypes = [
                    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 
                    'image/svg+xml', 'image/webp', 'application/octet-stream'
                ];
                
                // Obtener extensión del nombre original
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                
                // Verificar extensión
                if (!in_array($extension, $allowedExtensions)) {
                    throw new \Exception('Tipo de archivo no permitido. Solo se permiten: ' . implode(', ', $allowedExtensions));
                }
                
                // Verificar MIME type (más flexible)
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    throw new \Exception('Tipo MIME no permitido: ' . $mimeType);
                }
                
                // Obtener extensión de forma segura
                if (empty($extension)) {
                    // Fallback: obtener extensión desde el mime type
                    $extension = match($mimeType) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/svg+xml' => 'svg',
                        'image/webp' => 'webp',
                        default => 'jpg'
                    };
                }
                
                // Generar nombre único para el archivo
                $filename = time() . '_' . uniqid() . '.' . $extension;
                
                // Crear directorio si no existe
                Storage::disk('public')->makeDirectory('logos');
                
                // Método alternativo: usar move en lugar de putFileAs
                $destinationPath = storage_path('app/public/logos');
                
                // Asegurar que el directorio existe
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                // Eliminar logo anterior si existe y no es una URL
                if ($veterinaria->logo && !filter_var($veterinaria->logo, FILTER_VALIDATE_URL)) {
                    $oldLogoPath = $destinationPath . DIRECTORY_SEPARATOR . $veterinaria->logo;
                    if (file_exists($oldLogoPath)) {
                        unlink($oldLogoPath);
                        \Log::info('Old logo deleted: ' . $oldLogoPath);
                    }
                }
                
                // Mover el archivo manualmente
                $fullPath = $destinationPath . DIRECTORY_SEPARATOR . $filename;
                
                if (move_uploaded_file($tempPath, $fullPath)) {
                    $path = 'logos/' . $filename;
                    \Log::info('File moved successfully to: ' . $fullPath);
                } else {
                    throw new \Exception('Error al mover el archivo de logo');
                }
                
                // Verificar que el archivo se guardó correctamente
                if (!file_exists($fullPath)) {
                    throw new \Exception('Error: el archivo no se encontró después de guardarlo en: ' . $fullPath);
                }
                
                // Guardar solo el nombre del archivo para compatibilidad con el accessor
                $data['logo'] = $filename;
                
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Error de validación del archivo'
                ], 422);
            } catch (\Exception $e) {
                \Log::error('Error uploading logo (update): ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'errors' => ['logo' => ['Error al procesar el archivo de logo: ' . $e->getMessage()]],
                    'message' => 'Error al subir el logo'
                ], 422);
            }
        } elseif ($request->filled('logo')) {
            // Validar URL remota
            if (!filter_var($request->logo, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['logo' => ['La URL del logo no es válida']],
                    'message' => 'Error en la URL del logo'
                ], 422);
            }
            $data['logo'] = $request->logo;
        }

        // Usar transacción para asegurar que ambas actualizaciones se realicen correctamente
        DB::beginTransaction();
        
        try {
            // Actualizar la veterinaria
            $veterinaria->update($data);

            // Buscar y actualizar el usuario correspondiente
            $user = User::where('email', $veterinaria->email)->first();
            if ($user) {
                $userData = [];
                
                // Actualizar nombre si se proporciona responsable
                if ($request->filled('responsable')) {
                    $userData['name'] = $request->responsable;
                }
                
                // Actualizar email si se proporciona
                if ($request->filled('email')) {
                    $userData['email'] = $request->email;
                }
                
                // Actualizar contraseña si se proporciona
                if ($hashedPassword) {
                    $userData['password'] = $hashedPassword;
                }
                
                if (!empty($userData)) {
                    $user->update($userData);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $veterinaria->fresh(),
                'message' => 'Veterinaria y usuario actualizados exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            // Si hubo error y se subió un archivo, eliminarlo
            if (isset($data['logo']) && !filter_var($data['logo'], FILTER_VALIDATE_URL)) {
                $logoPath = storage_path('app/public/logos/' . $data['logo']);
                if (file_exists($logoPath)) {
                    unlink($logoPath);
                    \Log::info('Logo file deleted due to rollback: ' . $logoPath);
                }
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la veterinaria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $actor = request()->user();
        if ($actor instanceof User) {
            if (!$actor->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado'
                ], 403);
            }
        } else {
            if ((int) $id !== (int) $actor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado'
                ], 403);
            }
        }

        $veterinaria = Veterinaria::find($id);

        if (!$veterinaria) {
            return response()->json([
                'success' => false,
                'message' => 'Veterinaria no encontrada'
            ], 404);
        }

        // Usar transacción para asegurar que ambas eliminaciones se realicen correctamente
        DB::beginTransaction();
        
        try {
            // Buscar y eliminar el usuario correspondiente
            $user = User::where('email', $veterinaria->email)->first();
            if ($user) {
                $user->delete();
            }

            // Eliminar la veterinaria
            $veterinaria->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Veterinaria y usuario eliminados exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la veterinaria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar datos de veterinaria
     */
    private function validateVeterinaria(Request $request, $id = null): \Illuminate\Validation\Validator
    {
        // Para actualizaciones, solo validar campos que se están enviando
        $isUpdate = !is_null($id);
        
        \Log::info('Iniciando validación de veterinaria', [
            'is_update' => $isUpdate,
            'id' => $id,
        ]);
        
        if ($isUpdate) {
            $rules = [
                'veterinaria' => 'sometimes|required|string|max:255',
                'responsable' => 'sometimes|required|string|max:255',
                'direccion' => 'sometimes|required|string',
                'telefono' => 'sometimes|required|string|max:20',
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('veterinarias')->ignore($id),
                    Rule::unique('users')->ignore($id)
                ],
                'registro_oficial_veterinario' => 'sometimes|required|string|max:255',
                'ciudad' => 'sometimes|required|string|max:255',
                'provincia_departamento' => 'sometimes|required|string|max:255',
                'pais' => 'sometimes|required|string|max:255',
                'logo' => 'sometimes|nullable',
                'usuario' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('veterinarias')->ignore($id)
                ],
                'acepta_terminos' => 'sometimes|required|boolean',
                'acepta_tratamiento_datos' => 'sometimes|required|boolean',
            ];
        } else {
            $rules = [
                'veterinaria' => 'required|string|max:255',
                'responsable' => 'required|string|max:255',
                'direccion' => 'required|string',
                'telefono' => 'required|string|max:20',
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('veterinarias'),
                    Rule::unique('users')
                ],
                'registro_oficial_veterinario' => 'required|string|max:255',
                'ciudad' => 'required|string|max:255',
                'provincia_departamento' => 'required|string|max:255',
                'pais' => 'required|string|max:255',
                'logo' => 'nullable',
                'usuario' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('veterinarias')
                ],
                'acepta_terminos' => 'required|boolean',
                'acepta_tratamiento_datos' => 'required|boolean',
            ];
        }

        // Solo validar contraseña en creación o si se proporciona en actualización
        if (!$id || $request->filled('password')) {
            $rules['password'] = 'required|string|min:8';
            $rules['repetir_password'] = 'required|string|min:8';
        }

        // Log de las reglas de validación que se aplicarán
        \Log::info('Reglas de validación aplicadas:', [
            'rules' => $rules,
            'campos_en_request' => array_keys($request->all()),
        ]);

        $validator = Validator::make($request->all(), $rules);
        
        // Log de los valores recibidos para cada campo requerido
        if (!$isUpdate) {
            \Log::info('Valores recibidos para validación (registro nuevo):', [
                'veterinaria' => $request->input('veterinaria'),
                'responsable' => $request->input('responsable'),
                'direccion' => $request->input('direccion'),
                'telefono' => $request->input('telefono'),
                'email' => $request->input('email'),
                'registro_oficial_veterinario' => $request->input('registro_oficial_veterinario'),
                'ciudad' => $request->input('ciudad'),
                'provincia_departamento' => $request->input('provincia_departamento'),
                'pais' => $request->input('pais'),
                'usuario' => $request->input('usuario'),
                'acepta_terminos' => $request->input('acepta_terminos'),
                'acepta_tratamiento_datos' => $request->input('acepta_tratamiento_datos'),
                'password' => $request->has('password') ? '***' : null,
                'repetir_password' => $request->has('repetir_password') ? '***' : null,
            ]);
        }

        return $validator;
    }



    /**
     * Obtener países válidos
     */
    public function getPaises(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Veterinaria::getPaisesValidos(),
            'message' => 'Países obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener mensaje de error de upload
     */
    private function getUploadErrorMessage($errorCode): string
    {
        return match($errorCode) {
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            default => 'Unknown error code: ' . $errorCode
        };
    }
}