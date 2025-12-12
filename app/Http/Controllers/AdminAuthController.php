<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        // Validar datos como en AuthController
        $request->validate([
            'usuario' => 'required|string',
            'password' => 'required|string',
        ]);

        // Usar 'usuario' como el correo del admin (users.email)
        $user = User::where('email', $request->usuario)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'usuario' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Validar rol admin
        if (!$user->isAdmin()) {
            throw ValidationException::withMessages([
                'usuario' => ['El usuario no tiene permisos de administrador.'],
            ]);
        }

        // Revocar tokens anteriores (opcional)
        $user->tokens()->delete();

        // Crear token con habilidad de admin
        $token = $user->createToken('admin-api-token', ['admin:access'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login admin exitoso',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout admin exitoso',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Actualizar el role de un usuario
     */
    public function updateRole(Request $request, string $id): JsonResponse
    {
        // Obtener el usuario autenticado
        $authenticatedUser = $request->user();
        
        // Log para debugging
        Log::info('Intento de actualización de role', [
            'admin_id' => $authenticatedUser->id,
            'admin_email' => $authenticatedUser->email,
            'admin_role' => $authenticatedUser->role,
            'target_user_id' => $id,
            'new_role' => $request->role ?? 'no proporcionado',
        ]);

        // Validar que el usuario autenticado sea admin
        if (!$authenticatedUser || !$authenticatedUser->isAdmin()) {
            Log::warning('Intento de actualizar role sin permisos de admin', [
                'user_id' => $authenticatedUser->id ?? 'no autenticado',
                'user_role' => $authenticatedUser->role ?? 'no definido',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos de administrador para realizar esta acción',
                'error_type' => 'insufficient_permissions',
                'user_role' => $authenticatedUser->role ?? 'no definido',
            ], 403);
        }

        // Validar que el role sea proporcionado y válido
        $request->validate([
            'role' => 'required|string|in:user,admin',
        ]);

        // Buscar el usuario a actualizar
        $user = User::find($id);

        if (!$user) {
            Log::warning('Intento de actualizar role de usuario inexistente', [
                'target_user_id' => $id,
                'admin_id' => $authenticatedUser->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
                'error_type' => 'user_not_found',
            ], 404);
        }

        // Verificar que el role actual en la base de datos sea válido
        $currentRole = $user->role;
        if (!in_array($currentRole, ['user', 'admin'])) {
            Log::error('Usuario con role inválido en la base de datos', [
                'user_id' => $user->id,
                'current_role' => $currentRole,
            ]);
        }

        // No permitir que un admin se quite sus propios permisos de admin
        if ($user->id === $authenticatedUser->id && $request->role === 'user') {
            Log::warning('Intento de un admin de quitarse sus propios permisos', [
                'admin_id' => $authenticatedUser->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No puedes quitarte tus propios permisos de administrador',
                'error_type' => 'self_demotion_not_allowed',
            ], 403);
        }

        try {
            $oldRole = $user->role;
            $user->update([
                'role' => $request->role
            ]);

            // Verificar que la actualización se realizó correctamente
            $user->refresh();
            if ($user->role !== $request->role) {
                Log::error('Error: El role no se actualizó correctamente en la base de datos', [
                    'user_id' => $user->id,
                    'expected_role' => $request->role,
                    'actual_role' => $user->role,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error: El role no se actualizó correctamente. Verifica los logs del servidor.',
                    'error_type' => 'update_failed',
                ], 500);
            }

            Log::info('Role actualizado exitosamente', [
                'user_id' => $user->id,
                'old_role' => $oldRole,
                'new_role' => $user->role,
                'admin_id' => $authenticatedUser->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Role del usuario actualizado exitosamente',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'previous_role' => $oldRole,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar el role', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el role: ' . $e->getMessage(),
                'error_type' => 'database_error',
            ], 500);
        }
    }
}