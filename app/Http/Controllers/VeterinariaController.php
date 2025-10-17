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
        $validator = $this->validateVeterinaria($request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Error de validación'
            ], 422);
        }

        // Verificar que las contraseñas coincidan
        if ($request->password !== $request->repetir_password) {
            return response()->json([
                'success' => false,
                'message' => 'Las contraseñas no coinciden'
            ], 422);
        }

        // Verificar términos y condiciones
        if (!$request->acepta_terminos || !$request->acepta_tratamiento_datos) {
            return response()->json([
                'success' => false,
                'message' => 'Debe aceptar los términos y condiciones y el tratamiento de datos'
            ], 422);
        }

        $data = $request->except(['repetir_password']);
        $hashedPassword = Hash::make($request->password);
        $data['password'] = $hashedPassword;
        
        // Manejo mejorado de logo: aceptar archivo o URL
        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            try {
                // Validar archivo de imagen
                $request->validate([
                    'logo' => 'required|image|mimes:jpg,jpeg,png,gif,svg,webp|max:5120' // 5MB máximo
                ]);

                $logoFile = $request->file('logo');
                
                // Debug: Verificar propiedades del archivo
                \Log::info('Logo file info:', [
                    'original_name' => $logoFile->getClientOriginalName(),
                    'mime_type' => $logoFile->getMimeType(),
                    'size' => $logoFile->getSize(),
                    'path' => $logoFile->getPathname(),
                    'is_valid' => $logoFile->isValid(),
                    'error' => $logoFile->getError()
                ]);
                
                // Verificar que el archivo es válido
                if (!$logoFile->isValid()) {
                    throw new \Exception('El archivo de logo no es válido. Error: ' . $logoFile->getError());
                }
                
                // Verificar que el archivo tiene contenido
                if ($logoFile->getSize() === 0) {
                    throw new \Exception('El archivo de logo está vacío');
                }
                
                // Obtener extensión de forma segura
                $extension = $logoFile->getClientOriginalExtension();
                if (empty($extension)) {
                    // Fallback: obtener extensión desde el mime type
                    $mimeType = $logoFile->getMimeType();
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
                
                if (move_uploaded_file($logoFile->getPathname(), $fullPath)) {
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
            // Crear la veterinaria
            $veterinaria = Veterinaria::create($data);

            // Crear el usuario correspondiente en la tabla users
            User::create([
                'name' => $request->responsable,
                'email' => $request->email,
                'password' => $hashedPassword,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $veterinaria,
                'message' => 'Veterinaria y usuario registrados exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
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
        $actor = $request->user();
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
        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            try {
                // Validar archivo de imagen
                $request->validate([
                    'logo' => 'required|image|mimes:jpg,jpeg,png,gif,svg,webp|max:5120' // 5MB máximo
                ]);

                $logoFile = $request->file('logo');
                
                // Debug: Verificar propiedades del archivo
                \Log::info('Logo file info (update):', [
                    'original_name' => $logoFile->getClientOriginalName(),
                    'mime_type' => $logoFile->getMimeType(),
                    'size' => $logoFile->getSize(),
                    'path' => $logoFile->getPathname(),
                    'is_valid' => $logoFile->isValid(),
                    'error' => $logoFile->getError()
                ]);
                
                // Verificar que el archivo es válido
                if (!$logoFile->isValid()) {
                    throw new \Exception('El archivo de logo no es válido. Error: ' . $logoFile->getError());
                }
                
                // Verificar que el archivo tiene contenido
                if ($logoFile->getSize() === 0) {
                    throw new \Exception('El archivo de logo está vacío');
                }
                
                // Obtener extensión de forma segura
                $extension = $logoFile->getClientOriginalExtension();
                if (empty($extension)) {
                    // Fallback: obtener extensión desde el mime type
                    $mimeType = $logoFile->getMimeType();
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
                
                if (move_uploaded_file($logoFile->getPathname(), $fullPath)) {
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
        
        $rules = [
            'veterinaria' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'responsable' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'direccion' => ($isUpdate ? 'sometimes|' : '') . 'required|string',
            'telefono' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:20',
            'email' => [
                ($isUpdate ? 'sometimes|' : '') . 'required',
                'email',
                'max:255',
                Rule::unique('veterinarias')->ignore($id),
                Rule::unique('users')->ignore($id)
            ],
            'registro_oficial_veterinario' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'ciudad' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'provincia_departamento' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'pais' => [($isUpdate ? 'sometimes|' : '') . 'required', Rule::in(Veterinaria::getPaisesValidos())],
            // Aceptar archivo o URL para logo: se valida en el controlador según caso
            'logo' => ($isUpdate ? 'sometimes|' : '') . 'nullable',
            'usuario' => [
                ($isUpdate ? 'sometimes|' : '') . 'required',
                'string',
                'max:255',
                Rule::unique('veterinarias')->ignore($id)
            ],
            'acepta_terminos' => ($isUpdate ? 'sometimes|' : '') . 'required|boolean',
            'acepta_tratamiento_datos' => ($isUpdate ? 'sometimes|' : '') . 'required|boolean',
        ];

        // Solo validar contraseña en creación o si se proporciona en actualización
        if (!$id || $request->filled('password')) {
            $rules['password'] = 'required|string|min:8';
            $rules['repetir_password'] = 'required|string|min:8';
        }

        return Validator::make($request->all(), $rules);
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
}