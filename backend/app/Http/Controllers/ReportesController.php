<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB; // Para consultas complejas con Query Builder
use Illuminate\Http\Request; // Para manejar las solicitudes HTTP
use App\Models\Ticket; // modelo tickets
use App\Models\User; // modelo usuarios
use App\Models\Reserva; // modelo reservas
use Barryvdh\DomPDF\Facade\Pdf; // Para generar PDFs
use Carbon\Carbon; // Para manejo de fechas
use App\Exports\ReporteTickets; // Exportación a Excel para actividades
use Maatwebsite\Excel\Facades\Excel; // Para exportar a Excel
use App\Exports\ReporteGlobalExport; // Exportación a Excel para reporte global

class ReportesController extends Controller
{
    /**===============================================
     * 1. Generar reportes en PDF (actividades o estadístico)
     *================================================*/
    public function generar(Request $request)
    {
        // 1. Configurar Fechas
        $inicio = $request->input('fecha_inicio') ? Carbon::parse($request->input('fecha_inicio'))->startOfDay() : Carbon::now()->startOfMonth();
        $fin = $request->input('fecha_fin') ? Carbon::parse($request->input('fecha_fin'))->endOfDay() : Carbon::now()->endOfMonth();
        $tipo = $request->input('tipo', 'estadistico'); // 'actividades' o 'estadistico', por defecto 'estadistico'
        // 2. Recibir Nombres de Autoridades
        $nombreDecano = $request->input('nombre_decano', 'Dr. Javier Hubert Peñaranda Mendez');
        $nombreJefe = $request->input('nombre_jefe', 'Lic. Florencio Antonio Mamani');
        // 3. Identificar al Generador
        $generadorId = $request->input('generador_id');
        $generador = User::find($generadorId);
        $nombreGenerador = $generador ? $generador->name : 'Sistema';

        // =========================================================
        // CASO A: INFORME DE ACTIVIDADES (INDIVIDUAL PARA TÉCNICO)
        // =========================================================
        if ($tipo === 'actividades') { 
            $userId = $request->input('tecnico_id') ?? $generadorId; // Si se especifica un técnico, lo usamos; si no, asumimos que el generador es el técnico para el informe de actividades
            if (!$userId) {
                return response()->json(['message' => 'Falta el ID del usuario para el reporte.'], 400);
            }
            $usuario = User::with('area')->find($userId);
            if (!$usuario) {
                return response()->json(['message' => 'Usuario no encontrado.'], 404);
            }
            $tickets = Ticket::with(['category', 'area'])  // Cargamos relaciones para mostrar detalles en el informe
                ->where(function ($query) use ($userId) { // Filtramos tickets donde el técnico asignado es el usuario o donde el usuario es colaborador
                    $query->where('assigned_to', $userId)
                          ->orWhere('colaborador_id', $userId);
                })
                ->where('status', 'cerrado') // Solo incluimos tickets cerrados para reflejar las actividades completadas
                ->whereBetween('updated_at', [$inicio, $fin]) // Filtramos por fecha de cierre para reflejar las actividades realizadas en el período seleccionado
                ->orderBy('updated_at', 'asc')
                ->get();
            $data = [ // Datos para el PDF
                'usuario' => $usuario, 
                'tickets' => $tickets,
                'mes' => $inicio->locale('es')->monthName,
                'gestion' => $inicio->year,
                'fecha_inicio' => $inicio->format('d/m/Y'),
                'fecha_fin' => $fin->format('d/m/Y'),
                'nombre_decano' => $nombreDecano,
                'nombre_jefe' => $nombreJefe,
            ];
            
            if (ob_get_length()) ob_end_clean(); // Limpiar el buffer de salida para evitar problemas con el PDF
            $pdf = Pdf::loadView('Reportes.actividades', $data); // Cargar la vista del PDF con los datos preparados
            return $pdf->stream('informe_actividades.pdf'); // Devolver el PDF para que se muestre en el navegador (puede cambiar a download() para forzar descarga)
        }

        // =========================================================
        // CASO B: INFORME ESTADÍSTICO (GLOBAL INSTITUCIONAL)
        // =========================================================
        else {
            $ticketsQuery = Ticket::whereBetween('created_at', [$inicio, $fin]); // Base de la consulta de tickets para el período seleccionado, sobre la cual aplicaremos diferentes filtros para cada métrica
            $total = $ticketsQuery->count(); // Total de tickets creados en el período seleccionado
            $cerrados = (clone $ticketsQuery)->where('status', 'cerrado')->count(); // Tickets cerrados en el período seleccionado (usamos clone para no modificar la consulta original)
            $abiertos = (clone $ticketsQuery)->where('status', '!=', 'cerrado')->count(); // Tickets abiertos (no cerrados) en el período seleccionado
            $promedio = $total > 0 ? round((clone $ticketsQuery)->whereNotNull('minutes_spent')->avg('minutes_spent'), 0) : 0; // Promedio de tiempo en minutos

            // 1. RENDIMIENTO TÉCNICOS
            $tecnicos = User::where('role', 'tecnico')
                ->withCount(['ticketsAssigned as total_asignados' => function($q) use ($inicio, $fin) {
                    $q->whereBetween('created_at', [$inicio, $fin]);
                }])
                ->withCount(['ticketsAssigned as total_resueltos' => function($q) use ($inicio, $fin) {
                    $q->where('status', 'cerrado')->whereBetween('updated_at', [$inicio, $fin]);
                }])
                ->get();

            // 2. MTTR POR CATEGORÍA (Mean Time To Resolve - Tiempo Promedio de Resolución)
            $mttr = Ticket::select('category_id', DB::raw('AVG(minutes_spent) as tiempo_promedio'))
                ->where('status', 'cerrado')
                ->whereBetween('updated_at', [$inicio, $fin])
                ->whereNotNull('category_id')
                ->whereNotNull('minutes_spent')
                ->groupBy('category_id')
                ->with('category')
                ->get();

            // 3. CARGA SEMANAL
            $semana = Ticket::select(DB::raw('DAYOFWEEK(created_at) as dia_num'), DB::raw('count(*) as total'))
                ->whereBetween('created_at', [$inicio, $fin])
                ->groupBy('dia_num')
                ->orderBy('dia_num')
                ->get();
            
            $nombresDias = [1=>'Dom', 2=>'Lun', 3=>'Mar', 4=>'Mié', 5=>'Jue', 6=>'Vie', 7=>'Sáb'];
            $semana = $semana->map(function($item) use ($nombresDias) {
                $item->dia_nombre = $nombresDias[$item->dia_num];
                return $item;
            });

            // 4. AGING (Instantánea actual)
            $hoy = Carbon::now();
            $aging = [
                'frescos' => Ticket::where('status', '!=', 'cerrado')->where('created_at', '>=', $hoy->copy()->subDay())->count(),
                'normal'  => Ticket::where('status', '!=', 'cerrado')->whereBetween('created_at', [$hoy->copy()->subDays(3), $hoy->copy()->subDay()])->count(),
                'critico' => Ticket::where('status', '!=', 'cerrado')->where('created_at', '<', $hoy->copy()->subDays(3))->count(),
            ];

            // 5. TOP USUARIOS RECURRENTES
            $topUsuarios = Ticket::select('user_id', DB::raw('count(*) as total'))
                ->whereBetween('created_at', [$inicio, $fin])
                ->whereHas('user', function($q) { $q->where('role', 'usuario'); })
                ->groupBy('user_id')
                ->with('user')
                ->orderByDesc('total')
                ->take(5)
                ->get();
            //6. areas mas afectadas
            $areas_hotspots = Ticket::select('area_id', DB::raw('count(*) as total'))
            ->whereBetween('created_at', [$inicio, $fin])
            ->with('area:id,name')
            ->groupBy('area_id')
            ->orderByDesc('total')
            ->take(5)
            ->get();

            // 7. DATOS DE RESERVAS (Infraestructura)
            $reservasQuery = Reserva::whereBetween('inicio', [$inicio, $fin]);
            $totalReservas = $reservasQuery->count();
            $reservasAprobadas = (clone $reservasQuery)->where('estado', 'aprobada')->count();
            
            $topAmbientes = Reserva::select('ambiente_id', DB::raw('count(*) as total'))
                ->whereBetween('inicio', [$inicio, $fin])
                ->where('estado', 'aprobada')
                ->groupBy('ambiente_id')
                ->with('ambiente:id,nombre')
                ->orderByDesc('total')
                ->take(3)
                ->get();

            // PREPARAR DATOS PARA EL PDF
            $data = [
                'titulo' => 'Informe Estadístico Institucional',
                'rango' => 'Del ' . $inicio->format('d/m/Y') . ' al ' . $fin->format('d/m/Y'),
                'fecha_emision' => Carbon::now()->format('d/m/Y H:i'),
                'generado_por' => $nombreGenerador,
                
                // Métricas Tickets
                'total' => $total,
                'cerrados' => $cerrados,
                'abiertos' => $abiertos,
                'promedio' => $promedio,
                'tecnicos' => $tecnicos,
                'mttr' => $mttr,
                'semana' => $semana,
                'aging' => $aging,
                'top_usuarios' => $topUsuarios,
                'areas_hotspots' => $areas_hotspots,
                'max_mttr' => $mttr->max('tiempo_promedio') ?? 1,
                'max_semana' => $semana->max('total') ?? 1,

                // Métricas Espacios
                'total_reservas' => $totalReservas,
                'reservas_aprobadas' => $reservasAprobadas,
                'top_ambientes' => $topAmbientes,
            ];
            
            if (ob_get_length()) ob_end_clean();
            $pdf = Pdf::loadView('Reportes.estadistico', $data);
            return $pdf->stream('reporte_estadistico.pdf');
        }
    }

    /**===============================
     * 2. Exportar reporte a Excel
     ==============================*/
    public function exportarExcel(Request $request)
    {
        // Configurar Fechas y Tipo de Reporte
        if (ob_get_length()) ob_end_clean(); // Limpiar el buffer de salida para evitar problemas con la descarga de Excel
        $inicio = Carbon::parse($request->input('fecha_inicio'))->startOfDay();
        $fin = Carbon::parse($request->input('fecha_fin'))->endOfDay();
        $tipo = $request->input('tipo', 'estadistico'); 
        
        if ($tipo === 'actividades') {
            $generadorId = $request->input('generador_id');
            $userId = $request->input('tecnico_id') ?? $generadorId;
            
            $nombreArchivo = "informe_actividades_" . date('d-m-Y') . ".xlsx";
            return Excel::download(new ReporteTickets($inicio, $fin, $userId), $nombreArchivo);
        } else {
            $nombreArchivo = "reporte_global_" . date('d-m-Y') . ".xlsx";
            return Excel::download(new ReporteGlobalExport($inicio, $fin), $nombreArchivo);
        }
    }
}