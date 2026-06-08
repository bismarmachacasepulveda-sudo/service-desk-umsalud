<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Implementación de eliminación lógica (Papelera)
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\LogsActividades;

class Ticket extends Model
{
    use HasFactory, SoftDeletes, LogsActividades; 
    /** Atributos que pueden ser asignados mediante métodos masivos como create() o update(). */
    protected $fillable = [
        'area_id',
        'user_id',
        'assigned_to',
        'colaborador_id', //
        'subject',
        'description',
        'priority',
        'status',
        'category_id',
        'solution_notes',
        'minutes_spent',
        'ruta_archivo',
        'nombre_archivo',
        'impacto',
        'urgencia',
        'created_by',     //Auditoría
        'assigned_by_id', // Auditoría
        'closed_by_id',   // Auditoría
        'updated_by'      // Auditoría
    ];

    protected $casts = [
        'priority' => 'string',
        'status' => 'string',
        'minutes_spent' => 'integer',
    ];

    // --- RELACIONES---

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * El usuario afectado (quien tiene el problema).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * El técnico responsable principal.
*/
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * El técnico secundario o de apoyo.
     */
    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'colaborador_id');
    }

    // --- RELACIONES DE AUDITORÍA (Trazabilidad) ---

    /**
     * Quién registró físicamente el ticket en el sistema.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * El administrador que realizó la asignación del técnico.
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    /**
     * Quién dio el cierre definitivo al ticket.
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    /**
     * Quién realizó la última modificación en el registro.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function updated_by()
    {
    return $this->belongsTo(User::class, 'updated_by');
   }


    /**
     *  MATRIZ ITIL: Calcula la prioridad basada en Impacto y Urgencia.
     */
    public static function calcularPrioridad($impacto, $urgencia)
    {
        $matriz = [
            'individual' => [
                'baja'  => 'baja',
                'media' => 'media',
                'alta'  => 'media'
            ],
            'departamental' => [
                'baja'  => 'media',
                'media' => 'alta',
                'alta'  => 'alta'
            ],
            'general' => [
                'baja'  => 'alta',
                'media' => 'critica',
                'alta'  => 'critica'
            ]
        ];

        return $matriz[$impacto][$urgencia] ?? 'media';
    }
}