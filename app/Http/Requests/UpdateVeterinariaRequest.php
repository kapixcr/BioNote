<?php

namespace App\Http\Requests;

use App\Models\Veterinaria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVeterinariaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $veterinariaId = $this->route('id');

        return [
            'veterinaria' => 'required|string|max:255',
            'responsable' => 'required|string|max:255',
            'direccion' => 'required|string',
            'telefono' => 'required|string|max:20',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('veterinarias', 'email')->ignore($veterinariaId)
            ],
            'registro_oficial_veterinario' => 'required|string|max:255',
            'ciudad' => 'required|string|max:255',
            'provincia_departamento' => 'required|string|max:255',
            'pais' => ['required', Rule::in(Veterinaria::getPaisesValidos())],
            'logo' => 'nullable|url|max:500',
            'usuario' => [
                'required',
                'string',
                'max:255',
                Rule::unique('veterinarias', 'usuario')->ignore($veterinariaId)
            ],
            'password' => 'nullable|string|min:8',
            'repetir_password' => 'nullable|string|min:8|same:password',
            'acepta_terminos' => 'sometimes|boolean',
            'acepta_tratamiento_datos' => 'sometimes|boolean',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'veterinaria.required' => 'El nombre de la veterinaria es obligatorio.',
            'responsable.required' => 'El nombre del responsable es obligatorio.',
            'direccion.required' => 'La dirección es obligatoria.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado.',
            'registro_oficial_veterinario.required' => 'El registro oficial veterinario es obligatorio.',
            'ciudad.required' => 'La ciudad es obligatoria.',
            'provincia_departamento.required' => 'La provincia/departamento es obligatoria.',
            'pais.required' => 'El país es obligatorio.',
            'pais.in' => 'El país seleccionado no es válido.',
            'logo.url' => 'El logo debe ser una URL válida.',
            'logo.max' => 'La URL del logo no debe exceder 500 caracteres.',
            'usuario.required' => 'El usuario es obligatorio.',
            'usuario.unique' => 'Este usuario ya está registrado.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'repetir_password.same' => 'Las contraseñas no coinciden.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'veterinaria' => 'nombre de la veterinaria',
            'responsable' => 'responsable',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
            'email' => 'email',
            'registro_oficial_veterinario' => 'registro oficial veterinario',
            'ciudad' => 'ciudad',
            'provincia_departamento' => 'provincia/departamento',
            'pais' => 'país',
            'logo' => 'logo',
            'usuario' => 'usuario',
            'password' => 'contraseña',
            'repetir_password' => 'repetir contraseña',
        ];
    }
}