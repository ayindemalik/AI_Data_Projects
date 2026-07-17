<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subcategory\StoreSubcategoryRequest;
use App\Http\Requests\Subcategory\UpdateSubcategoryRequest;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubcategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Subcategory::class);

        $showTrashed = $request->boolean('trashed');

        $subcategories = Subcategory::query()
            ->with('category')
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->searchName($request->string('q')->toString())
            ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
            ->paginate(15)
            ->withQueryString();

        $categories = Category::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        return view('admin.subcategories.index', compact('subcategories', 'showTrashed', 'categories'));
    }

    public function create(): View
    {
        $this->authorize('create', Subcategory::class);

        $categories = Category::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        return view('admin.subcategories.create', compact('categories'));
    }

    public function store(StoreSubcategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Subcategory::class);

        Subcategory::create($request->validated());

        return redirect()->route('admin.subcategories.index')->with('status', 'Subcategory created successfully.');
    }

    public function edit(Subcategory $subcategory): View
    {
        $this->authorize('update', Subcategory::class);

        $categories = Category::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        return view('admin.subcategories.edit', compact('subcategory', 'categories'));
    }

    public function update(UpdateSubcategoryRequest $request, Subcategory $subcategory): RedirectResponse
    {
        $this->authorize('update', Subcategory::class);

        $subcategory->update($request->validated());

        return redirect()->route('admin.subcategories.index')->with('status', 'Subcategory updated successfully.');
    }

    public function destroy(Subcategory $subcategory): RedirectResponse
    {
        $this->authorize('delete', Subcategory::class);

        $subcategory->delete();

        return redirect()->route('admin.subcategories.index')->with('status', 'Subcategory moved to trash.');
    }

    public function restore(int $subcategoryId): RedirectResponse
    {
        $this->authorize('update', Subcategory::class);

        Subcategory::onlyTrashed()->findOrFail($subcategoryId)->restore();

        return redirect()->route('admin.subcategories.index')->with('status', 'Subcategory restored.');
    }

    public function forceDelete(int $subcategoryId): RedirectResponse
    {
        $this->authorize('delete', Subcategory::class);

        Subcategory::onlyTrashed()->findOrFail($subcategoryId)->forceDelete();

        return redirect()->route('admin.subcategories.index')->with('status', 'Subcategory permanently deleted.');
    }
}
