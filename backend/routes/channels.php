<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Ticket;
use App\Models\User;

Broadcast::channel('tickets.{ticketId}', function (User $user, $ticketId) {
    $ticket = Ticket::find($ticketId);

    if (!$ticket) return false;

    // Verificamos acceso
    $tieneAcceso = $user->role === 'admin' || 
                   $user->id === $ticket->user_id || 
                   $user->role === 'tecnico';

    // Si tiene acceso, devolvemos sus datos (esto lo convierte en Presence Channel)
    // Si no tiene acceso, devolvemos false.
    return $tieneAcceso ? ['id' => $user->id, 'name' => $user->name] : false;
});

Broadcast::channel('dashboard', function ($user) {
    return !is_null($user); // O return $user->role === 'admin' si quieres restringirlo
});