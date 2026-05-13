<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pruebas', function (Blueprint $table) {
            // Eliminar la restricción de clave foránea actual
            $table->dropForeign(['user_id']);
            
            // Hacer user_id nullable (para diagnósticos creados por veterinarias)
            $table->unsignedBigInteger('user_id')->nullable()->change();
            
            // Agregar veterinaria_id (para diagnósticos creados por veterinarias)
            $table->foreignId('veterinaria_id')->nullable()->after('user_id')->constrained('veterinarias')->onDelete('cascade');
            
            // Restaurar la restricción de user_id pero como nullable
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pruebas', function (Blueprint $table) {
            $table->dropForeign(['veterinaria_id']);
            $table->dropColumn('veterinaria_id');
            
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
