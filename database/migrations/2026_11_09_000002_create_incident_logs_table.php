<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_logs', function (Blueprint $table) {
            $table->id();
            // restrict: la trazabilidad no debe desaparecer en cascada al borrar una incidencia.
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            $table->string('action', 40);
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->string('user_name', 100);
            $table->json('metadata')->nullable();
            // Los logs son inmutables: solo created_at.
            $table->timestamp('created_at')->useCurrent();

            // Historial de una incidencia en orden y búsqueda del último log de estado.
            $table->index(['incident_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_logs');
    }
};
