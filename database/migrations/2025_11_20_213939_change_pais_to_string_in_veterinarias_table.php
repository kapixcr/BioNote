<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cambiar el campo pais de ENUM a VARCHAR
        // Usamos DB::statement porque Laravel no tiene soporte directo para cambiar ENUM a STRING
        DB::statement("ALTER TABLE veterinarias MODIFY COLUMN pais VARCHAR(255) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a ENUM con los valores originales
        DB::statement("ALTER TABLE veterinarias MODIFY COLUMN pais ENUM('GUATEMALA', 'EL SALVADOR', 'NICARAGUA', 'COSTA RICA') NOT NULL");
    }
};
