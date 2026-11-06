<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            // Prazos internos (dias após o recebimento do caso). Confirmar com a vigilância epidemiológica local.
            $table->unsignedSmallInteger('sinan_deadline_sexual_days')->default(1);
            $table->unsignedSmallInteger('sinan_deadline_default_days')->default(7);
            $table->unsignedSmallInteger('queue_alert_days')->default(3); // dias sem responsável até alertar
        });
    }

    public function down(): void
    {
        Schema::table('municipalities', fn (Blueprint $t) => $t->dropColumn(['sinan_deadline_sexual_days', 'sinan_deadline_default_days', 'queue_alert_days']));
    }
};
