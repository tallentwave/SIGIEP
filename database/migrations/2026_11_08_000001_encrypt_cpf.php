<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CPF passa a ser criptografado (texto longo) e a busca usa um índice cego (HMAC), sem expor o número.
        Schema::table('children', function (Blueprint $table) {
            $table->text('cpf')->nullable()->change();
            $table->string('cpf_hash', 64)->nullable()->index();
        });
        Schema::table('guardians', function (Blueprint $table) {
            $table->text('cpf')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn('cpf_hash');
        });
    }
};
