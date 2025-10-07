<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración del Sistema de Veterinarias
    |--------------------------------------------------------------------------
    |
    | Configuraciones específicas para el sistema de registro y gestión
    | de veterinarias en BioNote.
    |
    */

    'logo' => [
        'max_url_length' => 500, // Caracteres máximos para la URL
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
        'validate_accessibility' => false, // Si validar que la URL sea accesible
        'default_placeholder' => 'https://via.placeholder.com/150x150/cccccc/666666?text=Logo',
    ],

    'paises_validos' => [
        'GUATEMALA',
        'EL SALVADOR',
        'HONDURAS',
        'NICARAGUA',
        'COSTA RICA',
        'PANAMA',
        'BELICE',
    ],

    'validation' => [
        'telefono_regex' => '/^[\+]?[0-9\s\-\(\)]{7,20}$/',
        'password_min_length' => 8,
        'nombre_min_length' => 2,
        'nombre_max_length' => 255,
    ],

    'defaults' => [
        'logo_placeholder' => 'https://via.placeholder.com/150x150/cccccc/666666?text=Veterinaria',
        'pagination_per_page' => 15,
    ],

    'features' => [
        'email_verification' => true,
        'logo_url_support' => true,
        'multi_country_support' => true,
        'terms_acceptance_required' => true,
        'data_processing_acceptance_required' => true,
    ],
];