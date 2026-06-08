<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TicketChat;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewMessage; // Para notificaciones push/sistema
use App\Events\MessageSent; // para WebSockets
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache; // Para manejar presencia y throttling de notificaciones

class ChatController extends Controller
{
    

    /**=============================
     * 1. LISTAR MENSAJES DE UN TICKET
    ===============================*/
    public function index($ticketId)
    {
        $messages = TicketChat::where('ticket_id', $ticketId) // Obtenemos los mensajes del ticket específico
            ->with('user:id,name,role') // con la relación del usuario (solo id, name y role para no exponer datos sensibles)
            ->orderBy('created_at', 'asc') // ordenamos por fecha de creacion
            ->get(); // obtenemos la colección de mensajes
        return response()->json($messages);
    }

    /**==========================================
     * 2. ENVIAR UN MENSAJE A UN TICKET (GUARDAR)
     ===========================================*/
    public function store(Request $request, $ticketId)
    {
        $request->validate([ // Validación de datos de entrada
            'message' => 'nullable|string|max:1000',
            'archivo' => 'nullable|file|max:10240',
        ]);
        $ticket = Ticket::findOrFail($ticketId); // buscar el ticket para validar que existe y también para obtener información de los actores involucrados (dueño, técnico asignado, colaborador)
        $user = Auth::user(); // Usuario autenticado que envía el mensaje
        if (!$request->message && !$request->hasFile('archivo')) { // Si no hay mensaje ni archivo, no guardamos ni notificamos
            return response()->json(['message' => 'Escribe algo o sube un archivo.'], 422);
        }

        // 1. PROCESAR ARCHIVO ADJUNTO
        $ruta = null; // ruta de almacenamiento del archivo en el servidor (null si no se subió ningún archivo)
        $nombre = null; // nombre original del archivo subido por el usuario (null si no se subió ningún archivo)
        if ($request->hasFile('archivo')) { // Si se subió un archivo, lo procesamos
            $file = $request->file('archivo'); // Obtenemos el archivo subido
            $nombre = $file->getClientOriginalName(); // Guardamos el nombre original para mostrarlo en el chat
            $ruta = $file->store('archivos_chat', 'public');// Guardamos el archivo en la carpeta 'archivos_chat' del disco 'public' y obtenemos la ruta de almacenamiento para guardarla en la base de datos
        }

        // 2. CREAR EL REGISTRO
        $chat = TicketChat::create([
            'ticket_id' => $ticketId, // ID del ticket al que pertenece el mensaje
            'user_id' => $user->id, // ID del usuario que envía el mensaje
            'message' => $request->message ?? '', // El mensaje de texto (puede ser null si solo se envió un archivo)
            'ruta_archivo' => $ruta, // La ruta de almacenamiento del archivo (null si no se subió ningún archivo)
            'nombre_original' => $nombre, // El nombre original del archivo subido (null si no se subió ningún archivo)
            'type' => $ruta ? 'file' : 'text' //tipo del mensaje para el frontend
        ]);

        // Cargamos el usuario para que el WebSocket/Response lleve el nombre
        $chat->load('user:id,name,role');

        // 3. DISPARAR EVENTO WEBSOCKET
        broadcast(new MessageSent($chat))->toOthers();

        // 4. LÓGICA DE NOTIFICACIONES (Múltiples Destinatarios)
        $this->notificarActores(Ticket::find($ticketId), $chat, Auth::user());

        return response()->json($chat, 201);
    }

    /**==========================================================
     * Determina quién debe recibir la notificación push/sistema.
     ============================================================*/
    private function notificarActores(Ticket $ticket, TicketChat $chat, User $sender)
    {
        $destinatarios = collect(); // Colección para acumular los usuarios que deben ser notificados

        // Caso A: El usuario dueño del ticket escribe -> Notificar al Técnico y al Colaborador
        if ($sender->id === $ticket->user_id) {
            if ($ticket->assigned_to) $destinatarios->push(User::find($ticket->assigned_to)); // Si hay técnico asignado, lo agregamos a destinatarios
            if ($ticket->colaborador_id) $destinatarios->push(User::find($ticket->colaborador_id)); // Si hay colaborador, lo agregamos a destinatarios
        } 
        // Caso B: Un Técnico o Admin escribe -> Notificar al Dueño del ticket
        else {
            $destinatarios->push($ticket->user);
            
            // Si el que escribe NO es el técnico asignado, notificar también al asignado
            if ($ticket->assigned_to && $sender->id !== $ticket->assigned_to) {
                $destinatarios->push(User::find($ticket->assigned_to)); // Si hay técnico asignado y no es quien escribe, lo agregamos a destinatarios
            }
            
            // Si hay colaborador y no es quien escribe, notificarlo
            if ($ticket->colaborador_id && $sender->id !== $ticket->colaborador_id) {
                $destinatarios->push(User::find($ticket->colaborador_id));
            }
        }

        // Limpiamos nulos y duplicados, luego notificamos
        $mensajeTexto = $chat->message ?: 'Ha enviado un archivo 📎'; // Si el mensaje de texto está vacío, mostramos un texto genérico indicando que se ha enviado un archivo
        foreach ($destinatarios->filter()->unique('id') as $recipient) {
            
            // REGLA A: ¿Está el usuario viendo el chat ahora?
            // Usamos una llave de cache que el WebSocket actualizará
            $estaOnline = Cache::get("user-online-in-ticket-{$recipient->id}-{$ticket->id}", false); // Si el usuario ha enviado un pulso en los últimos 40 segundos indicando que está viendo el ticket, consideramos que está "online" en ese ticket
            if ($estaOnline) {
                continue; // No enviamos notificación si está con la ventana abierta
            }

            $mensajeTexto = $chat->message ?: 'Ha enviado un archivo 📎';
            $recipient->notify(new NewMessage($mensajeTexto, $sender, $ticket->id)); // Enviamos la notificación al destinatario 

            //  REGLA B: Throttling (No saturar la bandeja)
            // Revisamos si ya enviamos una notificación en los últimos 5 minutos
          //  $cacheKey = "last-notif-{$recipient->id}-{$ticket->id}";
          //  $yaNotificadoRecentemete = Cache::has($cacheKey);

          //  if (!$yaNotificadoRecentemete) {
           //     $mensajeTexto = $chat->message ?: 'Ha enviado un archivo 📎';
           //     $recipient->notify(new NewMessage($mensajeTexto, $sender, $ticket->id));

                // Guardamos en cache por 5 minutos para que no se repita
              //  Cache::put($cacheKey, true, now()->addMinutes(5));
           // }
        }
    }

    /**==================================================================
    * Indica al servidor que el usuario está viendo el ticket activamente.
    =====================================================================*/
public function setPresence(Request $request, $ticketId)
{
    $userId = Auth::id();
    // Guardamos en cache que este usuario está viendo este ticket por 40 segundos
    // El frontend enviará este pulso cada 30 segundos.
    Cache::put("user-online-in-ticket-{$userId}-{$ticketId}", true, 40); // Si no recibe otro pulso en 40 segundos, se considerará que el usuario ya no está viendo el ticket
    return response()->json(['status' => 'online']); // Respuesta simple para confirmar que el pulso se recibió
}
}