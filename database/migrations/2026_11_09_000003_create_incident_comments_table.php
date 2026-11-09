<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comentarios operativos de seguimiento de una incidencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_comments', function (Blueprint $table) {
            $table->id();
            // cascade (a diferencia de incident_logs): un comentario no tiene sentido sin su incidencia
            // y no es información de auditoría. Su creación ya queda registrada en incident_logs.
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            // Operador de la cabecera (texto libre, sin autenticación).
            $table->string('author_name', 100);
            $table->text('body');
            // Con updated_at: a diferencia de los logs, un comentario podría editarse en el futuro.
            $table->timestamps();

            // Comentarios de una incidencia ordenados por fecha.
            $table->index(['incident_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_comments');
    }
};
