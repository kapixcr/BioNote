<?php

namespace App\Services;

class FileUploadService
{
    /**
     * Validar URL de logo
     */
    public static function validateLogoUrl(?string $url): array
    {
        $errors = [];
        
        if (!$url) {
            return $errors;
        }

        // Validar que sea una URL válida
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'El logo debe ser una URL válida.';
            return $errors;
        }

        // Validar longitud de la URL
        $maxLength = config('veterinarias.logo.max_url_length', 500);
        if (strlen($url) > $maxLength) {
            $errors[] = "La URL del logo no debe exceder {$maxLength} caracteres.";
        }

        // Validar que la URL apunte a una imagen (opcional, basado en extensión)
        $allowedExtensions = config('veterinarias.logo.allowed_extensions', ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp']);
        $urlPath = parse_url($url, PHP_URL_PATH);
        
        if ($urlPath) {
            $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
            if ($extension && !in_array($extension, $allowedExtensions)) {
                $extensions = implode(', ', $allowedExtensions);
                $errors[] = "La URL debe apuntar a una imagen válida ({$extensions}).";
            }
        }

        return $errors;
    }

    /**
     * Obtener URL del logo (simplemente devuelve la URL tal como está)
     */
    public static function getLogoUrl(?string $url): ?string
    {
        return $url;
    }

    /**
     * Validar que la URL sea accesible (opcional)
     */
    public static function isUrlAccessible(string $url): bool
    {
        try {
            $headers = @get_headers($url, 1);
            return $headers && strpos($headers[0], '200') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtener información de la URL
     */
    public static function getUrlInfo(string $url): array
    {
        $parsedUrl = parse_url($url);
        
        return [
            'url' => $url,
            'domain' => $parsedUrl['host'] ?? null,
            'path' => $parsedUrl['path'] ?? null,
            'extension' => $parsedUrl['path'] ? strtolower(pathinfo($parsedUrl['path'], PATHINFO_EXTENSION)) : null,
            'is_valid' => filter_var($url, FILTER_VALIDATE_URL) !== false,
        ];
    }
}