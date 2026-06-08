<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel; // Canal público
use Illuminate\Broadcasting\InteractsWithSockets; // Para manejar conexiones de WebSockets
use Illuminate\Broadcasting\PrivateChannel; // Canal privado para administradores/técnicos
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // Interfaz para eventos que deben ser transmitidos inmediatamente
use Illuminate\Foundation\Events\Dispatchable; // Permite despachar el evento fácilmente
use Illuminate\Queue\SerializesModels; // Permite serializar modelos Eloquent para transmitirlos por WebSockets

class DashboardUpdate implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** 1. Constructor del evento */
    public function __construct()
    {
        // No necesitamos enviar datos, solo la señal de "algo cambió"
    }

    /** 2. Definir el canal de transmisión */
    public function broadcastOn(): array
    {
        // Usaremos un canal privado general para administradores/técnicos
        return [
            new PrivateChannel('dashboard') 
        ];
    }

    /** 3. Definir el nombre del evento */
    public function broadcastAs()
    {
        return 'stats.updated'; // Nombre del evento para escuchar en el frontend
    }
}