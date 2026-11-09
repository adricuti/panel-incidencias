<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description');
            // Valores controlados por App\Enums\IncidentStatus / IncidentPriority.
            $table->string('status', 20);
            $table->string('priority', 20);
            $table->string('requester_name', 100);
            $table->string('assigned_to', 100)->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();

            // Consultas habituales: filtrar por estado y/o prioridad, ordenar por fecha.
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
