<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SolicitudRegistro; // Modelo para las solicitudes de registro
use Illuminate\Support\Facades\Hash; // Para encriptar contraseñas
use Illuminate\Support\Facades\Auth; // Para obtener el usuario autenticado
use Illuminate\Support\Facades\DB; //para transacciones
use Illuminate\Validation\Rule; // Para reglas de validación condicionales
use App\Notifications\SystemNotification; // Para enviar notificaciones al usuario

class UserController extends Controller
{
    /**==============================
     *  1. LISTAR USUARIOS OFICIALES 
     * ==============================*/
    public function index()
    {
        return User::with(['area', 'creator:id,name', 'approver:id,name', 'editor:id,name'])
                ->orderBy('name', 'asc')
                ->get();
    }

    /**==============================
     *  2. CREAR UN NUEVO USUARIO (POST)
     * ==============================*/
    public function store(Request $request)
    {
        $request->validate([ // Validación básica de campos comunes
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role'     => ['required', Rule::in(['admin', 'tecnico', 'usuario'])],
            'phone'    => 'nullable|string|max:20',
            'cargo'    => 'nullable|string|max:100',

            // REGLAS CONDICIONALES:
            'ci' => [
                Rule::requiredIf(in_array($request->role, ['admin', 'tecnico'])), // Obligatorio para tecnicos y admins
                'nullable', 'string', 'max:20'
            ],
            'area_id' => [
                Rule::requiredIf($request->role === 'usuario'), // Usuarios deben tener un área asignada
                'nullable', 'exists:areas,id',
            ],
            'expertise' => [
                Rule::requiredIf($request->role === 'tecnico'), // Técnicos deben tener especialidad
                'nullable', 'string',
            ],
        ]);

        $user = User::create([ // Creamos el usuario con los datos validados
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password), 
            'role'      => $request->role,
            'area_id'   => $request->area_id,
            'expertise' => $request->expertise,
            'phone'     => $request->phone,
            'ci'        => $request->ci,
            'cargo'     => $request->cargo,
            'estado'    => 'activo',
            'created_by'     => Auth::id(), // Auditoría de creación
            'approved_by_id' => Auth::id(), // Aprobado automáticamente al ser manual
        ]);

        return response()->json(['message' => 'Usuario administrativo creado con éxito', 'user' => $user], 201);
    }

    /**==============================
     *  3. ACTUALIZAR USUARIO (PUT)
     * ==============================*/
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([ // Validación de campos comunes
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => ['required', Rule::in(['admin', 'tecnico', 'usuario'])],
            'phone' => 'nullable|string|max:20', 
            'cargo' => 'nullable|string|max:100',
            'ci' => 'nullable|string|max:20',
            'area_id' => [Rule::requiredIf($request->role === 'usuario'), 'nullable', 'exists:areas,id'],
            'expertise' => [Rule::requiredIf($request->role === 'tecnico'), 'nullable', 'string'],
            'password' => 'nullable|min:6',
        ]);
        // actualizamos la base de datos
        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->phone = $request->phone;
        $user->ci = $request->ci;    
        $user->cargo = $request->cargo; 
        $user->ci = ($request->role === 'tecnico') ? $request->ci : null;
        $user->area_id = ($request->role === 'usuario') ? $request->area_id : null;
        $user->expertise = ($request->role === 'tecnico') ? $request->expertise : null;
        
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->updated_by = Auth::id(); // Auditoría: Quién realizó la última modificación
        $user->save();

        return response()->json(['message' => 'Usuario actualizado', 'user' => $user]);
    }

    /**==============================
     *  4. ELIMINAR USUARIO (DELETE)
     * ==============================*/
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->estado = 'inhabilitado';
        $user->updated_by = Auth::id(); // Registro de quién ejecutó la eliminación
        $user->saveQuietly(); // Guardamos el autor antes del soft delete sin disparar eventos
        $user->delete(); //  soft delete
        
        return response()->json(['message' => 'Usuario Inhabilitado correctamente']);
    }

    /**==============================
     *  5. APROBAR SOLICITUD
     * ==============================*/
    public function aprobarUsuario($id)
    {
        // Buscamos en la nueva tabla de solicitudes
        $solicitud = SolicitudRegistro::findOrFail($id);
        // Creamos el usuario en la tabla oficial con los datos de la solicitud
        $user = User::create([
            'name'      => $solicitud->name,
            'email'     => $solicitud->email,
            'password'  => $solicitud->password, // Ya viene encriptada de la solicitud
            'phone'     => $solicitud->phone,
            'ci'        => $solicitud->ci,
            'cargo'     => $solicitud->cargo,
            'area_id'   => $solicitud->area_id,
            'role'      => 'usuario',
            'estado'    => 'activo',
            'approved_by_id' => Auth::id(), // Quién dio el visto bueno
        ]);
        // Actualizamos el estado de la solicitud para que no aparezca en pendientes
        $solicitud->update([
            'estado' => 'aprobado',
            'approved_by_id' => Auth::id()
        ]);
        // Enviamos una notificación al usuario sobre la aprobación de su cuenta
        try {
            $user->notify(new SystemNotification(
                '🎉 ¡Cuenta Aprobada!', 
                'Tu acceso a la Red UMSALUD ha sido habilitado. Ya puedes ingresar.',
                '/login', 
                'bi-check-circle-fill'
            ));
        } catch (\Exception $e) {
            \Log::error('Fallo de notificación: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Solicitud aprobada y usuario creado.', 'user' => $user]);
    }

    /**==============================
     *  6. RECHAZAR USUARIO
     * ==============================*/
    public function rechazarUsuario(Request $request, $id)
    {
        $solicitud = SolicitudRegistro::findOrFail($id); // Buscamos la solicitud por ID

        $solicitud->update([ // Actualizamos el estado a rechazado y registramos el motivo
            'estado' => 'rechazado',
            'rejected_by_id' => Auth::id(),
            'motivo_rechazo' => $request->motivo ?? 'No cumple con los requisitos institucionales.'
        ]);

        return response()->json(['message' => 'Solicitud rechazada correctamente.']);
    }

    /**==============================
     *  7. OBTENER USUARIOS PENDIENTES
     * ==============================*/
    public function getPendientes()
    {
        // Aconsultamos la tabla de SOLICITUDES
        return SolicitudRegistro::with('area')
                ->where('estado', 'pendiente')
                ->orderBy('created_at', 'desc')
                ->get();
    }

    /**==============================
     *  8. MÉTODO PARA CAMBIAR LA PREFERENCIA DE WHATSAPP
     * ==============================*/
    public function toggleWhatsapp(Request $request)
    {
        $user = $request->user(); // Obtenemos el usuario autenticado
        // Validamos que envíen un booleano
        $request->validate([
            'active' => 'required|boolean'
        ]);
    // Actualizamos
    $user->whatsapp_active = $request->active;
    $user->save();
     // Enviamos una respuesta indicando el nuevo estado
    $status = $user->whatsapp_active ? 'activadas' : 'desactivadas';
    return response()->json([
        'message' => "Notificaciones de WhatsApp $status correctamente.",
        'user' => $user
    ]);
    }

    /**==============================
     *  9. OBTIENE EL HISTORIAL COMPLETO DE SOLICITUDES PROCESADAS
     * ==============================*/
    public function getHistorialSolicitudes()
    {
        // Traemos las solicitudes que NO están pendientes (Aprobadas o Rechazadas)
        return SolicitudRegistro::with(['area', 'approver:id,name', 'rejecter:id,name'])
                ->where('estado', '!=', 'pendiente')
                ->orderBy('updated_at', 'desc')
                ->get();
    }

    /**===============================================
     * 10. Actualiza los datos del perfil del usuario autenticado.
     * ================================================*/
    public function updateProfile(Request $request)
    {
        $user = Auth::user(); // Obtenemos al usuario desde el Token
    $request->validate([ // Validamos los datos entrantes
        'name'  => 'required|string|max:255',
        'phone' => 'nullable|string|max:20',
        'cargo' => 'nullable|string|max:100',
        'email' => 'required|email|unique:users,email,' . $user->id,
    ]);

    $user->update([ // Actualizamos solo los campos permitidos para el perfil
        'name'  => $request->name,
        'phone' => $request->phone,
        'cargo' => $request->cargo,
        'email' => $request->email,
        'updated_by' => $user->id, // Auditoría: Se editó a sí mismo
    ]);

    return response()->json(['message' => 'Perfil actualizado correctamente', 'user' => $user]);
}

/** ===============================================
 * 11. Cambio de contraseña con verificación de seguridad.
 ** ==============================================*/
public function changePassword(Request $request)
{
    $request->validate([
        'old_password' => 'required',
        'password'     => 'required|min:6|confirmed', // password_confirmation
    ]);
    $user = Auth::user(); // Obtenemos al usuario autenticado
    // Verificamos que la contraseña antigua sea correcta
    if (!Hash::check($request->old_password, $user->password)) {
        return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
    }
    $user->update([
        'password'   => Hash::make($request->password),
        'updated_by' => $user->id,
    ]);

    return response()->json(['message' => 'Contraseña actualizada con éxito.']);
}

/** ===============================================
 *  --- MÉTODOS DE PAPELERA ---
 * ===============================================*/

public function trashed(Request $request) {
    if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);

    // Usuarios en la papelera con su área para el reporte
    $users = User::onlyTrashed()->with('editor:id,name')->get();
    return response()->json($users);
}

public function restore($id) 
{
    // Buscamos al usuario en la papelera
    $user = User::onlyTrashed()->findOrFail($id);
    $user->estado = 'activo'; 
    $user->updated_by = Auth::id(); // Registramos quién lo trajo de vuelta
    $user->restore(); // Esto quita el deleted_at y guarda los cambios anteriores
    
    return response()->json([
        'message' => 'Usuario restaurado y cuenta reactivada correctamente.',
        'user' => $user
    ]);
}

public function forceDelete($id) {
    $user = User::onlyTrashed()->findOrFail($id);

    // SEGURIDAD: No borrar si tiene historia
    if ($user->tickets()->exists() || $user->reservas()->exists()) { // Verificamos si tiene tickets o reservas
        return response()->json([
            'message' => 'No se puede eliminar permanentemente. El usuario tiene registros históricos (tickets/reservas).'
        ], 409);
    }

    $user->forceDelete();
    return response()->json(['message' => 'Usuario borrado físicamente.']);
}
}