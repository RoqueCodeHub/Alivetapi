<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Categoria;
use App\Services\CategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Categoria::class);

        if ($request->boolean('tree') || ! $request->hasAny(['page', 'q', 'parent_id', 'activo', 'per_page', 'flat'])) {
            return ApiResponse::success(
                CategoryResource::collection($this->categories->tree())->resolve(),
                'Categorías'
            );
        }

        $page = $this->categories->paginate($request);

        return ApiResponse::success([
            'items' => CategoryResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
        ], 'Categorías');
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Categoria::class);

        $categoria = $this->categories->create($request->validated());

        return ApiResponse::success(new CategoryResource($categoria), 'Categoría creada', 201);
    }

    public function update(UpdateCategoryRequest $request, Categoria $categoria): JsonResponse
    {
        $this->authorize('update', Categoria::class);

        $updated = $this->categories->update($categoria, $request->validated());

        return ApiResponse::success(new CategoryResource($updated), 'Categoría actualizada');
    }

    public function destroy(Categoria $categoria): JsonResponse
    {
        $this->authorize('delete', $categoria);

        $this->categories->delete($categoria);

        return ApiResponse::success(null, 'Categoría eliminada');
    }
}
