<?php

namespace App\Http\Controllers;

use App\Models\Variante;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class VarianteController extends Controller
{
    public function index(): JsonResponse
    {
        $variantes = Variante::orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $variantes,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $variante = Variante::find($id);

        if (!$variante) {
            return response()->json([
                'success' => false,
                'message' => 'Variante no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $variante,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'nombre' => ['required', 'string', 'max:255'],
            'resultados' => ['required', 'array'],
            'resultados.*' => ['string', 'in:positivo,negativo,invalido'],
        ];

        if ($request->hasFile('archivo')) {
            $rules['archivo'] = ['file', 'mimes:pdf', 'max:10240'];
        } else {
            $rules['archivo'] = ['nullable', 'string', 'url', 'max:2048'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');

            if ($file->isValid()) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $destinationPath = storage_path('app/public/variantes');

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                $fullPath = $destinationPath . DIRECTORY_SEPARATOR . $filename;

                if (move_uploaded_file($file->getPathname(), $fullPath)) {
                    $data['archivo'] = Storage::disk('public')->url('variantes/' . $filename);
                }
            }
        }

        $variante = Variante::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Variante creada exitosamente',
            'data' => $variante,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $variante = Variante::find($id);

        if (!$variante) {
            return response()->json([
                'success' => false,
                'message' => 'Variante no encontrada',
            ], 404);
        }

        $rules = [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'resultados' => ['sometimes', 'array'],
            'resultados.*' => ['string', 'in:positivo,negativo,invalido'],
        ];

        if ($request->hasFile('archivo')) {
            $rules['archivo'] = ['file', 'mimes:pdf', 'max:10240'];
        } else {
            $rules['archivo'] = ['nullable', 'string', 'url', 'max:2048'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');

            if ($file->isValid()) {
                if ($variante->archivo) {
                    $oldPath = str_replace(Storage::disk('public')->url(''), '', $variante->archivo);
                    Storage::disk('public')->delete($oldPath);
                }

                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $destinationPath = storage_path('app/public/variantes');

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                $fullPath = $destinationPath . DIRECTORY_SEPARATOR . $filename;

                if (move_uploaded_file($file->getPathname(), $fullPath)) {
                    $data['archivo'] = Storage::disk('public')->url('variantes/' . $filename);
                }
            }
        }

        $variante->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Variante actualizada exitosamente',
            'data' => $variante,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $variante = Variante::find($id);

        if (!$variante) {
            return response()->json([
                'success' => false,
                'message' => 'Variante no encontrada',
            ], 404);
        }

        if ($variante->archivo) {
            $oldPath = str_replace(Storage::disk('public')->url(''), '', $variante->archivo);
            Storage::disk('public')->delete($oldPath);
        }

        $variante->delete();

        return response()->json([
            'success' => true,
            'message' => 'Variante eliminada exitosamente',
        ]);
    }
}
