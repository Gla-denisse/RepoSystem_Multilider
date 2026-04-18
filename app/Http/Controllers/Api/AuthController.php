<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ==========================================
    // 1. INICIAR SESIÓN (LOGIN)
    // ==========================================
    /*public function login(Request $request)
    {
        // 1. Validar los datos recibidos
        $request->validate([
            'correo' => 'required|email',
            'password' => 'required'
        ]);

        // 2. Buscar al usuario por su correo
        $user = User::where('correo', $request->correo)->first();

        // 3. Verificar si el usuario existe y la contraseña es correcta
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas son incorrectas.'
            ], 401);
        }

        // 4. Verificar si la cuenta está activa (Validación extra de seguridad)
        if ($user->estado == 0 || $user->estado === false) {
            return response()->json([
                'message' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'
            ], 403); // 403 Forbidden
        }

        // 5. Generar el Token de acceso con Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // 6. Cargar los permisos del usuario para que Vue sepa qué menús mostrarle
        $user->load(['rolesPermisos.rolPermiso.rol', 'rolesPermisos.rolPermiso.permiso']);

        return response()->json([
            'message' => '¡Bienvenido al sistema!',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 200);
    }*/

    public function login(Request $request)
    {
        $request->validate([
            'correo' => 'required|email',
            'password' => 'required'
        ]);

        // 1. Crear una llave única basada en el correo y la IP del usuario
        $throttleKey = Str::transliterate(Str::lower($request->correo).'|'.$request->ip());

        // 2. Comprobar si ya excedió los intentos (ej. 3 intentos)
        /*if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => "Demasiados intentos fallidos. Por seguridad, la cuenta está bloqueada. Intente de nuevo en $seconds segundos."
            ], 429); // 429 = Too Many Requests
        }*/

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => 'Demasiados intentos fallidos. Por seguridad, la cuenta está bloqueada temporalmente.',
                'seconds_remaining' => $seconds 
            ], 429);
        }

        $user = User::where('correo', $request->correo)->first();

        // 3. Verificamos credenciales incorrectas
        if (!$user || !Hash::check($request->password, $user->password)) {
            
            // Registramos un intento fallido (bloquea por 60 segundos si llega a 3)
            RateLimiter::hit($throttleKey, 60); 

            return response()->json([
                'message' => 'Las credenciales proporcionadas son incorrectas.'
            ], 401);
        }

        // 4. Si el login es exitoso, limpiamos los intentos fallidos
        RateLimiter::clear($throttleKey);

        if ($user->estado == 0 || $user->estado === false) {
            return response()->json([
                'message' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load(['rolesPermisos.rolPermiso.rol', 'rolesPermisos.rolPermiso.permiso']);

        return response()->json([
            'message' => '¡Bienvenido al sistema!',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 200);
    }

    // ==========================================
    // 2. CERRAR SESIÓN (LOGOUT)
    // ==========================================
    public function logout(Request $request)
    {
        // Revoca (elimina) el token que el usuario está usando actualmente
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ], 200);
    }

    // ==========================================
    // 3. OBTENER PERFIL ACTUAL (ME)
    // ==========================================
    public function me(Request $request)
    {
        // Devuelve el usuario que hizo la petición, junto con sus permisos
        $user = $request->user()->load(['rolesPermisos.rolPermiso.rol', 'rolesPermisos.rolPermiso.permiso']);
        return response()->json($user, 200);
    }
}