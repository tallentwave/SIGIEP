<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('protocol', 20)->unique();
            $table->foreignId('child_id')->constrained();
            $table->string('condition', 20);              // vitima | testemunha
            $table->json('violence_types');
            $table->string('status', 30)->default('recebido')->index();
            $table->string('origin');                      // órgão que demandou
            $table->string('origin_document')->nullable(); // nº ofício / BO / processo
            $table->date('received_at');
            $table->date('violence_date')->nullable();
            $table->string('alleged_author')->nullable();
            $table->boolean('urgent')->default(false);
            // Relato inicial do órgão demandante. Dado sensível: criptografado.
            $table->text('initial_report')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Equipe com acesso ao caso (além da coordenação, que vê todos).
        Schema::create('case_user', function (Blueprint $table) {
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['case_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_user');
        Schema::dropIfExists('cases');
    }
};
