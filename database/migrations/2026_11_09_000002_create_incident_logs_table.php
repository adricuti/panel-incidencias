<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad de cambios relevantes de cada incidencia (registro de auditoría).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_logs', function (Blueprint $table) {
            $table->id();
            // restrict: la trazabilidad no debe desaparecer en cascada al borrar una incidencia.
            // En la práctica impide borrar incidencias (todas tienen al menos el log de creación),
            // que es lo coherente en un sistema auditado: se cierran, no se borran.
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            // Valores de App\Enums\IncidentLogAction.
            $table->string('action', 40);
            // Valor anterior y nuevo (en cambios de estado, los valores del enum IncidentStatus).
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            // Actor que hizo el cambio (operador de la cabecera o "system:diagnose").
            $table->string('user_name', 100);
            // Datos adicionales sin esquema fijo: motivo del cambio, comentario relacionado, detalle del diagnóstico...
            $table->json('metadata')->nullable();
            // Los logs son inmutables: solo created_at.
            $table->timestamp('created_at')->useCurrent();

            // (incident_id, created_at): historial de una incidencia en orden y búsqueda de su último log de estado.
            // action: consultas del diagnóstico por tipo de acción.
            $table->index(['incident_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_logs');
    }
};
