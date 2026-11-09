<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla principal de incidencias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description');
            // Valores controlados por App\Enums\IncidentStatus / IncidentPriority.
            // Se usa string y no ENUM de MySQL: añadir un estado no requiere alterar la columna.
            $table->string('status', 20);
            $table->string('priority', 20);
            // Texto libre porque no hay tabla de usuarios (autenticación fuera de alcance).
            // Con usuarios reales pasarían a ser requester_id / assigned_to_id con FK a users.
            $table->string('requester_name', 100);
            $table->string('assigned_to', 100)->nullable();
            // Campo extra (no pedido): permite resaltar incidencias vencidas.
            $table->date('due_date')->nullable();
            $table->timestamps();

            // Índices para las consultas habituales del listado:
            // - (status, priority): filtro por estado, o por estado + prioridad (prefijo izquierdo).
            // - priority: filtro solo por prioridad (el compuesto no sirve si no se filtra por status).
            // - created_at: orden del listado (más recientes primero).
            $table->index(['status', 'priority']);
            $table->index('priority');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
