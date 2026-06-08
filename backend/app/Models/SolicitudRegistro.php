<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; 
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Relación de pertenencia (belongsTo)
use App\Traits\LogsActividades; 

class SolicitudRegistro extends Model
{   use LogsActividades;
    // Conectamos con la tabla
    protected $table = 'solicitudes_registro';
    /** Atributos que pueden ser asignados mediante métodos masivos como create() o update(). */
    protected $fillable = [
        'name', 'email', 'password', 'ci', 'phone', 
        'cargo', 'area_id', 'estado', 'motivo_rechazo',
        'approved_by_id', 'rejected_by_id' // <--- Campos de auditoría añadidos
    ];

    protected $hidden = ['password'];

    /**
     * Relación con el AREA a la que desea pertenecer.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Administrador que APROBO esta solicitud.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /**
     * Administrador que RECHAZO esta solicitud.
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }
}