<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();

            // 1. PARTICIPANTES
            // El usuario que usará el ambiente (Solicitante)
            $table->unsignedBigInteger('user_id');
            // El administrador que aprobó/rechazó la reserva
            $table->unsignedBigInteger('processed_by_id')->nullable();

            // 2. RECURSO (Ambiente)
            $table->unsignedBigInteger('ambiente_id');

            // 3. TIEMPO
            $table->dateTime('inicio');
            $table->dateTime('fin');

            // 4. DETALLES DE LA ACCIÓN
            $table->string('motivo'); // Ej: "Examen Parcial de Cirugía"
            $table->text('motivo_rechazo')->nullable(); // Solo si el estado es 'rechazada'
            
            // Estados: pendiente, aprobada, rechazada, cancelada (por usuario), finalizada (completada)
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada', 'cancelada', 'finalizada'])
                  ->default('pendiente');

            // 5. AUDITORÍA (Estándar del sistema)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // --- LLAVES FORÁNEAS ---
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ambiente_id')->references('id')->on('ambientes')->onDelete('restrict');
            $table->foreign('processed_by_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};