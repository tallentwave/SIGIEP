<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listening_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('users');
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('room')->nullable();
            $table->string('status', 20)->default('agendada')->index();
            $table->string('accompanied_by')->nullable();   // quem acompanhou a criança
            // Sigilo: relato e observações técnicas criptografados.
            $table->text('report')->nullable();
            $table->text('technical_notes')->nullable();
            $table->dateTime('performed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['professional_id', 'scheduled_at']);
            $table->index(['room', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listening_sessions');
    }
};
