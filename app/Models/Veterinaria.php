<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Veterinaria extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'veterinarias';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'veterinaria',
        'responsable',
        'direccion',
        'telefono',
        'email',
        'registro_oficial_veterinario',
        'ciudad',
        'provincia_departamento',
        'pais',
        'logo',
        'usuario',
        'password',
        'acepta_terminos',
        'acepta_tratamiento_datos',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Accessors que se incluirán al serializar el modelo.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'logo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'acepta_terminos' => 'boolean',
            'acepta_tratamiento_datos' => 'boolean',
        ];
    }

    /**
     * Obtener los países válidos para el registro
     */
    public static function getPaisesValidos(): array
    {
        return config('veterinarias.paises_validos', [
            'GUATEMALA',
            'EL SALVADOR',
            'HONDURAS',
            'NICARAGUA',
            'COSTA RICA',
            'PANAMA',
            'BELICE',
        ]);
    }

    /**
     * Scope para filtrar por país
     */
    public function scopePorPais($query, $pais)
    {
        return $query->where('pais', $pais);
    }

    /**
     * Scope para filtrar por ciudad
     */
    public function scopePorCiudad($query, $ciudad)
    {
        return $query->where('ciudad', $ciudad);
    }

    /**
     * Accessor para obtener la URL del logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        // Si es una URL completa, retornar tal cual
        if (filter_var($this->logo, FILTER_VALIDATE_URL)) {
            return $this->logo;
        }

        // Si es un nombre/ruta de archivo almacenado en disk 'public'
        $path = str_starts_with($this->logo, 'logos/') ? $this->logo : ('logos/' . $this->logo);
        return Storage::disk('public')->url($path);
    }

    /**
     * Validar si la URL del logo es válida
     */
    public function isValidLogoUrl(): bool
    {
        if (!$this->logo) {
            return true; // Logo es opcional
        }

        // Válido si es URL remota
        if (filter_var($this->logo, FILTER_VALIDATE_URL)) {
            return true;
        }

        // Válido si existe el archivo en storage público
        $path = str_starts_with($this->logo, 'logos/') ? $this->logo : ('logos/' . $this->logo);
        return Storage::disk('public')->exists($path);
    }
}