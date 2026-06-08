<?php

namespace App\Http\Controllers;

use App\Models\Ticket; // Modelo
use Illuminate\Http\Request; // Para manejar solicitudes HTTP
use Illuminate\Support\Facades\Validator; // Para validación de datos
use App\Notifications\SystemNotification; // Notificaciones personalizadas
use Illuminate\Support\Facades\Notification; // Para enviar notificaciones a usuarios
use Illuminate\Bus\Queueable; // Para manejar colas de notificaciones (enviar en segundo plano)
use Illuminate\Contracts\Queue\ShouldQueue; // Interfaz para indicar que la notificación debe ser encolada
use App\Models\User; // modelo user
use Illuminate\Support\Facades\Auth;//para Auth::id()
use App\Events\DashboardUpdate; // Evento para actualizar el dashboard en tiempo real

class TicketController extends Controller
{
    /**
     * 1. Almacena un nuevo ticket en la base de datos.
     */
public function store(Request $request)
    {
        /** VALIDACIÓN */
        $validator = Validator::make($request->all(), [
            'area_id' => 'required|exists:areas,id',
            'user_id' => 'required|exists:users,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'impacto' => 'required|in:individual,departamental,general',
            'urgencia' => 'required|in:baja,media,alta',
            'assigned_to' => 'nullable|exists:users,id',
            'archivo' => 'nullable|file|max:10240', // Máx 10MB
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Error de validación', 'errors' => $validator->errors()], 422);
        }

        /** 2. CÁLCULO DE PRIORIDAD (Matriz ITIL) */
        $prioridadCalculada = Ticket::calcularPrioridad($request->impacto, $request->urgencia); // Usamos la función estática del modelo Ticket

        /** 3. PROCESAR ARCHIVO */
        $ruta = null;
        $nombreOriginal = null;

        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo'); // Obtenemos el archivo
            $nombreOriginal = $file->getClientOriginalName(); // Guardamos el nombre original para mostrarlo luego
            $ruta = $file->store('evidencias_tickets', 'public'); // Guardamos el archivo en la carpeta 'storage/app/public/evidencias_tickets' y obtenemos la ruta relativa
        }

        /** 4. CREAR EL TICKET */
        $ticket = Ticket::create([  // Creamos el ticket con los datos recibidos y calculados
            'area_id' => $request->area_id,
            'user_id' => $request->user_id,
            'subject' => $request->subject,
            'description' => $request->description,
            'impacto' => $request->impacto,
            'urgencia' => $request->urgencia,
            'priority' => $prioridadCalculada, // Resultado automático
            'status' => 'abierto',
            'assigned_to' => $request->assigned_to,
            'ruta_archivo' => $ruta,
            'nombre_archivo' => $nombreOriginal,
            'created_by'     => Auth::id(),
            'assigned_by_id' => $request->assigned_to ? Auth::id() : null,
        ]);

        /** 5. NOTIFICACIÓN A TÉCNICOS Y ADMINS */
        $destinatarios = User::whereIn('role', ['tecnico', 'admin'])->get(); // Obtenemos técnicos y admins para notificarles del nuevo ticket
        Notification::send($destinatarios, new SystemNotification( // Enviamos una notificación a cada técnico y admin
            // Personalizamos el mensaje con el ID del ticket y el asunto (limitado a 30 caracteres para no saturar la notificación)
        'Nuevo Ticket #' . $ticket->id,
        $request->user()->name . ' ha reportado: ' . substr($ticket->subject, 0, 30) . '...',
        '/tickets/' . $ticket->id,
        'bi-ticket-perforated-fill'
    ));
    broadcast(new DashboardUpdate()); // Emitimos el evento para actualizar el dashboard en tiempo real
    return response()->json($ticket, 201); // Devolvemos el ticket creado con código 201 (Creado)
}

    /** 2. LISTAR TICKETS */
    public function index(Request $request)
    {
        $user = $request->user();// Obtenemos el usuario autenticado para aplicar filtros de seguridad según su rol
        $query = Ticket::with(['area', 'user', 'assignedUser', 'colaborador', 'category', 'creator']) // Mostrar ticket
                       ->orderBy('created_at', 'desc'); // Ordenamos por fecha de creación (más reciente primero)
        // Filtro de Seguridad según ROL
        if ($user->role === 'usuario') { // Si es un usuario común, solo ve sus propios tickets
            $query->where('user_id', $user->id);
        }
        // (Si es 'admin' o 'tecnico', no aplicamos filtro, ven todo)
        //$tickets = $query->paginate(3);
        $tickets = $query->get(); // Obtenemos los tickets según el filtro aplicado
        return response()->json($tickets); // Devolvemos los tickets en formato JSON para que Angular los consuma
    }
    /**
     * 3. Eliminar ticket (Solo Admin).
     */
    public function destroy(Request $request, $id)
    {
    if ($request->user()->role !== 'admin') { // Verificación de rol: Solo los administradores pueden eliminar tickets
        return response()->json(['message' => 'No tienes permiso para realizar esta acción.'], 403);
    }
    $ticket = Ticket::findOrFail($id); // Buscamos el ticket por su ID
    $ticket->updated_by = $request->user()->id; // Auditoría: Registramos quién está realizando el borrado
    $ticket->save();  // guardamos
    $ticket->delete(); // Eliminacion Logica
    broadcast(new DashboardUpdate()); // Emitimos el evento para actualizar el dashboard en tiempo real
    return response()->json([ // Devolvemos un mensaje de éxito indicando que el ticket ha sido enviado a la papelera
        'message' => 'El ticket #' . $id . ' ha sido enviado a la papelera correctamente.'], 200);
    }
    
     /**
        * 4. Mostrar detalles de un ticket específico.
     */
    public function show($id)
    {
        // Buscamos el ticket con todas sus relaciones
        $ticket = Ticket::with(['user', 'area', 'assignedUser','category','creator', 'assigner', 'closer', 'editor','colaborador'])->findOrFail($id);
        return response()->json($ticket);
    }

    /**
     * 5. Actualiza el ticket (usado para cerrar, reasignar o cambiar estado/prioridad).
    */
    public function update(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);//busca el ticket actual
        $user = $request->user();//Busca al usuario que esta haciendo la edicion,actualizacion, etc.
    // Tomar ticket (Asignarse a sí mismo)
        if ($request->query('action') === 'take') {
            if ($user->role !== 'tecnico' && $user->role !== 'admin') { // Solo técnicos y admins pueden tomar tickets
                return response()->json(['message' => 'No autorizado'], 403);
            }
            $ticket->update([ // Actualizamos el ticket con el nuevo estado y asignación
                'assigned_to'    => $user->id,
                'status'         => 'en_proceso',
                'assigned_by_id' => $user->id, // Se asignó él mismo
                'updated_by'     => $user->id   // Auditoría
            ]);
    // Notificar al dueño del ticket que su solicitud está siendo atendida
            $ticket->user->notify(new SystemNotification( 
                '¡Ticket Atendido!',
                'El técnico ' . $user->name . ' ha comenzado a trabajar en tu solicitud.' . $ticket->id,
                '/tickets/' . $ticket->id,
                'bi-tools'
            ));
            return response()->json($ticket->load(['user', 'area', 'assignedUser', 'category','updated_by']), 200);
        }
    // Liberar ticket
            if ($request->query('action') === 'release') {
            if ($ticket->assigned_to !== $user->id && $user->role !== 'admin') { // Solo el técnico asignado o un admin pueden liberar el ticket
                return response()->json(['message' => 'No puedes liberar un ticket ajeno.'], 403);
            }
            $ticket->update([
                'assigned_to'    => null,
                'colaborador_id' => null, // Si se libera, también se va el colaborador
                'status'         => 'abierto',
                'updated_by'     => $user->id
            ]);
            return response()->json($ticket->load(['user', 'area', 'assignedUser', 'category', 'editor', 'colaborador']), 200);
        }

    // LÓGICA DE ACTUALIZACIÓN GENERAL (Cambiar estado, prioridad, reasignar, etc)
        // --- A. DETECTAR INTENCIÓN ---
        $nuevoEstado = $request->input('status');
        $esAdmin = $user->role === 'admin';
        $esTecnico = $user->role === 'tecnico';
        $esTecnicoAsignado = $ticket->assigned_to === $user->id;
        $esDueño = $ticket->user_id === $user->id;
        $esLibre = $ticket->assigned_to === null; 
        $esCierre = $nuevoEstado === 'cerrado';
        $esReapertura = $nuevoEstado === 'en_proceso';

        // --- B. VALIDACIÓN DE PERMISOS ---
        // Permitimos entrar si se cumple ALGUNA de estas condiciones:
        // 1. Es Admin
        // 2. Es el Técnico Asignado
        // 3. Es el Dueño Y está cerrando O reabriendo (rechazando solución)
        // 4. Es Técnico Y el ticket está libre (para auto-asignarse)  
        if ( 
            !$esAdmin && 
            !$esTecnicoAsignado && 
            !($esDueño && ($esCierre || $esReapertura)) && 
            !($esTecnico && $esLibre)
        ) {
             return response()->json(['message' => 'No tienes permiso para modificar este ticket.'], 403);
        }
        // --- VALIDACIÓN DE DATOS ---
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:abierto,en_proceso,en_espera,resuelto,cerrado',
            'assigned_to' => 'nullable|exists:users,id',
            'impacto' => 'sometimes|in:individual,departamental,general',
            'urgencia' => 'sometimes|in:baja,media,alta',
            'priority' => 'nullable|in:baja,media,alta,critica',
            'assigned_to'    => 'nullable|exists:users,id',
            'colaborador_id' => 'nullable|exists:users,id|different:assigned_to',
            // Exigir datos técnicos SOLO si se marca como RESUELTO o CERRADO
            'solution_notes' => ($nuevoEstado === 'resuelto') ? 'required|string' : 'nullable', 
            'minutes_spent' => ($nuevoEstado === 'resuelto') ? 'required|integer' : 'nullable', 
            'category_id' => ($nuevoEstado === 'resuelto') ? 'required|exists:categories,id' : 'nullable',
        ]);

        if ($validator->fails()) { // Si la validación falla, devolvemos un error con los detalles
            return response()->json(['message' => 'Error de validación.', 'errors' => $validator->errors()], 422);
        }
        $data = $request->all(); // Tomamos todos los datos enviados en la solicitud para procesarlos
        $data['updated_by'] = $user->id; // Auditoría: Registramos quién está haciendo la actualización
        //CIERRE
        if ($nuevoEstado === 'cerrado' && $ticket->status !== 'cerrado') {
        $data['closed_by_id'] = $user->id; 
        }
        // --- LÓGICA DE ASIGNACIÓN / REASIGNACIÓN ---
    if ($request->has('assigned_to')) { // Si se está enviando un nuevo técnico asignado
    if (!$esAdmin && $request->assigned_to != $user->id) { // Solo admins pueden asignar a otros técnicos, los técnicos solo pueden auto-asignarse
        return response()->json(['message' => 'No autorizado'], 403);
    }

    if ($request->assigned_to != $ticket->assigned_to) { // Solo si el técnico asignado realmente cambió, para evitar notificaciones innecesarias
        $data['assigned_by_id'] = $user->id; // Auditoría: Quién hizo la asignación o reasignación
        
        if ($ticket->status === 'abierto') { // Si el ticket estaba abierto y ahora se asigna, cambiamos su estado a "en_proceso"
            $data['status'] = 'en_proceso';  // Transición automática al ser asignado
        }

        // Notificar al nuevo técnico asignado
        $nuevoTecnico = User::find($request->assigned_to); // Obtenemos el nuevo técnico asignado para enviarle la notificación
        if ($nuevoTecnico) {
            $nuevoTecnico->notify(new SystemNotification(
                '🎫 Ticket Asignado',
                'Se te ha asignado el ticket #' . $ticket->id . ': ' . $ticket->subject,
                '/tickets/' . $ticket->id,
                'bi-person-check-fill'
            ));
        }
    }
    }
        // si se modifico ipacto o urgencia, recalculamos prioridad
        if ($request->has('impacto') || $request->has('urgencia')) {
            $nuevoImpacto = $request->impacto ?? $ticket->impacto;
            $nuevaUrgencia = $request->urgencia ?? $ticket->urgencia;
            $data['priority'] = Ticket::calcularPrioridad($nuevoImpacto, $nuevaUrgencia);
        }

        // CASO A: El Técnico/Admin hizo un cambio -> Avisar al DUEÑO del Ticket
        if ($user->id !== $ticket->user_id) {
            $ticket->user->notify(new SystemNotification(
                'Actualización en Ticket #' . $ticket->id,
                'Nuevo estado: ' . strtoupper(str_replace('_', ' ', $ticket->status)),
                '/tickets/' . $ticket->id,
                'bi-info-circle-fill'
            ));
        }

     // CASO B: El Usuario (Dueño) hizo un cambio -> Avisar al TÉCNICO ASIGNADO
    if ($user->id === $ticket->user_id && $ticket->assigned_to) {  // Solo si el ticket tiene un técnico asignado, para evitar notificar a nadie si aún no se ha tomado el ticket
    $tecnico = \App\Models\User::find($ticket->assigned_to);
    if ($tecnico) {
        $mensaje = "El usuario actualizó el ticket.";
        $titulo = "Actividad en Ticket #" . $ticket->id;
        $icono = "bi-info-circle";
        // 1. REAPERTURA (Rechazo de solución)
        if ($ticket->status === 'en_proceso') { // Antes estaba resuelto
            $mensaje = "El usuario RECHAZÓ la solución. El ticket ha vuelto a 'En Proceso'.";
            $titulo = "🔴 Solución Rechazada";
            $icono = "bi-exclamation-triangle-fill";
        } 
        // 2. CIERRE (Confirmación o Cancelación)
        elseif ($ticket->status === 'cerrado') {  
            // Revisamos las notas de solución que mandó el frontend
            if (str_contains($request->solution_notes, 'Cancelado por el usuario')) {
                $mensaje = "El usuario CANCELÓ la solicitud. Ya no requiere atención.";
                $titulo = "🚫 Ticket Cancelado";
                $icono = "bi-x-octagon-fill";
            } else {
                $mensaje = "El usuario CONFIRMÓ el cierre. ¡Buen trabajo!";
                $titulo = "✅ Cierre Confirmado";
                $icono = "bi-check-circle-fill";
            }
        }
        // Enviamos la notificación al técnico asignado
        $tecnico->notify(new SystemNotification(
            $titulo,
            $mensaje,
            '/tickets/' . $ticket->id,
            $icono
        ));
    }
        }

    //  NOTIFICACIÓN PARA EL COLABORADOR
    // Solo se envía si el campo 'colaborador_id' cambió y no es nulo
    if ($ticket->wasChanged('colaborador_id') && $ticket->colaborador_id) {
    $colaborador = \App\Models\User::find($ticket->colaborador_id);
    if ($colaborador) {
        $colaborador->notify(new SystemNotification(
            '🤝 Nueva Colaboración Asignada',
            $user->name . ' te ha solicitado apoyo en el Ticket #' . $ticket->id . ': ' . $ticket->subject,
            '/tickets/' . $ticket->id,
            'bi-people-fill'
        ));
    }
    }
     $ticket->update($data);// Actualizamos el ticket con los datos procesados
     broadcast(new DashboardUpdate()); // Emitimos el evento para actualizar el dashboard en tiempo real

        return response()->json($ticket->load(['user', 'area', 'assignedUser', 'category', 'editor', 'colaborador']), 200);
    }

    /**
     * 6. Listar solo tickets eliminados (Papelera)
     */
    public function trashed(Request $request)
    {
    if ($request->user()->role !== 'admin') {
        return response()->json(['message' => 'No autorizado'], 403);
    }
    // onlyTrashed() filtra solo los que tienen deleted_at != null
    // Cargamos 'editor' para saber quién lo borró (updated_by)
    $tickets = Ticket::onlyTrashed()
        ->with(['user', 'area', 'editor']) 
        ->orderBy('deleted_at', 'desc')
        ->get();
    broadcast(new DashboardUpdate());  // Emitimos el evento para actualizar el dashboard en tiempo real
    return response()->json($tickets);
    }

    /**
    * 7. Restaurar un ticket eliminado
    */
   public function restore(Request $request, $id)
   {
    $ticket = Ticket::onlyTrashed()->findOrFail($id);
    // Auditoría: Quién lo está restaurando
    $ticket->updated_by = $request->user()->id;
    $ticket->restore();
    broadcast(new DashboardUpdate());
    return response()->json(['message' => 'Ticket restaurado con éxito.']);
}

/**
 * Borrado físico (Permanente)
 */
public function forceDelete(Request $request, $id)
{
    if ($request->user()->role !== 'admin') return response()->json(['message' => 'No autorizado'], 403);
    
    $ticket = Ticket::onlyTrashed()->findOrFail($id); // Solo buscamos entre los eliminados (papelera)
    $ticket->forceDelete(); // Elimina el registro de la base de datos de forma permanente

    return response()->json(['message' => 'Ticket eliminado permanentemente.']);
}
} 