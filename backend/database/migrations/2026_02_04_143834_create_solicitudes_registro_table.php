<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
Schema::create('solicitudes_registro', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->string('password');
    $table->string('ci')->nullable();
    $table->string('phone')->nullable();
    $table->string('cargo')->nullable();
    $table->unsignedBigInteger('area_id');

    // --- ESTADOS Y AUDITORÍA ---
    $table->enum('estado', ['pendiente', 'aprobado', 'rechazado'])->default('pendiente');
    $table->text('motivo_rechazo')->nullable(); 
    
    $table->unsignedBigInteger('approved_by_id')->nullable(); // Admin que aceptó
    $table->unsignedBigInteger('rejected_by_id')->nullable(); // Admin que rechazó
    
    $table->timestamps(); // Registra fecha de solicitud y fecha de última acción

    // Relaciones
// --- INTEGRIDAD REFERENCIAL BLINDADA ---

// Si intentan borrar un Área con solicitudes, el sistema lo RECHAZA (Restrict)
$table->foreign('area_id')->references('id')->on('areas')->onDelete('restrict');
// Si intentan borrar físicamente a un Admin que aprobó algo, el sistema lo RECHAZA (Restrict)
// Esto obliga a que la auditoría sea permanente.
$table->foreign('approved_by_id')->references('id')->on('users')->onDelete('restrict');
$table->foreign('rejected_by_id')->references('id')->on('users')->onDelete('restrict');
});
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_registro');
    }
};
