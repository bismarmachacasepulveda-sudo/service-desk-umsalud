<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable; // Permite que la notificación se ejecute en segundo plano usando colas
use Illuminate\Contracts\Queue\ShouldQueue; // Interfaz que indica que esta notificación debe ser procesada por una cola
use Illuminate\Notifications\Notification; // Clase base para todas las notificaciones de Laravel
use App\Channels\WhatsAppChannel; // Importamos el canal personalizado que creamos para WhatsApp

class NewMessage extends Notification implements ShouldQueue
{
    use Queueable;
    // 1. PROPIEDADES PARA GUARDAR LOS DATOS NECESARIOS EN LA NOTIFICACIÓN
    protected $message;
    protected $sender;
    protected $ticketId;
    // Constructor para inicializar la notificación con los datos necesarios
    public function __construct($message, $sender, $ticketId)
    {
        $this->message = $message;
        $this->sender = $sender;
        $this->ticketId = $ticketId;
    }

    // 2. DEFINIMOS LOS CANALES POR LOS QUE SE ENVIARÁ LA NOTIFICACIÓN
    public function via($notifiable): array
    {
        // "Envía por Base de Datos Y por WhatsApp"
        return ['database', WhatsAppChannel::class]; 
    }
    // 3. DEFINIMOS EL FORMATO DE LA NOTIFICACIÓN PARA LA BASE DE DATOS
    public function toArray($notifiable)
    {
        return [
            'type' => 'chat',
            'sender_name' => $this->sender->name,
            'message' => $this->message ?? 'Archivo adjunto',
            'ticket_id' => $this->ticketId,
            'link' => '/tickets/' . $this->ticketId
        ];
    }

    // 3. AGREGAMOS EL MÉTODO PARA EL TEXTO DE WHATSAPP
    public function toWhatsApp($notifiable)
    {
        // diseñamos el mensaje
        return "Hola *{$notifiable->name}* 👋,\n\n" .
               "Nuevo mensaje de *{$this->sender->name}* en el ticket #{$this->ticketId}:\n\n" .
               "_{$this->message}_";
    }
}