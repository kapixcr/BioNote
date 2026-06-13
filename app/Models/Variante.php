<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variante extends Model
{
    use HasFactory;

    protected $table = 'variantes';

    protected $fillable = [
        'nombre',
        'es_titulacion',
        'resultados',
        'archivo',
    ];

    protected $casts = [
        'es_titulacion' => 'boolean',
        'resultados' => 'array',
    ];

    public function kits()
    {
        return $this->belongsToMany(Kit::class, 'kit_variante');
    }
}
