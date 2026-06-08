<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;// Generación de datos de prueba o sedders
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Eliminacion logica (Papelera)
use App\Traits\LogsActividades;               // Trait de Auditoría Automática
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    use HasFactory, SoftDeletes, LogsActividades;
    /** Atributos que pueden ser asignados mediante métodos masivos como create() o update(). */
    protected $fillable = [
        'name', 
        'description', 
        'active', 
        'tipo', 
        'visibilidad',
        'created_by',  // Auditoría manual
        'updated_by'   // Auditoría manual
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    // --- RELACIONES DE AUDITORÍA ---

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}