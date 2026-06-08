<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http; // <--- Importante

Route::get('/prueba-whatsapp', function () {
    
    // CAMBIA ESTE NÚMERO POR EL TUYO REAL (Con código de país, ej: 591 para Bolivia)
    // No uses el mismo número que está enviando (el que escaneó el QR), usa otro número o el de un amigo.
    // WhatsApp a veces no muestra notificación si te envías a ti mismo.
    $telefonoDestino = '59172509575'; 
    
    $mensaje = "Hola desde Laravel! 🚀 Tu ticket ha sido actualizado.";

    // Hacemos la petición a nuestro servidor Node.js
    $response = Http::post('http://localhost:3000/enviar', [
        'telefono' => $telefonoDestino,
        'mensaje' => $mensaje,
    ]);

    return $response->json();
});
