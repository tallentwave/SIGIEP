<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            // Meta interna: dias entre o recebimento do caso e a 1ª escuta marcada ou realizada.
            $table->unsignedSmallInteger('first_listening_days')->default(7);
        });
    }

    public function down(): void
    {
        Schema::table('municipalities', fn (Blueprint $t) => $t->dropColumn('first_listening_days'));
    }
};
