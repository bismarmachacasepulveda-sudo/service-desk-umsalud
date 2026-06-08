<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reserva;
use App\Models\Ambiente;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Notifications\SystemNotification;

class ReservaController extends Controller
{
    /**================================
     * 1. LISTAR
     *================================*/
    public function index(Request $request)
    {
        $query = Reserva::with(['user:id,name', 'ambiente:id,nombre,ubicacion', 'procesador:id,name'])
                        ->orderBy('inicio', 'asc');// Ordenamos por fecha de inicio para mostrar primero las reservas más próximas

        // Filtro por fecha específica
        if ($request->has('fecha')) {
            $query->whereDate('inicio', $request->fecha);
        }
        // Filtro por ambiente
        if ($request->has('ambiente_id')) {
            $query->where('ambiente_id', $request->ambiente_id);
        }
        return response()->json($query->get());
    }

    /**================================
     * 2. CREAR (Solicitud de Usuario)
     *=================================*/
    public function store(Request $request)
    {
        $request->validate([
            'ambiente_id' => 'required|exists:ambientes,id',
            'inicio' => 'required|date|after:now',
            'fin' => 'required|date|after:inicio',
            'motivo' => 'required|string|max:255'
        ]);
        // Validación de Fechas y Horarios
        $inicio = Carbon::parse($request->inicio);
        $fin = Carbon::parse($request->fin);
        // Validación de Horario Institucional (07:00 - 21:00)
        if ($inicio->hour < 7 || $fin->hour > 21 || ($fin->hour == 21 && $fin->minute > 0)) {
            return response()->json(['message' => 'El horario permitido es de 07:00 a 21:00.'], 422);
        }
        // Validar Estado del Ambiente
        $ambiente = Ambiente::findOrFail($request->ambiente_id);
        if ($ambiente->estado === 'mantenimiento') {
            return response()->json(['message' => 'Este ambiente no está disponible por mantenimiento.'], 422);
        }
        // ===========LÓGICA ANTI-CHOQUE MEJORADA===========
        // Solo chocamos con reservas que estén 'aprobada' o 'pendiente'.
        // Ignoramos 'rechazada', 'cancelada' o 'finalizada'.
        $choque = Reserva::where('ambiente_id', $request->ambiente_id)
            ->whereIn('estado', ['aprobada', 'pendiente'])
            ->where(function ($query) use ($inicio, $fin) {
                $query->where('inicio', '<', $fin)
                      ->where('fin', '>', $inicio);
            })
            ->exists(); // Solo verificamos si existe algún choque
        if ($choque) {
            return response()->json(['message' => 'Lo sentimos, este horario ya está ocupado o solicitado.'], 409);
        }

        $reserva = Reserva::create([ // Creamos la reserva con estado 'pendiente' por defecto
            'user_id'     => Auth::id(),
            'ambiente_id' => $request->ambiente_id,
            'inicio'      => $inicio,
            'fin'         => $fin,
            'motivo'      => $request->motivo,
            'estado'      => 'pendiente',
            'created_by'  => Auth::id()
        ]);
        // Notificar a los Admins
        $this->notificarAdmins($reserva, $ambiente, $inicio);
        return response()->json($reserva->load(['user', 'ambiente']), 201);
    }

    /**================================
     * 3. ACTUALIZAR (Gestión del Admin)
     *=================================*/
    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        $reserva = Reserva::with(['user', 'ambiente'])->findOrFail($id);
        
        $request->validate([
            'estado' => 'required|in:aprobada,rechazada,finalizada,pendiente',
            'motivo_rechazo' => 'required_if:estado,rechazada|nullable|string|max:255'
        ]);
        $estadoAnterior = $reserva->estado;
        $reserva->update([
            'estado' => $request->estado,
            'motivo_rechazo' => $request->estado === 'rechazada' ? $request->motivo_rechazo : null,
            'processed_by_id' => Auth::id(),
            'updated_by' => Auth::id()
        ]);
        // Notificar al usuario sobre el cambio
        if ($estadoAnterior !== $request->estado) {
            $this->notificarUsuarioCambioEstado($reserva);
        }

        return response()->json($reserva);
    }

    /**================================
     * 4. CANCELAR / ELIMINAR
     *=================================*/
    public function destroy(Request $request, $id)
    {
        $reserva = Reserva::findOrFail($id);
        // Si es el usuario dueño, la marcamos como CANCELADA (No la borramos)
        if ($request->user()->id === $reserva->user_id && $reserva->estado === 'pendiente') {
            $reserva->update(['estado' => 'cancelada']);
            return response()->json(['message' => 'Reserva cancelada correctamente.']);
        }
        // Si es Admin, la mandamos a la PAPELERA
        if ($request->user()->role === 'admin') {
            $reserva->delete();
            return response()->json(['message' => 'Reserva enviada a la papelera.']);
        }

        return response()->json(['message' => 'Acción no permitida.'], 403);
    }
    // --- MÉTODOS PRIVADOS DE NOTIFICACIÓN ---

    private function notificarAdmins($reserva, $ambiente, $inicio) {
        // Notificar a todos los administradores sobre la nueva solicitud de reserva
        try {
            $admins = User::where('role', 'admin')->get();
            $fechaTexto = $inicio->locale('es')->isoFormat('dddd D [de] MMMM, h:mm a');
            
            foreach ($admins as $admin) { // Para cada admin, enviamos una notificación del sistema
                $admin->notify(new SystemNotification(
                    '📅 Nueva Solicitud de Reserva',
                    "Ambiente: {$ambiente->nombre}\nSolicitante: {$reserva->user->name}\nFecha: {$fechaTexto}",
                    '/admin/reservas',
                    'bi-calendar-event'
                ));
            }
        } catch (\Exception $e) { \Log::error($e->getMessage()); }
    }
    // Notificar al usuario sobre el cambio de estado de su reserva (aprobada o rechazada)
    private function notificarUsuarioCambioEstado($reserva) {
        try {
            $titulo = $reserva->estado === 'aprobada' ? '✅ Reserva Confirmada' : '❌ Reserva No Aprobada';
            $mensaje = $reserva->estado === 'aprobada' 
                ? "Tu solicitud para {$reserva->ambiente->nombre} ha sido aceptada."
                : "Tu solicitud fue rechazada. Motivo: " . ($reserva->motivo_rechazo ?? 'No especificado');

            $reserva->user->notify(new SystemNotification(
                $titulo,
                $mensaje,
                '/mis-reservas',
                $reserva->estado === 'aprobada' ? 'bi-check-circle' : 'bi-x-circle'
            ));
        } catch (\Exception $e) { \Log::error($e->getMessage()); }
    }

    // ==========================================
    // GESTIÓN DE PAPELERA (RESERVAS)
    // ==========================================

    /**
     * Listar solo reservas eliminadas
     */
    public function trashed(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => '403'], 403);
        }
        $reservas = Reserva::onlyTrashed()
            ->with([
                'user:id,name', 
                'ambiente:id,nombre', 
                'editor:id,name' // Para saber quién la borró
            ])
            ->orderBy('deleted_at', 'desc')
            ->get();

        return response()->json($reservas);
    }

    /**
     * Restaurar una reserva borrada
     */
    public function restore(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => '403'], 403);
        }
        $reserva = Reserva::onlyTrashed()->findOrFail($id);
        // Registramos quién restauró
        $reserva->updated_by = Auth::id();
        $reserva->restore();
        return response()->json(['message' => 'Reserva restaurada correctamente.']);
    }

    /**
     * Borrado físico definitivo
     */
    public function forceDelete(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => '403'], 403);
        }
        $reserva = Reserva::onlyTrashed()->findOrFail($id);
        $reserva->forceDelete();

        return response()->json(['message' => 'Reserva eliminada permanentemente de la base de datos.']);
    }
}