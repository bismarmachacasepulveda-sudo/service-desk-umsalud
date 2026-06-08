<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // Generación de datos de prueba o sedders
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; //  Eliminacion logica (Papelera)
use App\Traits\LogsActividades;               // Trait de Auditoría Automática

class Area extends Model
{
    use HasFactory, SoftDeletes, LogsActividades;

    /** Atributos que pueden ser asignados mediante métodos masivos como create() o update(). */
    protected $fillable = [
        'name',
        'description', // Nuevo
        'active',      // Nuevo
        'created_by',  // Auditoría
        'updated_by'   // Auditoría
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    // --- RELACIONES ---

    // Usuarios que pertenecen a esta área
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Tickets generados desde esta área
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    // Auditoría
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}