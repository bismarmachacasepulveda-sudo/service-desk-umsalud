<?php
php artisan migrate:fresh --seed
PHP
// Definición técnica de integridad referencial en Tickets
Schema::create('tickets', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id'); 
    
    // Aplicación de restricción de integridad para evitar pérdida de datos
    $table->foreign('user_id')
          ->references('id')
          ->on('users')
          ->onDelete('restrict'); 

    $table->softDeletes(); // Implementación de capa de recuperación (Papelera)
    $table->timestamps();  // Trazabilidad temporal automática
});