<?php

namespace App\Models;// Direccion del modelo
use Illuminate\Database\Eloquent\Model; // Importa la clase base de Eloquent

class Actividades extends Model 
{
    // Columnas asociadas al modelo
    protected $fillable = ['user_id', 'auditable_id', 'auditable_type', 'action', 'old_values', 'new_values', 'ip_address', 'user_agent'];
    // Tipos de datos de las columnas
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];
    // Relacion de pertinencia con el modelo User (quién realizó la acción)
    public function user() {
        return $this->belongsTo(User::class);
    }
    // Relación polimórfica para obtener el objeto original (Ticket, Usuario, etc.)
    public function auditable() {
        return $this->morphTo();
    }
}
