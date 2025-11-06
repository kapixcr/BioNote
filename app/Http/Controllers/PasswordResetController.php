<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\Veterinaria;

class PasswordResetController extends Controller
{
    /**
     * Request a password reset link.
     */
    public function forgot(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $key = $this->throttleKey($request->email, $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => true,
                'message' => 'Si el correo existe, se ha enviado un enlace para restablecer la contraseña. Intenta nuevamente más tarde.'
            ], 200);
        }

        RateLimiter::hit($key, 60 * 60); // ventana de 1 hora

        try {
            $status = Password::sendResetLink($request->only('email'));

            Log::info('Password reset requested', [
                'email_hash' => hash('sha256', strtolower($request->email)),
                'status' => $status,
            ]);

        } catch (\Throwable $e) {
            Log::error('Password reset request failed', [
                'email_hash' => hash('sha256', strtolower($request->email)),
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Si el correo existe, se ha enviado un enlace para restablecer la contraseña.'
        ]);
    }

    /**
     * Reset the user password using token.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $key = $this->throttleKey('reset_'.$request->email, $request->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Demasiados intentos. Inténtalo más tarde.'
            ], 429);
        }
        RateLimiter::hit($key, 60 * 10); // ventana de 10 minutos

        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    // Asegurar hash correcto del password para el modelo User
                    $hashed = Hash::make($password);
                    $user->password = $hashed;
                    $user->save();

                    // Sincronizar contraseña en Veterinaria si comparten el mismo email
                    $veterinaria = Veterinaria::where('email', $user->email)->first();
                    if ($veterinaria) {
                        $veterinaria->password = $hashed;
                        $veterinaria->save();
                        Log::info('Password sincronizado en Veterinaria tras reset por email', [
                            'user_id' => $user->id,
                            'veterinaria_id' => $veterinaria->id,
                        ]);
                    }
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                Log::info('Password reset success', [
                    'email_hash' => hash('sha256', strtolower($request->email)),
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Contraseña restablecida exitosamente.'
                ]);
            }

            Log::warning('Password reset failed', [
                'email_hash' => hash('sha256', strtolower($request->email)),
                'status' => $status,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'El token de recuperación es inválido o ha expirado.'
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Password reset error', [
                'email_hash' => hash('sha256', strtolower($request->email)),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al restablecer la contraseña.'
            ], 500);
        }
    }

    private function throttleKey(string $identifier, string $ip): string
    {
        return Str::lower($identifier).'|'.$ip;
    }
}