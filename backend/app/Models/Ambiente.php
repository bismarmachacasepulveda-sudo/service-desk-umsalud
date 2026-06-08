<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; //Implementación de eliminación lógica
use App\Traits\LogsActividades;               // Trait personalizado para la persistencia de logs de auditoría

class Ambiente extends Model
{
    use HasFactory, SoftDeletes, LogsActividades;

    /**Atributos asignables de forma masiva. */
    protected $fillable = [
        'nombre',
        'tipo',
        'capacidad',
        'ubicacion',
        'estado',      // 'activo', 'mantenimiento'
        'descripcion',
        'created_by',  // Auditoría
        'updated_by'   // Auditoría
    ];

    // --- RELACIONES DE AUDITORÍA ---
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}