<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_chats', function (Blueprint $table) {
            $table->id();

            // --- RELACIONES ---
            // Si el ticket se borra físicamente (aunque usamos softDelete), los mensajes no tienen sentido sin él.
            $table->unsignedBigInteger('ticket_id');
            // El autor del mensaje.
            $table->unsignedBigInteger('user_id');

            // --- CONTENIDO ---
            $table->text('message')->nullable(); // Puede ser nulo si solo se envía un archivo
            
            // --- ARCHIVOS ADJUNTOS ---
            $table->string('ruta_archivo')->nullable();
            $table->string('nombre_original')->nullable();

            // --- METADATOS ---
            // 'type' ayuda al frontend a renderizar: 'text', 'file', o 'system' (ej: "Ticket asignado")
            $table->enum('type', ['text', 'file', 'system'])->default('text');
            $table->timestamps(); // Vital para el orden cronológico del chat
            $table->softDeletes(); // Para que el técnico pueda "borrar" un mensaje pero quede rastro

            // --- REGLAS DE INTEGRIDAD ---
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            // Mantenemos RESTRICT en user_id para que no se borre el historial si el usuario es inhabilitado
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_chats');
    }
};