<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Category::class);

        $showTrashed = $request->boolean('trashed');

        $categories = Category::query()
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->searchName($request->string('q')->toString())
            ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
            ->paginate(15)
            ->withQueryString();

        return view('admin.categories.index', compact('categories', 'showTrashed'));
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        Category::create($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Category created successfully.');
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', Category::class);

        return view('admin.categories.edit', compact('category'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', Category::class);

        $category->update($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', Category::class);

        $category->delete(); // Soft delete — row stays in the database, just hidden.

        return redirect()->route('admin.categories.index')->with('status', 'Category moved to trash.');
    }

    public function restore(int $categoryId): RedirectResponse
    {
        $this->authorize('update', Category::class);

        Category::onlyTrashed()->findOrFail($categoryId)->restore();

        return redirect()->route('admin.categories.index')->with('status', 'Category restored.');
    }

    public function forceDelete(int $categoryId): RedirectResponse
    {
        $this->authorize('delete', Category::class);

        Category::onlyTrashed()->findOrFail($categoryId)->forceDelete();

        return redirect()->route('admin.categories.index')->with('status', 'Category permanently deleted.');
    }
}
