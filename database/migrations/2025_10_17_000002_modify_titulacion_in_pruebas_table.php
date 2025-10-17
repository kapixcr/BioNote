<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Eliminar columnas antiguas
        Schema::table('pruebas', function (Blueprint $table) {
            $table->dropColumn('result_titulacion');
            $table->dropColumn('titulacion');
        });

        // Crear 'titulacion' como JSON (array)
        Schema::table('pruebas', function (Blueprint $table) {
            $table->json('titulacion')->after('result_prueba');
        });
    }

    public function down(): void
    {
        // Revertir: quitar JSON y restaurar columnas tipo string
        Schema::table('pruebas', function (Blueprint $table) {
            $table->dropColumn('titulacion');
        });

        Schema::table('pruebas', function (Blueprint $table) {
            $table->string('titulacion')->after('result_prueba');
            $table->string('result_titulacion')->after('titulacion');
        });
    }
};