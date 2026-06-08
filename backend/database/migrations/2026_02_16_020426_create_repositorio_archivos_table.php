<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositorio_archivos', function (Blueprint $table) {
            $table->id();

            // 1. INFORMACIÓN DEL ARCHIVO
            $table->string('nombre_original'); // Ej: "Manual_Impresora_X.pdf"
            $table->text('descripcion')->nullable(); // Descripción detallada
            
            // 2. METADATA TÉCNICA
            $table->string('ruta_archivo');    // Path relativo: "repositorio/xyz.pdf"
            $table->string('extension', 10);   // Ej: "pdf", "docx", "zip" (Más claro que tipo_archivo)
            $table->string('mime_type')->nullable(); // Ej: "application/pdf" (Opcional pero recomendado)
            $table->unsignedBigInteger('peso_bytes'); // Ej: 2048576 (Para cálculos precisos)
            
            // 3. CLASIFICACIÓN Y ACCESO
            // 'publico' = Visible para todos los usuarios logueados
            // 'tecnico' = Solo visible para roles admin y tecnico
            $table->enum('visibilidad', ['publico', 'tecnico'])->default('tecnico');

            // 4. AUDITORÍA Y TRAZABILIDAD
            $table->unsignedBigInteger('created_by')->nullable(); // Quién lo subió
            $table->unsignedBigInteger('updated_by')->nullable(); // Quién editó la metadata
            $table->timestamps();
            $table->softDeletes(); // Papelera de reciclaje

            // 5. RELACIONES (Foreign Keys al final)
            
            // Relación con Categorías (Si se borra la categoría, el archivo queda 'sin categoría' -> set null)
            $table->foreignId('category_id')->nullable()
                  ->constrained('categories')
                  ->onDelete('set null');

            // Relación con Usuarios (Auditoría)
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositorio_archivos');
    }
};