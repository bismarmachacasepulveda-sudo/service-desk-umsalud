<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // ---  IDENTIFICACIÓN PRINCIPAL ---
            $table->id(); // Identificador único numérico (Llave Primaria)
            $table->string('name'); // Almacena el nombre del usuario
            $table->string('ci')->unique()->nullable(); // Cédula de identidad (Única para evitar duplicados, dato para Informes)
            $table->string('email')->unique(); // Correo electrónico (Único, se usa para el Login)
            $table->timestamp('email_verified_at')->nullable(); // Fecha/Hora de verificación de correo
            $table->string('password'); // Almacena la contraseña encriptada (Hash)

            // --- ROLES Y PERMISOS ---
            $table->enum('role', ['admin', 'tecnico', 'usuario'])->default('usuario'); // Define el nivel de acceso
            $table->string('estado')->default('pendiente'); // Control de flujo: activo, pendiente o rechazado
            $table->string('cargo')->nullable(); // cargo u ocupación (ej: Docente, Administrativo, investigador)

            // --- CONTACTO ---
            $table->string('phone', 20)->nullable(); // Número telefónico de contacto
            $table->boolean('whatsapp_active')->default(true); // Indica si el usuario quiere recibir notificaciones al cel

            // --- DATOS DE SOPORTE TÉCNICO ---
            $table->unsignedBigInteger('area_id')->nullable(); // Vínculo numérico con la tabla de Áreas
            $table->string('expertise')->nullable(); // Especialidad del técnico (ej: Redes, Hardware, Software)

            // --- AUDITORÍA INMUTABLE
            $table->unsignedBigInteger('created_by')->nullable(); // ID del administrador que creó a este usuario
            $table->unsignedBigInteger('approved_by_id')->nullable(); // ID del administrador que aprobó la cuenta
            $table->unsignedBigInteger('updated_by')->nullable(); // ID del último usuario que modificó este registro
            
            // --- METADATOS Y SEGURIDAD ---
            $table->softDeletes(); // Crea 'deleted_at'. Permite "borrar" sin destruir el dato (Auditoría)
            $table->rememberToken(); // Token especial para la opción "Mantener sesión iniciada"
            $table->timestamps(); // Crea 'created_at' (fecha registro) y 'updated_at' (fecha modificación)

            // --- REGLAS DE INTEGRIDAD (Relaciones detalladas) ---
            
            // Si intentan borrar un Área con Usuarios ya asignados, el sistema lo RECHAZA (Restrict)
            //$table->foreign('area_id')->references('id')->on('areas')->onDelete('restrict');
            // Si intentan borrar físicamente a un Admin que aprobó algo, el sistema lo RECHAZA (Restrict)
            // Esto obliga a que la auditoría sea permanente.
            $table->foreign('approved_by_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};