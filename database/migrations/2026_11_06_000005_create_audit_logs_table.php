<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();   // snapshot, sobrevive à remoção do usuário
            $table->string('action', 30)->index();
            $table->nullableMorphs('subject');
            $table->unsignedBigInteger('case_id')->nullable()->index();
            $table->json('changes')->nullable();       // só nomes dos campos, nunca o conteúdo sigiloso
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
