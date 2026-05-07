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
        'resultados',
        'archivo',
    ];

    protected $casts = [
        'resultados' => 'array',
    ];

    public function kits()
    {
        return $this->belongsToMany(Kit::class, 'kit_variante');
    }
}
