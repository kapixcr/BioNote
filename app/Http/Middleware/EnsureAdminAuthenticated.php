<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log para debugging
        Log::info('EnsureAdminAuthenticated middleware ejecutado', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'has_auth_header' => $request->hasHeader('Authorization'),
            'auth_header_present' => !empty($request->header('Authorization')),
        ]);

        // Verificar si hay header de autorización
        if (!$request->hasHeader('Authorization')) {
            Log::warning('Request sin header Authorization', [
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token de autorización requerido. Incluya el header: Authorization: Bearer {token}',
                'error_type' => 'missing_authorization_header',
                'code' => 401
            ], 401);
        }

        $authHeader = $request->header('Authorization');
        
        // Verificar formato del header
        if (!str_starts_with($authHeader, 'Bearer ')) {
            Log::warning('Formato de token inválido', [
                'header_received' => substr($authHeader, 0, 20) . '...',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Formato de token inválido. Use: Authorization: Bearer {token}',
                'error_type' => 'invalid_token_format',
                'code' => 401
            ], 401);
        }

        // Extraer el token
        $token = substr($authHeader, 7);
        
        if (empty($token)) {
            Log::warning('Token vacío', [
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token vacío. Proporcione un token válido.',
                'error_type' => 'empty_token',
                'code' => 401
            ], 401);
        }

        // Intentar autenticar usando el guard admin
        try {
            // Primero intentar encontrar el token en la base de datos
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            
            if (!$tokenModel) {
                Log::warning('Token no encontrado en la base de datos', [
                    'token_preview' => substr($token, 0, 20) . '...',
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Token no encontrado o inválido. Asegúrate de haber iniciado sesión como administrador.',
                    'error_type' => 'token_not_found',
                    'hint' => 'El token debe ser generado mediante: POST /api/auth/admin/login',
                    'code' => 401
                ], 401);
            }

            Log::info('Token encontrado en la base de datos', [
                'tokenable_type' => $tokenModel->tokenable_type,
                'tokenable_id' => $tokenModel->tokenable_id,
                'abilities' => $tokenModel->abilities,
            ]);

            // Verificar que el token pertenezca a un User (no Veterinaria)
            if ($tokenModel->tokenable_type !== 'App\\Models\\User') {
                Log::warning('Token pertenece a un modelo diferente a User', [
                    'tokenable_type' => $tokenModel->tokenable_type,
                    'expected' => 'App\\Models\\User',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Este token pertenece a una veterinaria. Necesitas un token de administrador.',
                    'error_type' => 'wrong_token_type',
                    'hint' => 'Debes iniciar sesión como administrador usando: POST /api/auth/admin/login',
                    'code' => 401
                ], 401);
            }

            // Obtener el usuario del token
            $user = $tokenModel->tokenable;
            
            if (!$user) {
                Log::error('Token válido pero usuario no encontrado', [
                    'tokenable_id' => $tokenModel->tokenable_id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Usuario asociado al token no encontrado',
                    'error_type' => 'user_not_found',
                    'code' => 401
                ], 401);
            }

            // Establecer el usuario en el guard admin
            auth('admin')->setUser($user);
            
            Log::info('Usuario autenticado desde token', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $user->role,
            ]);

            // Verificar que el usuario tenga role admin
            if (!$user->isAdmin()) {
                Log::warning('Usuario autenticado pero sin role admin', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos de administrador. Tu role actual es: ' . ($user->role ?? 'no definido'),
                    'error_type' => 'insufficient_permissions',
                    'user_role' => $user->role ?? 'no definido',
                    'code' => 403
                ], 403);
            }

            Log::info('Usuario admin autenticado correctamente', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'url' => $request->fullUrl(),
            ]);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Error en EnsureAdminAuthenticated middleware', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error de autenticación: ' . $e->getMessage(),
                'error_type' => 'authentication_error',
                'code' => 401
            ], 401);
        }
    }
}

