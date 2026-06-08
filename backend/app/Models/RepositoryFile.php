<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;// Generación de datos de prueba o sedders
use Illuminate\Database\Eloquent\Model;// Modelo base de Eloquent

class RepositoryFile extends Model
{
    use HasFactory;
    // Atributos que pueden ser asignados mediante métodos masivos como create() o update().
    protected $fillable = [
        'title',
        'description',
        'file_path',
        'file_type',
        'user_id'
    ];

    // Relación con quien lo subió
    public function uploader()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}