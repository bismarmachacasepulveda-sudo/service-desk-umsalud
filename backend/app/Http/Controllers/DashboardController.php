<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket; // modelo de tickets
use App\Models\User; // modelo de usuarios
use App\Models\Area; // modelo de áreas
use App\Models\Reserva; // modelo de reservas
use App\Models\Ambiente; // modelo de ambientes
use App\Models\Repositorio; // modelo de repositorio de archivos
use Illuminate\Support\Facades\DB; // Para consultas más complejas con agregaciones
use Carbon\Carbon; // Para manejo de fechas

class DashboardController extends Controller
{
    /**============================================
     *  1. OBTENER ESTADÍSTICAS DEL DASHBOARD 
     ============================================*/
    public function getStats(Request $request)
    {
        $user = $request->user(); // Usuario autenticado
        $stats = []; // Array para acumular las estadísticas a retornar
        $hoy = Carbon::today(); // Fecha de hoy para consultas relacionadas con reservas

        // --- ESTADÍSTICAS PARA ADMINISTRADOR ---
        if ($user->role === 'admin') {
            // 1. Resumen de Tickets
            $stats['tickets'] = [ // Sub-array para estadísticas relacionadas con tickets
                'total'      => Ticket::count(), // Total de tickets en el sistema
                'abiertos'   => Ticket::where('status', 'abierto')->count(), // Tickets que aún no han sido atendidos
                'en_proceso' => Ticket::where('status', 'en_proceso')->count(),
                'cerrados'   => Ticket::where('status', 'cerrado')->count(),
            ];

            // 2. Resumen de Usuarios
            $stats['usuarios'] = [ // Sub-array para estadísticas relacionadas con usuarios
                'total_finales' => User::where('role', 'usuario')->count(),
                'total_tecnicos' => User::where('role', 'tecnico')->count(),
            ];
            
            // 3. Gestión de Espacios
            $stats['espacios'] = [ // Sub-array para estadísticas relacionadas con ambientes y reservas
                'total_ambientes' => Ambiente::count(),
                'reservas_hoy'    => Reserva::whereDate('inicio', $hoy)->count(),
                'ambientes_mantenimiento' => Ambiente::where('estado', 'mantenimiento')->count(),
            ];

            // 4. Áreas con más incidencias (Top 5)
            $stats['areas_hotspots'] = Ticket::select('area_id', DB::raw('count(*) as total'))
                ->with('area:id,name')
                ->groupBy('area_id')
                ->orderByDesc('total')
                ->take(5)
                ->get();

            // 5. Rendimiento de Técnicos (Eficiencia)
            $stats['rendimiento_tecnicos'] = User::where('role', 'tecnico')
                ->withCount(['ticketsAssigned as total_asignados'])
                ->withCount(['ticketsAssigned as total_resueltos' => function ($query) { // Contamos solo los tickets resueltos (status = cerrado) para calcular la eficiencia
                    $query->where('status', 'cerrado');
                }])
                ->get()
                ->map(function ($tech) {
                    $tech->eficiencia = $tech->total_asignados > 0 
                        ? round(($tech->total_resueltos / $tech->total_asignados) * 100, 1) 
                        : 0;
                    return $tech;
                })
                ->sortByDesc('eficiencia')->values();

            // 6. Tickets por Categoría (Para Gráfico de Dona)
            $stats['tickets_por_categoria'] = Ticket::select('category_id', DB::raw('count(*) as total'))
                ->whereNotNull('category_id') // Filtramos solo las categorías que existen
                ->with('category:id,name') // Con la relación de categoría para mostrar el nombre en el frontend
                ->groupBy('category_id') // Agrupamos por categoría
                ->get(); // Obtenemos el resultado para mostrar en un gráfico de dona (categoría vs cantidad de tickets)

            // 7. Volumen Mensual (Últimos 6 meses)
            $stats['tendencia_mensual'] = Ticket::select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as mes'), // Agrupamos por mes (formato YYYY-MM)
                    DB::raw('count(*) as total') // Contamos el total de tickets creados en cada mes
                )
                ->groupBy('mes') // Agrupamos por el campo calculado "mes"
                ->orderBy('mes', 'asc')
                ->take(6) // Tomamos solo los últimos 6 meses para mostrar una tendencia en el tiempo
                ->get();

            // 8. Ambientes más solicitados (Top 3)
            $stats['top_ambientes'] = Reserva::select('ambiente_id', DB::raw('count(*) as total'))
                ->with('ambiente:id,nombre')
                ->groupBy('ambiente_id')
                ->orderByDesc('total')
                ->take(3)
                ->get();
        }

        // --- ESTADÍSTICAS PARA TÉCNICO ---
        if ($user->role === 'tecnico') {
            $stats['mi_panel'] = [
                'mis_pendientes' => Ticket::where('assigned_to', $user->id)
                    ->whereIn('status', ['abierto', 'en_proceso'])->count(),
                'mis_resueltos'  => Ticket::where('assigned_to', $user->id)
                    ->where('status', 'cerrado')->count(),
                'bolsa_trabajo'  => Ticket::whereNull('assigned_to')
                    ->where('status', 'abierto')->count(),
            ];
            
            // Sus próximas reservas (si las tiene)
            $stats['mis_proximas_reservas'] = Reserva::where('user_id', $user->id)
                ->where('inicio', '>=', now())
                ->with('ambiente:id,nombre')
                ->take(3)->get();
        }

        // --- ESTADÍSTICAS PARA USUARIO FINAL ---
        if ($user->role === 'usuario') {
            $stats['mis_indicadores'] = [
                'tickets_activos' => Ticket::where('user_id', $user->id)
                    ->where('status', '!=', 'cerrado')->count(),
                'reservas_aprobadas' => Reserva::where('user_id', $user->id)
                    ->where('estado', 'aprobada')
                    ->whereDate('inicio', '>=', $hoy)->count(),
            ];
        }

        // --- WIDGET COMÚN: RECURSOS RECIENTES ---
        $filesQuery = Repositorio::with('creator:id,name');
        if ($user->role === 'usuario') {
            $filesQuery->where('visibilidad', 'publico');
        }
        $stats['archivos_recientes'] = $filesQuery->orderBy('created_at', 'desc')->take(5)->get();

        return response()->json($stats);
    }
}