<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            
            // --- ACTORES DEL TICKET (RELACIONES) ---
            $table->unsignedBigInteger('user_id'); // El afectado (Doctor/Licenciada)
            $table->unsignedBigInteger('area_id'); 
            $table->unsignedBigInteger('category_id')->nullable(); // Clasificación final
            
            // --- ASIGNACIÓN TÉCNICA ---
            $table->unsignedBigInteger('assigned_to')->nullable(); // Técnico Responsable
            $table->unsignedBigInteger('colaborador_id')->nullable(); // TÉCNICO (Apoyo)

            // --- CONTENIDO ---
            $table->string('subject');
            $table->text('description');

            // --- MATRIZ DE PRIORIDAD (ITIL) ---
            $table->enum('impacto', ['individual', 'departamental', 'general'])->nullable();
            $table->enum('urgencia', ['baja', 'media', 'alta'])->nullable();
            $table->enum('priority', ['baja', 'media', 'alta', 'critica'])->default('media');

            // --- ESTADO Y EVIDENCIA ---
            $table->enum('status', ['abierto', 'en_proceso', 'en_espera', 'resuelto', 'cerrado'])->default('abierto');
            $table->string('ruta_archivo')->nullable(); // Evidencia del problema
            $table->string('nombre_archivo')->nullable();

            // --- RESOLUCIÓN ---
            $table->text('solution_notes')->nullable();
            $table->integer('minutes_spent')->nullable();

            // --- SECCIÓN: AUDITORÍA DE PROCESO ---
            $table->unsignedBigInteger('created_by'); // Quién registró el ticket (Admin, Técnico o el propio User)
            $table->unsignedBigInteger('assigned_by_id')->nullable(); // Quién hizo la delegación
            $table->unsignedBigInteger('closed_by_id')->nullable(); // Quién dio el cierre final
            $table->unsignedBigInteger('updated_by')->nullable(); // Última modificación

            $table->timestamps();
            $table->softDeletes();

            // --- REGLAS DE INTEGRIDAD (RESTRICT) ---
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
            
            // Relaciones con la tabla Users para auditoría
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('colaborador_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('assigned_by_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('closed_by_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};