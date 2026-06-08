<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambientes', function (Blueprint $table) {
            $table->id();

            // Identificación
            $table->string('nombre'); // Ej: "Aula 101" (Único para evitar confusiones)
            $table->string('tipo');             // Ej: "Aula", "Laboratorio", "Auditorio"
            $table->text('descripcion')->nullable(); // Ej: equipado con "Proyector HDMI, Aire Acondicionado"
            
            // Capacidad y Ubicación
            $table->unsignedInteger('capacidad'); // Solo números positivos
            $table->string('ubicacion')->nullable(); // Ej: "Bloque C, Planta Baja"

            // Lógica de Negocio
            // 'activo': Disponible para reservar
            // 'mantenimiento': Bloqueado temporalmente (pero visible en administración)
            $table->enum('estado', ['activo', 'mantenimiento'])->default('activo');

            // Auditoría y Trazabilidad
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes(); 

            // Relaciones
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambientes');
    }
};