<?php

namespace App\Traits;

use App\Models\Actividades;
use Illuminate\Support\Facades\Auth;

trait LogsActividades
{
    protected static function booted()
    {
        static::created(fn ($model) => $model->logActivity('created'));

        static::updated(function ($model) {
            // 1. Ignorar restauraciones para evitar duplicados
if ($model->isDirty('deleted_at') && $model->deleted_at === null) {
                return;
            }

            //  Detectar Aprobación o Rechazo
            $action = 'updated';
            
            if ($model->isDirty('estado')) {
                if ($model->estado === 'aprobado') {
                    $action = 'approved';
                } elseif ($model->estado === 'rechazado') {
                    $action = 'rejected';
                }
            }

            $model->logActivity($action);
        });

        static::deleted(fn ($model) => $model->logActivity('deleted'));
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(static::class))) {
        static::restored(fn ($model) => $model->logActivity('restored'));
    }
    }

    protected function logActivity(string $action)
    {
        $old = null;
        $new = null;

        switch ($action) {
            case 'created':
                $new = $this->getAttributes();
                break;

            case 'updated':
            case 'approved': // 🟢 Agregamos los nuevos casos
            case 'rejected':
                $old = array_intersect_key($this->getOriginal(), $this->getDirty());
                $new = $this->getDirty();
                break;

            case 'deleted':
                $old = $this->getRawOriginal(); 
                break;

            case 'restored':
                $new = $this->getAttributes();
                break;
        }

        $ignore = [
        'password', 
        'remember_token', 
        'updated_at', 
        'created_at', 
        'deleted_at',
        'updated_by', 
        'created_by'
    ];
        foreach ($ignore as $key) {
            if (isset($old[$key])) unset($old[$key]);
            if (isset($new[$key])) unset($new[$key]);
        }
if ($action === 'updated' && empty($new)) {
        return;
    }
        Actividades::create([
            'user_id' => auth()->id(),
            'auditable_id' => $this->id,
            'auditable_type' => get_class($this),
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}