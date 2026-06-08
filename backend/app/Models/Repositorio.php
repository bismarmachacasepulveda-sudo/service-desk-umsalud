<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // Generación de datos de prueba o sedders   
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Eliminacion logica (Papelera)
use App\Traits\LogsActividades;               // Trait de Auditoría Automática

class Repositorio extends Model
{
    use HasFactory, SoftDeletes, LogsActividades;

    // Conectamos con la tabla
    protected $table = 'repositorio_archivos'; 
    /** Atributos que pueden ser asignados mediante métodos masivos como create() o update(). */
    protected $fillable = [
        'created_by',     
        'updated_by',
        'category_id',
        'nombre_original',
        'ruta_archivo',
        'descripcion',
        'extension',      
        'mime_type',      
        'peso_bytes',    
        'visibilidad'
    ];

    // Convertir bytes en unidades legibles (KB/MB)
    protected $appends = ['peso_legible'];

    public function getPesoLegibleAttribute()
    {
        $bytes = $this->peso_bytes;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    // --- RELACIONES ---

    public function categoria()
    {
        return $this->belongsTo(Category::class, 'category_id')->withTrashed(); // Para que no falle si la categoría está borrada
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}