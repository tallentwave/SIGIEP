<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('social_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->boolean('birth_date_estimated')->default(false);
            $table->string('sex', 20)->nullable();
            $table->string('cpf', 14)->nullable()->index();
            $table->string('mother_name')->nullable();
            $table->string('school')->nullable();
            $table->string('address')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->boolean('has_disability')->default(false);
            $table->string('disability_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone')->nullable();
            $table->string('cpf', 14)->nullable();
            // Responsável é o suposto autor da violência? Muda o fluxo de contato.
            $table->boolean('is_suspected')->default(false);
            $table->boolean('can_be_contacted')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('children');
    }
};
