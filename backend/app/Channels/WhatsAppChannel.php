<?php

namespace App\Channels;

use Illuminate\Notifications\Notification; // Importamos la clase base de las notificaciones de Laravel
use App\Services\WhatsAppService; //Importamos el servicio que creamos para enviar mensajes por WhatsApp
use Illuminate\Support\Facades\Log; // Importamos el facade de Log para registrar errores

class WhatsAppChannel
{
    /**=======================================
     * Enviar notificación por WhatsApp
     */
    public function send($notifiable, Notification $notification)
    {
        // 1. Verifica si el usuario tiene teléfono guardado
        // (Asegúrate que tu columna en BD sea 'phone', 'celular' o 'telefono')
        if (empty($notifiable->phone)) {
            return;
        }
        // 2. NUEVA VERIFICACIÓN: ¿El usuario quiere recibir WhatsApps?
        // Si whatsapp_active es falso, detenemos todo aquí.
        if ($notifiable->whatsapp_active === false) {
        return;
        }
        // 3. Verifica si la notificación tiene el método 'toWhatsApp'
        if (!method_exists($notification, 'toWhatsApp')) {
            return;
        }
        // 4. Obtiene el texto que definiste en la notificación
        $mensaje = $notification->toWhatsApp($notifiable);

        // 5. Envía el mensaje
        try {
            $whatsAppService = new WhatsAppService();
            $whatsAppService->enviarMensaje($notifiable->phone, $mensaje);
        } catch (\Exception $e) {
            Log::error("Error enviando WhatsApp: " . $e->getMessage());
        }
    }
}