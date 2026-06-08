<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AreaController extends Controller
{
    /**
     * 1. LISTAR
     */
public function index(Request $request)
{
    $user = $request->user('sanctum'); // Intentamos obtener el usuario autenticado, pero no es obligatorio para listar áreas (PREREGISTRO)
    $query = Area::orderBy('name', 'asc'); // Orden alfabético por nombre
    if (!$user || $user->role !== 'admin') { // Si no hay usuario autenticado o no es admin, solo mostramos áreas activas
        $query->where('active', true);
    }
    return response()->json($query->get());
}

    /**
     * 2. CREAR (Solo Admin)
     */
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') { // Solo admins
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        $request->validate([ // Validación de datos de entrada
            'name'        => 'required|string|max:50|unique:areas,name',
            'description' => 'nullable|string|max:255',
        ]);
        $area = Area::create([ // Creación del nuevo área con los datos validados
            'name'        => $request->name,
            'description' => $request->description,
            'active'      => true,
            'created_by'  => Auth::id() // Auditoría
        ]);

        return response()->json($area, 201);
    }

    /**
     * 3. ACTUALIZAR (Solo Admin)
     */
    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        $area = Area::findOrFail($id); // Buscar el área por ID
        $request->validate([ // Validación de datos de entrada
            'name'        => 'required|string|max:50|unique:areas,name,' . $id,
            'description' => 'nullable|string|max:255',
            'active'      => 'boolean'
        ]);
        $area->update([ // Actualización del área con los datos validados
            'name'        => $request->name,
            'description' => $request->description,
            'active'      => $request->active,
            'updated_by'  => Auth::id()
        ]);
        
        return response()->json($area);
    }

    /**
     * 4. ELIMINACION LÓGICA (Mover a Papelera)
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        $area = Area::findOrFail($id); // Buscar el área por ID
        $area->updated_by = Auth::id(); // Registrar quién lo está eliminando
        $area->save();
        $area->delete(); // SoftDelete
        return response()->json(['message' => 'Área enviada a la papelera.']);
    }

    // ==========================================
    // 5. GESTIÓN DE PAPELERA
    // ==========================================

    public function trashed(Request $request)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);
        $areas = Area::onlyTrashed() // Solo los eliminados
            ->with('editor:id,name') // Quién lo borró (relación editor)
            ->orderBy('deleted_at', 'desc')
            ->get();
        return response()->json($areas);
    }

    public function restore(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);
        $area = Area::onlyTrashed()->findOrFail($id); // Buscar el área eliminada por ID
        $area->updated_by = Auth::id();
        $area->restore();
        return response()->json(['message' => 'Área restaurada.']);
    }

    public function forceDelete(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);
        $area = Area::onlyTrashed()->findOrFail($id); // Buscar el área eliminada por ID
        if ($area->users()->exists()) { // Validar que no tenga usuarios asignados
            return response()->json(['message' => 'No se puede eliminar: Hay usuarios asignados a esta área.'], 409);
        } 
        if ($area->tickets()->exists()) { // Validar que no tenga tickets asociados (históricos)
            return response()->json(['message' => 'No se puede eliminar: Existen tickets históricos de esta área.'], 409);
        }
        $area->forceDelete();
        return response()->json(['message' => 'Área eliminada permanentemente.']);
    }
}