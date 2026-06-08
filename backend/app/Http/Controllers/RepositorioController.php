<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Repositorio; // Modelo del repositorio de archivos
use Illuminate\Support\Facades\Storage; // Para manejar el almacenamiento de archivos
use Illuminate\Support\Facades\Auth; // Para obtener el ID del usuario autenticado

class RepositorioController extends Controller
{
    /**==============================
     * 1. LISTAR ARCHIVOS
     *==============================*/
    public function index(Request $request)
    {
        $user = $request->user();
        // Cargamos la relación del creador (solo id, name y role para no exponer datos sensibles) y la categoría del archivo
        $query = Repositorio::with(['creator:id,name,role', 'categoria']);
        //  REGLA DE SEGURIDAD:
        // Si es usuario, SOLO ve 'publico'. Si es técnico/admin, ve todo.
        if ($user->role === 'usuario') {
            $query->where('visibilidad', 'publico');
        }
        // Filtros solo la categoria seleccionada
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**==============================
     * 2. SUBIR ARCHIVO
     *==============================*/
    public function store(Request $request)
    {
        // 1. Seguridad: Solo técnicos y admins
        if ($request->user()->role === 'usuario') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        $request->validate([
            'archivo'     => 'required|file|max:51200', // Máx 50MB
            'descripcion' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'visibilidad' => 'required|in:publico,tecnico',
        ]);
        $file = $request->file('archivo'); // Obtenemos el archivo subido
        $ruta = $file->store('repositorio', 'public'); // Guardamos el archivo en la carpeta 'repositorio' del disco 'public' y obtenemos la ruta de almacenamiento para guardarla en la base de datos
        
        // Crear registro en BD
        $archivo = Repositorio::create([
            'created_by'      => Auth::id(), // Auditoría
            'category_id'     => $request->category_id,
            'nombre_original' => $file->getClientOriginalName(),
            'ruta_archivo'    => $ruta,
            'extension'       => strtolower($file->getClientOriginalExtension()),
            'mime_type'       => $file->getMimeType(),
            'visibilidad'     => $request->visibilidad,
            'descripcion'     => $request->descripcion,
            'peso_bytes'      => $file->getSize() // Guardamos bytes exactos
        ]);

        return response()->json($archivo->load('creator', 'categoria'), 201);
    }

    /**==============================
     * 3. DESCARGAR
     *==============================*/
    public function download($id)
    {
        $archivo = Repositorio::findOrFail($id);
        $user = Auth::user();
        // Seguridad: Evitar que un usuario baje un archivo técnico por URL directa
        if ($archivo->visibilidad === 'tecnico' && $user->role === 'usuario') {
            return response()->json(['message' => 'Archivo restringido.'], 403);
        }
        // Verificar que el archivo físico exista antes de intentar descargarlo
        if (!Storage::disk('public')->exists($archivo->ruta_archivo)) {
            return response()->json(['message' => 'El archivo físico no existe.'], 404);
        }
        // Descargar el archivo con su nombre original
        return Storage::disk('public')->download($archivo->ruta_archivo, $archivo->nombre_original);
    }

    /**===========================================
     * 4. ELIMINAR (Soft Delete - Mover a Papelera)
     *===========================================*/
    public function destroy(Request $request, $id)
    {
        $archivo = Repositorio::findOrFail($id);
        // Solo Admin o el Creador pueden borrar
        if ($request->user()->role !== 'admin' && $request->user()->id !== $archivo->created_by) {
            return response()->json(['message' => 'No tienes permiso.'], 403);
        }
        // Auditoría manual antes de borrar
        $archivo->updated_by = Auth::id();
        $archivo->save();
        $archivo->delete(); //SOLO BORRADO LÓGICO (El archivo físico sigue ahí)
        return response()->json(['message' => 'Archivo enviado a la papelera.']);
    }

    /**===========================================
     * 5. GESTIÓN DE PAPELERA (Solo Admin)
     *===========================================*/

    public function trashed(Request $request)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);

        $archivos = Repositorio::onlyTrashed()
            ->with(['creator:id,name', 'categoria'])
            ->orderBy('deleted_at', 'desc')
            ->get();

        return response()->json($archivos);
    }

    public function restore(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);

        $archivo = Repositorio::onlyTrashed()->findOrFail($id);
        $archivo->updated_by = Auth::id();
        $archivo->restore();

        return response()->json(['message' => 'Archivo restaurado.']);
    }

    public function forceDelete(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') return response()->json(['message' => '403'], 403);

        $archivo = Repositorio::onlyTrashed()->findOrFail($id);
        if (Storage::disk('public')->exists($archivo->ruta_archivo)) {
            Storage::disk('public')->delete($archivo->ruta_archivo);
        }

        $archivo->forceDelete(); // Adios registro BD

        return response()->json(['message' => 'Archivo eliminado permanentemente.']);
    }
}