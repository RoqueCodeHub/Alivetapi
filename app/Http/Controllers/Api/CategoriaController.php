<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index()
    {
        $categorias = Categoria::orderBy('nombre')->get();

        return response()->json([
            'message' => 'Categorías obtenidas exitosamente',
            'total' => $categorias->count(),
            'categorias' => $categorias,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'      => 'required|string|max:255|unique:categorias,nombre',
            'descripcion' => 'nullable|string',
        ]);

        $categoria = Categoria::create($request->all());

        return response()->json([
            'message'   => 'Categoría creada exitosamente',
            'categoria' => $categoria,
        ], 201);
    }

    public function show($id)
    {
        $categoria = Categoria::withCount('productos')->find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        return response()->json(['categoria' => $categoria]);
    }

    public function update(Request $request, $id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        $request->validate([
            'nombre'      => 'sometimes|string|max:255|unique:categorias,nombre,' . $id,
            'descripcion' => 'nullable|string',
            'activo'      => 'boolean',
        ]);

        $categoria->update($request->all());

        return response()->json([
            'message'   => 'Categoría actualizada exitosamente',
            'categoria' => $categoria,
        ]);
    }

    public function destroy($id)
    {
        $categoria = Categoria::withCount('productos')->find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        if ($categoria->productos_count > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene productos asociados',
            ], 409);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada exitosamente']);
    }
}
