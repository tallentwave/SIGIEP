<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('uf', 2);
            $table->string('ibge_code', 7)->nullable()->unique();
            $table->string('slug')->unique();            // /denuncia/{slug}
            $table->string('contact_phone')->nullable(); // exibido no formulário público
            $table->boolean('active')->default(true);
            $table->boolean('accepts_public_reports')->default(true);
            $table->timestamps();
        });

        // Fluxo municipal: etapas que viram encaminhamentos ao cadastrar o caso.
        Schema::create('flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('name');
            $table->string('destination', 30);
            $table->string('destination_detail')->nullable(); // unidade/serviço do município
            $table->unsignedSmallInteger('due_days')->default(5); // prazo p/ retorno
            $table->json('violence_types')->nullable();   // null = todos
            $table->boolean('only_urgent')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['municipality_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_steps');
        Schema::dropIfExists('municipalities');
    }
};
