<?php

namespace App\Events;

use App\Models\TicketChat;
use Illuminate\Broadcasting\Channel; // Canal público
use Illuminate\Broadcasting\InteractsWithSockets; // Para manejar conexiones de WebSockets
use Illuminate\Broadcasting\PresenceChannel; // Canal Privado por seguridad
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; // Indica que este evento se transmitirá en tiempo real
use Illuminate\Foundation\Events\Dispatchable; // Permite despachar el evento fácilmente
use Illuminate\Queue\SerializesModels; // Permite serializar modelos Eloquent para transmitirlos por WebSockets

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;// El mensaje del chat que se va a transmitir
    /** 1. Constructor del evento */
    public function __construct(TicketChat $chat)
    {
        // Pasamos el mensaje con la relación del usuario ya cargada
        $this->chat = $chat;
    }

    /**
     * 2. Definimos el canal: "tickets.{id}"
     * Solo los usuarios autorizados en este ticket podrán escucharlo.
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('tickets.' . $this->chat->ticket_id),
        ];
    }

    /**
     * 3. Nombre del evento que escuchará Angular
     */
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }
}