<?php

namespace App\Http\Controllers;

use App\Models\Ambiente;
use Illuminate\Http\Request; // Para manejar las solicitudes HTTP
use Illuminate\Support\Facades\Auth; // Para obtener el ID del usuario autenticado

class AmbienteController extends Controller
{
    /**
     * 1. LISTAR AMBIENTES
     */
    public function index(Request $request)
    {
        // Ordenamos por Nombre y luego por Ubicación para que sea ordenado
        return response()->json(Ambiente::orderBy('nombre', 'asc')->get());
    }

    /**
     * 2. CREAR (Solo Admin)
     */
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') { // Solo admins pueden crear ambientes
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        $request->validate([ // Validación de datos de entrada
            'nombre'      => 'required|string|max:100',
            'tipo'        => 'required|string',
            'capacidad'   => 'required|integer|min:1',
            'ubicacion'   => 'nullable|string|max:255',
            'estado'      => 'required|in:activo,mantenimiento',
            'descripcion' => 'nullable|string'
        ]);

        $ambiente = Ambiente::create([ // Creación del nuevo ambiente con los datos validados
            'nombre'      => $request->nombre,
            'tipo'        => $request->tipo,
            'capacidad'   => $request->capacidad,
            'ubicacion'   => $request->ubicacion,
            'estado'      => $request->estado,
            'descripcion' => $request->descripcion,
            'created_by'  => Auth::id() //Auditoría
        ]);
        return response()->json($ambiente, 201);
    }

    /**
     * 3. EDITAR (Solo Admin)
     */
    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        $ambiente = Ambiente::findOrFail($id); // Buscar el ambiente por ID
        $request->validate([ // validacion
            'nombre'      => 'required|string|max:100',
            'tipo'        => 'required|string',
            'capacidad'   => 'required|integer|min:1',
            'ubicacion'   => 'nullable|string|max:255',
            'estado'      => 'required|in:activo,mantenimiento',
            'descripcion' => 'nullable|string'
        ]);
        $ambiente->update([ // Actualizar el ambiente con los nuevos datos
            'nombre'      => $request->nombre,
            'tipo'        => $request->tipo,
            'capacidad'   => $request->capacidad,
            'ubicacion'   => $request->ubicacion,
            'estado'      => $request->estado,
            'descripcion' => $request->descripcion,
            'updated_by'  => Auth::id() // Auditoría
        ]);

        return response()->json($ambiente);
    }
    /**========================================
     * 4. ELIMINAR LÓGICO (Mover a Papelera)
    =========================================== */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        $ambiente = Ambiente::findOrFail($id); // Buscar el ambiente por ID
        $ambiente->updated_by = Auth::id(); // Auditoría: Quién lo está eliminando
        $ambiente->save(); // Guardar el cambio de auditoría antes de eliminar
        // Futuro: Validar que no tenga reservas PENDIENTES o ACTIVAS hoy
        // if ($ambiente->reservas()->where('fecha', '>=', now())->exists()) { ... }
        $ambiente->delete(); // SoftDelete
        return response()->json(['message' => 'Ambiente movido a la papelera.']);
    }

    // ==========================================
    // 5. GESTIÓN DE PAPELERA
    // ==========================================

    public function trashed(Request $request)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);
        $ambientes = Ambiente::onlyTrashed() // Solo los eliminados
            ->with('editor:id,name') // Quién lo borró
            ->orderBy('deleted_at', 'desc') // Más recientes primero
            ->get(); // Obtener los ambientes eliminados con su editor
        return response()->json($ambientes);
    }
    // ==========================================
    // 6. RESTAURAR
    // ========================================== 
    public function restore(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);

        $ambiente = Ambiente::onlyTrashed()->findOrFail($id);
        $ambiente->updated_by = Auth::id(); // Quién restauró
        $ambiente->restore();
        return response()->json(['message' => 'Ambiente restaurado.']);
    }
    // ==========================================
    // 7. ELIMINACIÓN PERMANENTE
    // ==========================================
    public function forceDelete(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);

        $ambiente = Ambiente::onlyTrashed()->findOrFail($id);

        // 🛡️ VALIDACIÓN DE INTEGRIDAD
        // Cuando creemos la tabla Reservas, descomentar esto:
        /*
        if ($ambiente->reservas()->exists()) { // Si tiene reservas asociadas, no se puede eliminar permanentemente
             return response()->json(['message' => 'No se puede eliminar permanentemente: Tiene historial de reservas.'], 409);
        }
        */
        $ambiente->forceDelete(); // Eliminación física definitiva
        return response()->json(['message' => 'Ambiente eliminado permanentemente.']);
    }
}