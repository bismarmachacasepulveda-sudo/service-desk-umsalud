<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            
            // Información Principal
            $table->string('name')->unique(); // Ej: "Area desconcentrada"
            $table->text('description')->nullable(); // Ej: "Piso 2, al lado de biblioteca"
            
            // Estado (Para ocultar áreas antiguas sin borrarlas)
            $table->boolean('active')->default(true);

            // Auditoría
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes(); // Papelera

            // Relaciones de Auditoría
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
Schema::table('users', function (Blueprint $table) {
        $table->foreign('area_id')->references('id')->on('areas')->onDelete('restrict');
    });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};