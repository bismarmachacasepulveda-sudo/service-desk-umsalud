<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Implementación de eliminación lógica (Papelera)
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketChat extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'ruta_archivo',
        'nombre_original',
        'type' // Importante para distinguir mensajes automáticos
    ];

    /**
     * Relación: El autor del mensaje.
     * Cargamos solo los campos necesarios (name, role) para no enviar datos sensibles por el chat.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'name', 'role']);
    }

    /**
     * Relación: El ticket al que pertenece el mensaje.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}