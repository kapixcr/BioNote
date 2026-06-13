<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kit extends Model
{
    use HasFactory;

    protected $table = 'kits';

    protected $fillable = [
        'nombre',
        'titulacion',
    ];

    protected $casts = [
        'titulacion' => 'boolean',
    ];

    public function variantes()
    {
        return $this->belongsToMany(Variante::class, 'kit_variante');
    }
}
