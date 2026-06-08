<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected $baseUrl;

    public function __construct()
    {
        // La dirección del servidor Node.js
        $this->baseUrl = 'http://localhost:3000';
    }
    /**
     * Envia un mensaje a un número de teléfono.
     *
     * @param string $telefono El número del usuario (ej: 70654321 o 59170654321)
     * @param string $mensaje El texto a enviar
     */
    public function enviarMensaje($telefono, $mensaje)
    {
        // 1. Validación básica: si no hay teléfono, no hacemos nada
        if (!$telefono) return;
        // 2. Formatear el número (Importante para WhatsApp)
        // Eliminamos espacios, guiones o símbolos raros
        $numeroLimpio = preg_replace('/[^0-9]/', '', $telefono);

        // OJO: Si tus usuarios guardan el número sin código de país (ej: solo 70654321)
        // y estás en Bolivia, descomenta la siguiente línea para agregar el 591:
        
        // if (strlen($numeroLimpio) <= 8) { $numeroLimpio = '591' . $numeroLimpio; }

        try {
            // 3. Enviar la petición al servidor Node
            Http::post("{$this->baseUrl}/enviar", [
                'telefono' => $numeroLimpio,
                'mensaje' => $mensaje,
            ]);
        } catch (\Exception $e) {
            // Si falla el servidor de Node, no queremos que se rompa Laravel.
            // Solo lo registramos en el log y seguimos.
            \Log::error("Error enviando WhatsApp: " . $e->getMessage());
        }
    }
}