<?php

namespace App\Models;

// Importaciones para tokens de API, fábricas y notificaciones
use Laravel\Sanctum\HasApiTokens; // Permite generar tokens para Angular (Sanctum)
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // Extiende de Authenticatable para autenticación
use Illuminate\Notifications\Notifiable; // Permite enviar notificaciones
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // Para definir relaciones de uno a muchos
use Illuminate\Database\Eloquent\SoftDeletes; // Lógica para no borrar registros físicamente
use App\Traits\LogsActividades;


class User extends Authenticatable
{

    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, LogsActividades;

    /**
     * Valores predeterminados para atributos del modelo.
     */
    protected $attributes = [
        'role' => 'usuario', // Rol por defecto al crear un usuario
        'estado' => 'pendiente', // Estado por defecto al crear un usuario
    ];

    /**
     * Atributos habilitados para asignación masiva.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',   
        'area_id',    
        'phone',
        'expertise',
        'ci',      
        'cargo',
        'estado',
        'whatsapp_active',
        'created_by',      // Auditoría: Quién registró al usuario
        'approved_by_id',  // Auditoría: Quién validó la cuenta
        'updated_by'       // Auditoría: Último autor de cambios
    ];

    /**
     * Atributos que se omiten en las respuestas JSON hacia el Frontend (Angular).
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casteo (cambiar) de tipos para asegurar integridad en la lógica de negocio.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // Asegura el cifrado automático
        'whatsapp_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // --- RELACIONES DE ESTRUCTURA ---

    /**
     * Relación con el área perteneciente.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    // --- RELACIONES DE OPERATIVIDAD (TICKETS) ---
    /**
     * Tickets creados por este usuario (como solicitante).
     */
    public function ticketsCreated(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id');
    }
    /**
     * Tickets asignados a este usuario para su resolución (como agente de soporte).
     */
    public function ticketsAssigned(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    // --- RELACIONES DE AUDITORÍA ---

    /**
     * Identifica al administrador que creó o registró a este usuario.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Identifica al administrador que aprobó el acceso de este usuario.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
    /**
 * Identifica al último que editór de este perfil.
 */
public function editor(): BelongsTo
{
    return $this->belongsTo(User::class, 'updated_by');
}

}