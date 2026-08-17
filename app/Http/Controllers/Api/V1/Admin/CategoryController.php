<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'is_active' => (bool) $category->is_active,
                'sort_order' => (int) $category->sort_order,
                'products_count' => (int) $category->products_count,
            ]);

        return response()->json(['data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $slug = $this->uniqueSlug(Str::slug($validated['name']));
        $config = config("category_specs.{$slug}");

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'icon' => $validated['icon'] ?? ($config['icon'] ?? null),
            'spec_schema' => $config ? ['fields' => $config['fields']] : null,
            'is_active' => true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json(['message' => 'Category created.', 'data' => $category], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $category->update([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'is_active' => $validated['is_active'] ?? $category->is_active,
            'sort_order' => $validated['sort_order'] ?? $category->sort_order,
        ]);

        return response()->json(['message' => 'Category updated.', 'data' => $category->fresh()]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            $category->update(['is_active' => false]);

            return response()->json(['message' => 'Category has products — it was hidden instead of deleted.']);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    private function uniqueSlug(string $slug): string
    {
        $original = $slug ?: 'category';
        $candidate = $original;
        $count = 1;

        while (Category::where('slug', $candidate)->exists()) {
            $candidate = "{$original}-{$count}";
            $count++;
        }

        return $candidate;
    }
}
