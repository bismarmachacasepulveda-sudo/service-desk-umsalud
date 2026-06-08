<?php

namespace App\Notifications;
use Illuminate\Bus\Queueable; // Permite que la notificación se ejecute en segundo plano usando colas
use Illuminate\Contracts\Queue\ShouldQueue; // Interfaz que indica que esta notificación debe ser procesada por una cola
use Illuminate\Notifications\Notification; // Clase base para todas las notificaciones de Laravel
use App\Channels\WhatsAppChannel; // Importamos el canal personalizado que creamos para WhatsApp

class SystemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $titulo;
    public $mensaje;
    public $link;
    public $icono; 

    public function __construct($titulo, $mensaje, $link = '#', $icono = 'bi-info-circle')
    {
        $this->titulo = $titulo;
        $this->mensaje = $mensaje;
        $this->link = $link;
        $this->icono = $icono;
    }

    /**
     * Define por qué canales se enviará.
     */
    public function via($notifiable)
    {
        //enviará a la BD (Campanita) Y al WhatsApp
        return ['database', WhatsAppChannel::class]; 
    }

    /**
     * Define qué se guarda en la base de datos (JSON).
     */
    public function toArray($notifiable)
    {
        return [
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'link' => $this->link,
            'icono' => $this->icono,
            'time' => now() 
        ];
    }

    /**
     * 3. DEFINIMOS EL FORMATO PARA WHATSAPP
     * Este método es el que busca el WhatsAppChannel para saber qué texto enviar.
     */
    public function toWhatsApp($notifiable)
    {
        // Convertimos el link relativo (ej: /tickets/1) a absoluto (http://localhost...)
        // para que sea cliqueable en el celular.
        $urlCompleta = ($this->link !== '#') ? url($this->link) : '';

        return "🔔 *Notificación: {$this->titulo}*\n\n" .
               "{$this->mensaje}\n\n" .
               ($urlCompleta ? "🔗 $urlCompleta" : "");
    }
}