<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
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
        // Validar que el role sea proporcionado y válido
        $request->validate([
            'role' => 'required|string|in:user,admin',
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        try {
            $user->update([
                'role' => $request->role
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Role del usuario actualizado exitosamente',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el role: ' . $e->getMessage()
            ], 500);
        }
    }
}