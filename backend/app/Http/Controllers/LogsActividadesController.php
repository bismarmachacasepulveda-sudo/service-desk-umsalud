<?php

namespace App\Http\Controllers;

use App\Models\Actividades; // Modelo de actividades para acceder a los logs
use Illuminate\Http\Request; // Para manejar las solicitudes HTTP

class LogsActividadesController extends Controller
{
    /**======================================
     * 1. MUESTRA LA LISTA DE ACTIVIDADES (Solo Admin)
     * =====================================*/
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // 1. Empezamos la consulta con los filtros dinámicos
        $query = Actividades::with('user:id,name,role'); // Consulta actividades, con relacion con usuario para mostrar su nombre y rol
        // Filtro por acción (created, updated, deleted)
        $query->when($request->filled('action'), function ($q) use ($request) { // Si el campo "action" está presente en la solicitud, aplicamos el filtro
            return $q->where('action', $request->action); // retorna la acción
        });
        // Filtro por tipo de entidad (Ticket, User, etc)
        $query->when($request->filled('tipo'), function ($q) use ($request) { // Si el campo "tipo" está presente en la solicitud, aplicamos el filtro
            return $q->where('auditable_type', 'LIKE', '%' . $request->tipo . '%'); // Retorna el tipo de entidad (Ticket, User, etc) usando un filtro "LIKE" para permitir coincidencias parciales
        });

        // 2. Ejecutamos la paginación después de filtrar
        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        // --- ESTRATEGIA DE DICCIONARIO PARA MAPEAR IDs A NOMBRES (USUARIOS, ÁREAS, CATEGORÍAS, AMBIENTES) ---
        $userIds = collect();
        $areaIds = collect();
        $catIds = collect();
        $ambienteIds = collect();

        foreach ($logs as $log) {
            $todosLosValores = collect($log->old_values)->merge($log->new_values); // Unimos los valores antiguos y nuevos para extraer todos los IDs
            
            if ($todosLosValores->has('assigned_to')) $userIds->push($todosLosValores->get('assigned_to')); // si el log tiene "assigned_to" pusheamos ese ID al array de userIds para luego mapearlo a un nombre de usuario
            if ($todosLosValores->has('assigned_by_id')) $userIds->push($todosLosValores->get('assigned_by_id'));
            if ($todosLosValores->has('user_id')) $userIds->push($todosLosValores->get('user_id'));
            if ($todosLosValores->has('area_id')) $areaIds->push($todosLosValores->get('area_id'));
            if ($todosLosValores->has('created_by')) $userIds->push($todosLosValores->get('created_by'));
            if ($todosLosValores->has('approved_by_id')) $userIds->push($todosLosValores->get('approved_by_id'));
            if ($todosLosValores->has('rejected_by_id')) $userIds->push($todosLosValores->get('rejected_by_id'));
            if ($todosLosValores->has('category_id')) {$catIds->push($todosLosValores->get('category_id'));}
            if ($todosLosValores->has('ambiente_id')) {$ambienteIds->push($todosLosValores->get('ambiente_id'));}
        }

        $mapeos = [ // Creamos un array de mapeos para convertir IDs a nombres legibles en el frontend
            'users' => \App\Models\User::whereIn('id', $userIds->unique()->filter())->pluck('name', 'id'), 
            'areas' => \App\Models\Area::whereIn('id', $areaIds->unique()->filter())->pluck('name', 'id'),
            'categories' => \App\Models\Category::withTrashed() ->whereIn('id', $catIds->unique()->filter())->pluck('name', 'id'),
            'ambientes' => \App\Models\Ambiente::withTrashed()->whereIn('id', $ambienteIds->unique()->filter())->pluck('nombre', 'id'),
        ];

        return response()->json([ // Devolvemos los logs y los mapeos
            'logs' => $logs,
            'mapeos' => $mapeos
        ]);
    }
}