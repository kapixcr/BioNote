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
        Schema::table('veterinarias', function (Blueprint $table) {
            // Modificar el campo logo para que sea una URL más larga
            $table->string('logo', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('veterinarias', function (Blueprint $table) {
            // Revertir al tamaño original
            $table->string('logo', 255)->nullable()->change();
        });
    }
};