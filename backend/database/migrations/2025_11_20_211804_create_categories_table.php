<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            
            // Datos Principales
            $table->string('name')->unique(); // Nombres únicos para evitar duplicados
            $table->text('description')->nullable(); // Explicación de uso
            
            // Clasificación y Lógica
            $table->enum('tipo', ['ticket', 'repositorio'])->default('ticket');
            $table->enum('visibilidad', ['publico', 'tecnico'])->default('publico');
            $table->boolean('active')->default(true);
            
            // Auditoría y Trazabilidad
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes(); // Borrado lógico vital

            // Llaves foráneas
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};