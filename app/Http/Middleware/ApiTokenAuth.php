<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar si el header Authorization está presente
        if (!$request->hasHeader('Authorization')) {
            return $this->unauthorizedResponse('Token de autorización requerido. Incluya el header: Authorization: Bearer {token}');
        }

        $authHeader = $request->header('Authorization');
        
        // Verificar formato del header
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorizedResponse('Formato de token inválido. Use: Authorization: Bearer {token}');
        }

        // Extraer el token
        $token = substr($authHeader, 7);
        
        if (empty($token)) {
            return $this->unauthorizedResponse('Token vacío. Proporcione un token válido.');
        }

        // Intentar autenticar usando Sanctum
        if (!auth('api')->check()) {
            return $this->unauthorizedResponse('Token inválido o expirado. Inicie sesión nuevamente.');
        }

        return $next($request);
    }

    /**
     * Respuesta de error de autorización
     */
    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'No autorizado',
            'message' => $message,
            'code' => 401
        ], 401);
    }
}