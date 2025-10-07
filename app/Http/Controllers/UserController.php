<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Veterinaria;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Filtro de búsqueda opcional
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $users,
            'message' => 'Usuarios obtenidos exitosamente'
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
            'message' => 'Usuario obtenido exitosamente'
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Validar datos
        $validator = $this->validateUser($request, $id);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Error de validación'
            ], 422);
        }

        // Preparar datos para actualización
        $data = [];

        if ($request->filled('name')) {
            $data['name'] = $request->name;
        }

        if ($request->filled('email')) {
            $data['email'] = $request->email;
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        try {
            $user->update($data);

            return response()->json([
                'success' => true,
                'data' => $user->fresh(),
                'message' => 'Usuario actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Usar transacción para asegurar que ambas eliminaciones se realicen correctamente
        DB::beginTransaction();
        
        try {
            // Buscar y eliminar la veterinaria correspondiente con el mismo email
            $veterinaria = Veterinaria::where('email', $user->email)->first();
            if ($veterinaria) {
                $veterinaria->delete();
            }

            // Eliminar el usuario
            $user->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Usuario y veterinaria eliminados exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el usuario y veterinaria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate user data
     */
    private function validateUser(Request $request, $id = null)
    {
        $isUpdate = !is_null($id);

        $rules = [
            'name' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'email' => [
                ($isUpdate ? 'sometimes|' : '') . 'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($id)
            ],
        ];

        // Solo validar contraseña si se proporciona
        if (!$isUpdate || $request->filled('password')) {
            $rules['password'] = 'required|string|min:8';
        }

        return Validator::make($request->all(), $rules);
    }
}