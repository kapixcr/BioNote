<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prueba extends Model
{
    use HasFactory;

    protected $table = 'pruebas';

    protected $fillable = [
        'user_id',
        'fecha',
        'especie',
        'nombre_mascota',
        'sexo',
        'raza',
        'edad',
        'nombre_prueba',
        'result_prueba',
        'titulacion',
        'result_titulacion',
        'fotos',
    ];

    protected $casts = [
        'fecha' => 'date',
        'result_prueba' => 'array',
        'fotos' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}