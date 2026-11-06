<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->unsignedBigInteger('flow_step_id')->nullable()->index();
            $table->string('destination', 30);
            $table->string('destination_detail')->nullable(); // nome da unidade
            $table->string('status', 20)->default('pendente')->index();
            $table->string('document_number')->nullable();
            $table->date('sent_at')->nullable();
            $table->date('due_at')->nullable();              // prazo p/ retorno
            $table->date('returned_at')->nullable();
            $table->text('summary')->nullable();
            $table->text('return_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
