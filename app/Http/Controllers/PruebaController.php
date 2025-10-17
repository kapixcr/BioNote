<?php

namespace App\Http\Controllers;

use App\Models\Prueba;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class PruebaController extends Controller
{
    /**
     * Listar pruebas con filtros básicos y paginación.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int)($request->query('per_page', 15));
        $query = Prueba::query();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('especie')) {
            $query->where('especie', 'like', '%' . $request->query('especie') . '%');
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->query('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->query('fecha_hasta'));
        }

        $pruebas = $query->orderByDesc('fecha')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $pruebas
        ]);
    }

    /**
     * Mostrar una prueba específica.
     */
    public function show(string $id): JsonResponse
    {
        $prueba = Prueba::find($id);
        if (!$prueba) {
            return response()->json([
                'success' => false,
                'message' => 'Prueba no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $prueba
        ]);
    }

    /**
     * Crear una nueva prueba.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
            'fecha' => ['required', 'date'],
            'especie' => ['required', 'string', 'max:100'],
            'nombre_mascota' => ['required', 'string', 'max:100'],
            'sexo' => ['required', 'string', 'max:50'],
            'raza' => ['required', 'string', 'max:100'],
            'edad' => ['required', 'integer', 'min:0'],
            'nombre_prueba' => ['required', 'string', 'max:150'],
            'result_prueba' => ['nullable', 'array'],
            'titulacion' => ['required', 'array'],
            // 'fotos' se maneja como archivos en multipart/form-data; validación específica abajo
            'fotos' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Validar y almacenar archivos de fotos si se enviaron
        $fotoUrls = [];
        if ($request->hasFile('fotos')) {
            try {
                // Soportar una sola foto o múltiples (fotos[])
                $files = $request->file('fotos');
                if (!is_array($files)) {
                    $files = [$files];
                }
                
                // Validar cada imagen
                $request->validate([
                    'fotos.*' => 'image|mimes:jpg,jpeg,png,gif,svg,webp|max:5120'
                ]);
                
                foreach ($files as $index => $file) {
                    if (!$file || !$file->isValid()) {
                        \Log::warning("Archivo de foto inválido en índice {$index}");
                        continue;
                    }
                    
                    // Debug: Verificar propiedades del archivo
                    \Log::info("Foto file info (index {$index}):", [
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'path' => $file->getPathname(),
                        'is_valid' => $file->isValid(),
                        'error' => $file->getError()
                    ]);
                    
                    // Verificar que el archivo tiene contenido
                    if ($file->getSize() === 0) {
                        \Log::warning("Archivo de foto vacío en índice {$index}");
                        continue;
                    }
                    
                    // Obtener extensión de forma segura
                    $extension = $file->getClientOriginalExtension();
                    if (empty($extension)) {
                        // Fallback: obtener extensión desde el mime type
                        $mimeType = $file->getMimeType();
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
                    $filename = time() . '_' . uniqid() . '_' . $index . '.' . $extension;
                    
                    // Crear directorio si no existe
                    $destinationPath = storage_path('app/public/pruebas');
                    if (!file_exists($destinationPath)) {
                        mkdir($destinationPath, 0755, true);
                    }
                    
                    // Mover el archivo manualmente
                    $fullPath = $destinationPath . DIRECTORY_SEPARATOR . $filename;
                    
                    if (move_uploaded_file($file->getPathname(), $fullPath)) {
                        $fotoUrls[] = Storage::disk('public')->url('pruebas/' . $filename);
                        \Log::info("Foto guardada exitosamente: {$fullPath}");
                    } else {
                        \Log::error("Error al mover archivo de foto en índice {$index}");
                        throw new \Exception("Error al guardar la foto en índice {$index}");
                    }
                    
                    // Verificar que el archivo se guardó correctamente
                    if (!file_exists($fullPath)) {
                        throw new \Exception("Error: la foto no se encontró después de guardarla en: {$fullPath}");
                    }
                }
                
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Error de validación de las fotos'
                ], 422);
            } catch (\Exception $e) {
                \Log::error('Error uploading fotos: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'errors' => ['fotos' => ['Error al procesar las fotos: ' . $e->getMessage()]],
                    'message' => 'Error al subir las fotos'
                ], 422);
            }
        }

        if (!empty($fotoUrls)) {
            $data['fotos'] = $fotoUrls;
        }

        $prueba = Prueba::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Prueba creada exitosamente',
            'data' => $prueba
        ], 201);
    }

    /**
     * Actualizar una prueba existente (parcial).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $prueba = Prueba::find($id);
        if (!$prueba) {
            return response()->json([
                'success' => false,
                'message' => 'Prueba no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => ['sometimes', 'exists:users,id'],
            'fecha' => ['sometimes', 'date'],
            'especie' => ['sometimes', 'string', 'max:100'],
            'nombre_mascota' => ['sometimes', 'string', 'max:100'],
            'sexo' => ['sometimes', 'string', 'max:50'],
            'raza' => ['sometimes', 'string', 'max:100'],
            'edad' => ['sometimes', 'integer', 'min:0'],
            'nombre_prueba' => ['sometimes', 'string', 'max:150'],
            'result_prueba' => ['sometimes', 'array'],
            'titulacion' => ['sometimes', 'array'],
            // 'fotos' como archivos en multipart/form-data; validación específica abajo
            'fotos' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Manejo de nuevas fotos: agregar a las existentes
        if ($request->hasFile('fotos')) {
            $files = $request->file('fotos');
            if (!is_array($files)) {
                $files = [$files];
            }
            $request->validate([
                'fotos.*' => 'image|mimes:jpg,jpeg,png,gif,svg,webp|max:5120'
            ]);
            $existing = is_array($prueba->fotos) ? $prueba->fotos : [];
            foreach ($files as $file) {
                if (!$file) continue;
                $path = $file->store('pruebas', 'public');
                $existing[] = Storage::disk('public')->url($path);
            }
            $data['fotos'] = $existing;
        }

        $prueba->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Prueba actualizada exitosamente',
            'data' => $prueba
        ]);
    }
 /**
     * Listar pruebas del usuario autenticado con filtros y paginación.
     */
    public function myPruebas(Request $request): JsonResponse
    {
        // Obtener el usuario autenticado
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado'
            ], 401);
        }
        // Bloquear acceso si es admin (solo para usuarios del modelo User)
        if ($user instanceof \App\Models\User && $user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado'
            ], 403);
        }
        
        // Para veterinarias, usar su propio ID como user_id en las pruebas
        $userId = $user instanceof \App\Models\User ? $user->id : $user->id;

        $perPage = (int)($request->query('per_page', 15));
        
        // Crear query base filtrado por el usuario autenticado
        $query = Prueba::where('user_id', $userId);

        // Aplicar filtros adicionales si se proporcionan
        if ($request->filled('especie')) {
            $query->where('especie', 'like', '%' . $request->query('especie') . '%');
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->query('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->query('fecha_hasta'));
        }

        if ($request->filled('nombre_mascota')) {
            $query->where('nombre_mascota', 'like', '%' . $request->query('nombre_mascota') . '%');
        }

        if ($request->filled('nombre_prueba')) {
            $query->where('nombre_prueba', 'like', '%' . $request->query('nombre_prueba') . '%');
        }

        // Ordenar por fecha descendente y paginar
        $pruebas = $query->orderByDesc('fecha')->paginate($perPage);

        // Transformar los datos para convertir arrays a strings para compatibilidad con Flutter
        $pruebas->getCollection()->transform(function ($prueba) {
            // Convertir result_prueba de array a string separado por comas
            if (is_array($prueba->result_prueba)) {
                $prueba->result_prueba = implode(', ', $prueba->result_prueba);
            }
            
            // Convertir titulacion de array a string si existe
            if (is_array($prueba->titulacion)) {
                $prueba->titulacion = implode(', ', $prueba->titulacion);
            }
            
            return $prueba;
        });

        return response()->json([
            'success' => true,
            'data' => $pruebas,
            'user_id' => $userId,
            'user_type' => $user instanceof \App\Models\User ? 'user' : 'veterinaria'
        ]);
    }
    /**
     * Eliminar una prueba.
     */
    public function destroy(string $id): JsonResponse
    {
        $prueba = Prueba::find($id);
        if (!$prueba) {
            return response()->json([
                'success' => false,
                'message' => 'Prueba no encontrada'
            ], 404);
        }

        try {
            $prueba->delete();
            return response()->json([
                'success' => true,
                'message' => 'Prueba eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la prueba: ' . $e->getMessage()
            ], 500);
        }
    }
}