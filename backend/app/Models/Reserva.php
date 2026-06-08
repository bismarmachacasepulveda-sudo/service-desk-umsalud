<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // Generación de datos de prueba o sedders
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Eliminacion logica (Papelera)
use App\Traits\LogsActividades;               // Trait de Auditoría Automática
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reserva extends Model
{
    use HasFactory, SoftDeletes, LogsActividades;

    protected $fillable = [
        'user_id',
        'ambiente_id',
        'processed_by_id', // Quién la aprobó/rechazó
        'inicio',
        'fin',
        'motivo',
        'motivo_rechazo',  // Por qué se rechazó
        'estado',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'inicio' => 'datetime',
        'fin' => 'datetime',
    ];

    // --- RELACIONES ---

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function ambiente() {
        return $this->belongsTo(Ambiente::class);
    }

    // El administrador que procesó la reserva
    public function procesador() {
        return $this->belongsTo(User::class, 'processed_by_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Relación para saber quién la creó originalmente
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}