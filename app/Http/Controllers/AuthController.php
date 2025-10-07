<?php

namespace App\Http\Controllers;

use App\Models\Veterinaria;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login de veterinaria y generación de token
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'usuario' => 'required|string',
            'password' => 'required|string',
        ]);

        $veterinaria = Veterinaria::where('usuario', $request->usuario)->first();

        if (!$veterinaria || !Hash::check($request->password, $veterinaria->password)) {
            throw ValidationException::withMessages([
                'usuario' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Revocar tokens existentes (opcional, para mayor seguridad)
        $veterinaria->tokens()->delete();

        // Crear nuevo token
        $token = $veterinaria->createToken('api-token', ['veterinaria:access'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'veterinaria' => [
                    'id' => $veterinaria->id,
                    'veterinaria' => $veterinaria->veterinaria,
                    'responsable' => $veterinaria->responsable,
                    'email' => $veterinaria->email,
                    'usuario' => $veterinaria->usuario,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    /**
     * Logout y revocación de token
     */
    public function logout(Request $request): JsonResponse
    {
        // Revocar el token actual
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout exitoso'
        ]);
    }

    /**
     * Obtener información del usuario autenticado
     */
    public function me(Request $request): JsonResponse
    {
        $veterinaria = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $veterinaria->id,
                'veterinaria' => $veterinaria->veterinaria,
                'responsable' => $veterinaria->responsable,
                'direccion' => $veterinaria->direccion,
                'telefono' => $veterinaria->telefono,
                'email' => $veterinaria->email,
                'registro_oficial_veterinario' => $veterinaria->registro_oficial_veterinario,
                'ciudad' => $veterinaria->ciudad,
                'provincia_departamento' => $veterinaria->provincia_departamento,
                'pais' => $veterinaria->pais,
                'logo_url' => $veterinaria->logo_url,
                'usuario' => $veterinaria->usuario,
            ]
        ]);
    }

    /**
     * Renovar token
     */
    public function refresh(Request $request): JsonResponse
    {
        $veterinaria = $request->user();
        
        // Revocar el token actual
        $request->user()->currentAccessToken()->delete();
        
        // Crear nuevo token
        $token = $veterinaria->createToken('api-token', ['veterinaria:access'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token renovado exitosamente',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }
}