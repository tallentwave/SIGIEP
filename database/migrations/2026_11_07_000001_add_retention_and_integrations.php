<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Retenção e anonimização -------------------------------------------------
        Schema::table('cases', function (Blueprint $table) {
            $table->boolean('legal_hold')->default(false);       // guarda legal: suspende a anonimização
            $table->string('legal_hold_reason')->nullable();
            $table->timestamp('legal_hold_at')->nullable();
            $table->foreignId('legal_hold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anonymized_at')->nullable()->index();
            $table->string('anonymization_reason', 30)->nullable(); // prazo | manual | exclusao
        });

        Schema::table('children', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable();
        });

        Schema::table('municipalities', function (Blueprint $table) {
            // Prazo padrão PROVISÓRIO: deve ser validado pela assessoria jurídica / encarregado (DPO) do município.
            $table->unsignedSmallInteger('retention_years')->default(10);
            // Unidade notificadora nas fichas do SINAN (campos 6, 7 e 7.1 do dicionário).
            $table->unsignedTinyInteger('sinan_unit_type')->default(7); // 1 saúde, 2 assist. social, 3 ensino, 4 CT, 5 indígena, 6 CEAM, 7 outros
            $table->string('sinan_unit_name')->nullable();
            $table->string('sinan_unit_code', 20)->nullable();
        });

        // --- Integrações: SINAN e Sistema de Justiça ----------------------------------
        Schema::create('judicial_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('number', 25);                 // numeração única CNJ, validada pelo dígito verificador
            $table->string('court_unit')->nullable();     // vara / juízo
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['case_id', 'number']);
        });

        Schema::create('integration_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('system', 10)->index();        // sinan | justica
            $table->string('status', 15)->index();        // gerado | concluido | dispensado
            $table->string('batch', 26)->nullable()->index();
            $table->foreignId('judicial_process_id')->nullable()->constrained('judicial_processes')->nullOnDelete();
            $table->string('reference')->nullable();      // NU_NOTIFIC devolvido pelo SINAN ou nº do ofício/protocolo
            $table->text('summary')->nullable();          // síntese ao Juízo (mínimo necessário) ou justificativa; criptografada
            $table->string('payload_hash', 64)->nullable(); // prova do que foi gerado, sem guardar o conteúdo
            $table->date('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_dispatches');
        Schema::dropIfExists('judicial_processes');
        Schema::table('municipalities', fn (Blueprint $t) => $t->dropColumn(['retention_years', 'sinan_unit_type', 'sinan_unit_name', 'sinan_unit_code']));
        Schema::table('children', fn (Blueprint $t) => $t->dropColumn('anonymized_at'));
        Schema::table('cases', function (Blueprint $t) {
            $t->dropConstrainedForeignId('legal_hold_by');
            $t->dropColumn(['legal_hold', 'legal_hold_reason', 'legal_hold_at', 'anonymized_at', 'anonymization_reason']);
        });
    }
};
