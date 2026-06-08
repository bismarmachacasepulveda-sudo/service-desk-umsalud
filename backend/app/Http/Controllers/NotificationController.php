<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** ===============================================
     * 1. Obtener notificaciones del usuario autenticado.
     *=================================================*/
    public function index(Request $request)
    {
        return response()->json( // Devolvemos las últimas 10 notificaciones del usuario autenticado
            $request->user()->notifications()->limit(10)->get() 
        );
    }

    /**=====================================================
     * 2. Obtener conteo de NO LEÍDAS (para el numerito rojo).
    *======================================================= */
    public function unreadCount(Request $request)
    {
        return response()->json([ // conteo de notificaciones no leídas para mostrar en el frontend
            'count' => $request->user()->unreadNotifications->count()
        ]);
    }

    /**======================================
     * 3. Marcar una notificación como LEÍDA.
    *========================================*/
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first(); //
        if ($notification) { // si la notificación existe y pertenece al usuario autenticado, la marcamos como leída
            $notification->markAsRead();
        }
        return response()->json(['message' => 'Marcada como leída']);
    }

    /**======================================
     * 4. Marcar TODAS como leídas.
     *========================================*/
    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'Todas marcadas como leídas']);
    }
}