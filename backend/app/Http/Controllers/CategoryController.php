<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request; // Para manejar las solicitudes HTTP
use Illuminate\Support\Facades\Auth; // Para obtener el ID del usuario autenticado

class CategoryController extends Controller
{
    /**====================================
     * 1. LISTAR (Público según roles)
    ====================================== */
    public function index(Request $request)
    {
        $user = $request->user(); // Usuario autenticado
        $query = Category::orderBy('name', 'asc'); // consultamos las categorias ordenadas por nombre
        // Filtro 1: Si es usuario final, SOLO ve categorías 'activas' y 'públicas'
        if ($user->role === 'usuario') {
            $query->where('active', true)
                  ->where('visibilidad', 'publico');
        }
        // Filtro 2: Técnicos ven activas (públicas y técnicas)
        elseif ($user->role === 'tecnico') {
            $query->where('active', true);
        }
        // Admin ve TODO (incluso las 'active = false' para poder reactivarlas)
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        return response()->json($query->get());
    }
    /**========================================
     * 2. CREAR (Solo Admin)
     ==========================================*/
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        $request->validate([ // Validación de datos de entrada
            'name'        => 'required|string|max:50|unique:categories,name',
            'description' => 'nullable|string|max:255',
            'tipo'        => 'required|in:ticket,repositorio',
            'visibilidad' => 'required|in:publico,tecnico'
        ]);

        $category = Category::create([ // Creación de la nueva categoría con los datos validados
            'name'        => $request->name,
            'description' => $request->description,
            'tipo'        => $request->tipo,
            'visibilidad' => $request->visibilidad,
            'active'      => true,
            'created_by'  => Auth::id(), //Auditoría
        ]);
        return response()->json($category, 201);
    }

    /**=======================================
     * 3. ACTUALIZAR (Solo Admin)
     ==========================================*/
    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        $category = Category::findOrFail($id); // Buscar la categoria por ID
        $request->validate([ // Validación de datos de entrada
            'name'        => 'required|string|max:50|unique:categories,name,' . $id,
            'description' => 'nullable|string|max:255',
            'tipo'        => 'required|in:ticket,repositorio',
            'visibilidad' => 'required|in:publico,tecnico',
            'active'      => 'boolean'
        ]);

        $category->update([ // Actualización de la categoría con los nuevos datos
            'name'        => $request->name,
            'description' => $request->description,
            'tipo'        => $request->tipo,
            'visibilidad' => $request->visibilidad,
            'active'      => $request->active,
            'updated_by'  => Auth::id() // Quién actualizó
        ]);
        return response()->json($category);
    }

    /**========================================
     * 4. ELIMINAR LÓGICAMENTE (Papelera)
     ==========================================*/
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        $category = Category::findOrFail($id); // Buscar la categoría por ID
        $category->updated_by = Auth::id(); // Quién la eliminó (para auditoría)
        $category->save(); // Guardar el cambio de auditoría antes de eliminar
        $category->delete(); // SoftDelete
        return response()->json(['message' => 'Categoría enviada a la papelera.']);
    }

    /**========================================
     * 5. OBTENER CATEGORÍAS EN PAPELERA
     ==========================================*/
    public function trashed(Request $request)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);
        $categories = Category::onlyTrashed() // Solo las categorías eliminadas
            ->with('editor:id,name') // Quién la borró
            ->orderBy('deleted_at', 'desc')
            ->get();

        return response()->json($categories);
    }

    /**========================================
     * 6. RESTAURAR CATEGORÍA (Papelera)
     ==========================================*/
    public function restore(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);
        $category = Category::onlyTrashed()->findOrFail($id); // Buscar la categoría eliminada por ID
        $category->updated_by = Auth::id(); // Quién restauró
        $category->restore();
        return response()->json(['message' => 'Categoría restaurada.']);
    }
    /**========================================
     * 7. ELIMINAR CATEGORÍA PERMANENTEMENTE
     ==========================================*/
    public function forceDelete(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);
        $category = Category::onlyTrashed()->findOrFail($id); // Buscar la categoría eliminada por ID
        // Validación opcional de seguridad
        //if ($category->tickets()->exists()) { return error... } // Si existen tickets asociados, no se puede eliminar físicamente
        $category->forceDelete(); // Eliminación física definitiva
        return response()->json(['message' => 'Categoría eliminada permanentemente.']);
    }
}