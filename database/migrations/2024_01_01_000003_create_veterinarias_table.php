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
        Schema::create('veterinarias', function (Blueprint $table) {
            $table->id();
            $table->string('veterinaria');
            $table->string('responsable');
            $table->text('direccion');
            $table->string('telefono');
            $table->string('email')->unique();
            $table->string('registro_oficial_veterinario');
            $table->string('ciudad');
            $table->string('provincia_departamento');
            $table->enum('pais', ['GUATEMALA', 'EL SALVADOR', 'NICARAGUA', 'COSTA RICA']);
            $table->string('logo')->nullable();
            $table->string('usuario')->unique();
            $table->string('password');
            $table->boolean('acepta_terminos')->default(false);
            $table->boolean('acepta_tratamiento_datos')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veterinarias');
    }
};