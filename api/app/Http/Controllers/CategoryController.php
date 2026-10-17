<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    /**
     * Flat list of every category; the SPA builds the tree from parent_id.
     */
    public function index()
    {
        return CategoryResource::collection(Category::orderBy('name')->get());
    }

    public function store(SaveCategoryRequest $request): CategoryResource
    {
        $this->authorize('create', Category::class);

        return new CategoryResource(Category::create($request->validated()));
    }

    public function update(SaveCategoryRequest $request, Category $category): CategoryResource
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return new CategoryResource($category);
    }

    /**
     * Products and sub-categories of a deleted category are kept, just uncategorised.
     */
    public function destroy(Category $category): Response
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->noContent();
    }
}
