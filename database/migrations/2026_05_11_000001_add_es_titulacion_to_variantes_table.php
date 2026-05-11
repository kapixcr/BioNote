<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            $table->boolean('es_titulacion')->default(false)->after('nombre');
            $table->json('resultados')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            $table->dropColumn('es_titulacion');
            $table->json('resultados')->nullable(false)->change();
        });
    }
};
