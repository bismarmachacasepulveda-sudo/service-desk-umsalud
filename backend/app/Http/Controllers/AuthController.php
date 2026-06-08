<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Para obtener el ID del usuario autenticado
use Illuminate\Support\Facades\Hash; // Para verificar contraseñas y encriptarlas
use App\Models\User;
use App\Models\SolicitudRegistro; 
use App\Notifications\SystemNotification; // Para notificaciones personalizadas
use Illuminate\Support\Facades\Notification; // Para enviar notificaciones
class AuthController extends Controller
{
    // =============== 1. LOGIN =======================
public function login(Request $request)
{
    // 1. Existe en 'users' (incluyendo papelera)
    $request->validate([ // Validación básica de formato
        'email' => 'required|email',
        'password' => 'required',
    ]);
    $user = User::withTrashed()->where('email', $request->email)->first(); // Buscamos usuarios, incluyendo los inhabilitados (papelera)

    if ($user) {
        if ($user->trashed()) { // Caso A: El usuario está en la papelera
            return response()->json(['message' => 'Esta cuenta ha sido inhabilitada por el administrador.'], 403);
        }
        if (!Hash::check($request->password, $user->password)) { // Caso B: Contraseña incorrecta
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }
        // Caso C: Todo OK - Generamos Token
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'message' => 'Hola ' . $user->name,
            'accessToken' => $token,
            'user' => $user,
        ]);
    }

    // 2. Si no existe en 'users'
    $solicitud = SolicitudRegistro::where('email', $request->email)->first();
    if ($solicitud) { // CASO A: Existe una solicitud, pero aún no es usuario oficial
        return response()->json([
            'message' => 'Su solicitud de acceso está en estado: ' . $solicitud->estado . '. Aún no puede ingresar.'
        ], 403);
    }
    // CASO B: No existe ni en usuarios ni en solicitudes
    return response()->json(['message' => 'El correo electrónico no se encuentra registrado.'], 404);
    }

    //================ 2. REGISTRO DE USUARIO =======================
    public function registrarse(Request $request)
    {
        $request->validate([ // Validación de datos de entrada
            'nombre_completo' => 'required|string|max:255',
            'email'           => 'required|string|email|max:255',
            'password'        => 'required|string|min:6|confirmed',
            'telefono'        => 'nullable|string|max:20', 
            'cargo'           => 'nullable|string|max:100',
            'area_id'         => 'required|exists:areas,id',
        ]);

        // A. VERIFICACIÓN SI EXISTE EN TABLA DE USUARIOS OFICIALES
    $userExistente = User::withTrashed()->where('email', $request->email)->first();
    if ($userExistente) {
        // Caso 1: El usuario está activo (no tiene deleted_at)
        if (!$userExistente->trashed()) {
            return response()->json([
                'message' => 'Este correo ya pertenece a un usuario activo. Intente iniciar sesión.'
            ], 422);
        } 
        // Caso 2: El usuario existe pero está inhabilitado (está en la papelera)
        return response()->json([
            'message' => 'Esta cuenta se encuentra inhabilitada actualmente. Por favor, contacte al administrador para solicitar su restauración.'
        ], 403); 
    }
    $mensajeRespuesta = '';
    $statusHttp = 201;
        // B. VERIFICACION TABLA DE SOLICITUDES DE REGISTRO
    $solicitudExistente = SolicitudRegistro::where('email', $request->email)->first();
    if ($solicitudExistente) {
    // CASO 3: LA SOLICITUD ESTÁ PENDIENTE (Se actualiza automáticamente)
    if ($solicitudExistente->estado === 'pendiente') {
        $solicitudExistente->update([
            'name'     => $request->nombre_completo,
            'password' => Hash::make($request->password),
            'phone'    => $request->telefono,
            'cargo'    => $request->cargo,
            'area_id'  => $request->area_id,
        ]);
            $mensajeRespuesta = 'Se ha detectado una solicitud previa con este correo. Los datos han sido actualizados con la nueva información proporcionada.';
            $statusHttp = 200; // Enviamos 200 porque la operación fue exitosa
    }
            // CASO 4: USUARIO EXISTE PERO LA SOLICITUD FUE RECHAZADA (SOBREESCRITURA)
            elseif ($solicitudExistente->estado === 'rechazado') {
                $solicitudExistente->update([
                    'name'     => $request->nombre_completo,
                    'password' => Hash::make($request->password),
                    'phone'    => $request->telefono,
                    'cargo'    => $request->cargo,
                    'area_id'  => $request->area_id,
                    'estado'   => 'pendiente', // Se vuelve a poner en cola
                    'motivo_rechazo' => null,   // Limpiamos el motivo del rechazo anterior
                    'rejected_by_id' => null    // Limpiamos quién lo rechazó
                ]);
            $mensajeRespuesta = 'Tu solicitud anterior se encontraba rechazada. Hemos actualizado tus datos y enviado una nueva solicitud de aprobación.';
            $statusHttp = 200;
            }
        }

        else {
        // CASO 5: ES UN REGISTRO TOTALMENTE NUEVO (CREAMOS UNA NUEVA SOLICITUD)
        SolicitudRegistro::create([
            'name'     => $request->nombre_completo,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'phone'    => $request->telefono,
            'cargo'    => $request->cargo,
            'area_id'  => $request->area_id,
            'estado'   => 'pendiente'
        ]);
        $mensajeRespuesta = 'Registro exitoso. Tu solicitud ha sido enviada al administrador para su revisión.';
        $statusHttp = 201;
    }

    try {// Enviar notificación a los administradores sobre la nueva solicitud
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new SystemNotification(
            '👤 Nueva Solicitud de Acceso',
            "El usuario {$request->nombre_completo} ha solicitado registrarse en el sistema.",
            '/admin/usuarios/pendientes',
            'bi-person-plus-fill'
        ));
    } catch (\Exception $e) { // Manejo de errores en caso de que falle el envío de notificaciones
        \Log::error("Error al notificar registro: " . $e->getMessage());
    }
    return response()->json(['message' => $mensajeRespuesta], $statusHttp);
    }

    // =============== 3. LOGOUT =======================
    public function logout()
    {
        auth()->user()->tokens()->delete(); // Revocación de tokens activos
        return response()->json(['message' => 'Sesión cerrada']);
    }
}