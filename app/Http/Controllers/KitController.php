<?php

namespace App\Http\Controllers;

use App\Models\Kit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class KitController extends Controller
{
    public function index(): JsonResponse
    {
        $kits = Kit::with('variantes')->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $kits,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $kit = Kit::with('variantes')->find($id);

        if (!$kit) {
            return response()->json([
                'success' => false,
                'message' => 'Kit no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $kit,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:255'],
            'titulacion' => ['required', 'boolean'],
            'variante_ids' => ['required', 'array', 'min:1'],
            'variante_ids.*' => ['integer', 'exists:variantes,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $kit = Kit::create([
            'nombre' => $data['nombre'],
            'titulacion' => $data['titulacion'],
        ]);

        $kit->variantes()->attach($data['variante_ids']);

        $kit->load('variantes');

        return response()->json([
            'success' => true,
            'message' => 'Kit creado exitosamente',
            'data' => $kit,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $kit = Kit::find($id);

        if (!$kit) {
            return response()->json([
                'success' => false,
                'message' => 'Kit no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'titulacion' => ['sometimes', 'boolean'],
            'variante_ids' => ['sometimes', 'array', 'min:1'],
            'variante_ids.*' => ['integer', 'exists:variantes,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $kit->update([
            'nombre' => $data['nombre'] ?? $kit->nombre,
            'titulacion' => $data['titulacion'] ?? $kit->titulacion,
        ]);

        if (isset($data['variante_ids'])) {
            $kit->variantes()->sync($data['variante_ids']);
        }

        $kit->load('variantes');

        return response()->json([
            'success' => true,
            'message' => 'Kit actualizado exitosamente',
            'data' => $kit,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $kit = Kit::find($id);

        if (!$kit) {
            return response()->json([
                'success' => false,
                'message' => 'Kit no encontrado',
            ], 404);
        }

        $kit->variantes()->detach();
        $kit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kit eliminado exitosamente',
        ]);
    }
}
