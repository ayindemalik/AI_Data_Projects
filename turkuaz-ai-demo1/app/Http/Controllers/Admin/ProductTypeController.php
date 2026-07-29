<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductType\StoreProductTypeRequest;
use App\Http\Requests\ProductType\UpdateProductTypeRequest;
use App\Models\ProductType;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductTypeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductType::class);

        $showTrashed = $request->boolean('trashed');

        $productTypes = ProductType::query()
            ->with('subcategory')
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->searchName($request->string('q')->toString())
            ->when($request->filled('subcategory_id'), fn ($q) => $q->where('subcategory_id', $request->integer('subcategory_id')))
            ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
            ->paginate(15)
            ->withQueryString();

        $subcategories = Subcategory::orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        return view('admin.product-types.index', compact('productTypes', 'showTrashed', 'subcategories'));
    }

    public function create(): View
    {
        $this->authorize('create', ProductType::class);

        $subcategories = Subcategory::orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        return view('admin.product-types.create', compact('subcategories'));
    }

    public function store(StoreProductTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', ProductType::class);

        ProductType::create($request->validated());

        return redirect()->route('admin.product-types.index')->with('status', 'Product type created successfully.');
    }

    public function edit(ProductType $productType): View
    {
        $this->authorize('update', ProductType::class);

        $subcategories = Subcategory::orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        return view('admin.product-types.edit', compact('productType', 'subcategories'));
    }

    public function update(UpdateProductTypeRequest $request, ProductType $productType): RedirectResponse
    {
        $this->authorize('update', ProductType::class);

        $productType->update($request->validated());

        return redirect()->route('admin.product-types.index')->with('status', 'Product type updated successfully.');
    }

    public function destroy(ProductType $productType): RedirectResponse
    {
        $this->authorize('delete', ProductType::class);

        $productType->delete();

        return redirect()->route('admin.product-types.index')->with('status', 'Product type moved to trash.');
    }

    public function restore(int $productTypeId): RedirectResponse
    {
        $this->authorize('update', ProductType::class);

        ProductType::onlyTrashed()->findOrFail($productTypeId)->restore();

        return redirect()->route('admin.product-types.index')->with('status', 'Product type restored.');
    }

    public function forceDelete(int $productTypeId): RedirectResponse
    {
        $this->authorize('delete', ProductType::class);

        ProductType::onlyTrashed()->findOrFail($productTypeId)->forceDelete();

        return redirect()->route('admin.product-types.index')->with('status', 'Product type permanently deleted.');
    }
}
