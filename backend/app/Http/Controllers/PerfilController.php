<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Para obtener el usuario autenticado
use Illuminate\Support\Facades\Hash; // Para verificar contraseñas y encriptarlas
use Illuminate\Validation\Rules\Password; // Para reglas de validación de contraseñas

class PerfilController extends Controller
{
    /**=============================================
     * 1. Devuelve los datos del usuario autenticado.
     ===============================================*/
    public function show()
    {
        return Auth::user()->load('area'); // Devolvemos el usuario autenticado con su área relacionada
    }

    /**=============================================
     * 2. Actualiza la información del perfil.
     ===============================================*/
    public function update(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'cargo' => 'nullable|string|max:100',
        ]);
        // PROTECCIÓN: Solo permitimos editar campos no críticos.
        // El rol y el área_id NO se incluyen aquí para evitar "auto-promociones".
        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'cargo' => $request->cargo,
            'updated_by' => $user->id, // Auditoría: Se editó a sí mismo
        ]);

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user'    => $user
        ]);
    }

    /** ==================================================
    * 3. Cambia la contraseña tras verificar la anterior.
    *=====================================================*/
    public function updatePassword(Request $request)
    {
        $request->validate([ // Validación de datos de entrada
            'current_password' => 'required', // La contraseña actual es obligatoria para verificar identidad
            'password' => ['required', 'confirmed', Password::min(6)], // La nueva contraseña debe ser confirmada y tener al menos 6 caracteres
        ]);
        $user = Auth::user(); // Usuario autenticado que desea cambiar su contraseña

        // VALIDACIÓN DE SEGURIDAD: Comprobar que la contraseña actual coincide
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual no es correcta.'
            ], 422);
        }
        // Actualizamos con la nueva contraseña cifrada
        $user->update([
            'password'   => Hash::make($request->password),
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada con éxito.'
        ]);
    }
}