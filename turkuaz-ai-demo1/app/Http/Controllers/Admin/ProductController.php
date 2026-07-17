<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Category;
use App\Models\Color;
use App\Models\Measure;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Series;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $showTrashed = $request->boolean('trashed');
        $term = $request->string('q')->toString();

        $products = Product::query()
            ->with(['category', 'subcategory', 'series'])
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($term, function ($q) use ($term) {
                $q->where(function ($inner) use ($term) {
                    $inner->whereRaw("JSON_EXTRACT(name, '$.tr') LIKE ?", ["%{$term}%"])
                          ->orWhereRaw("JSON_EXTRACT(name, '$.en') LIKE ?", ["%{$term}%"])
                          ->orWhere('sku', 'like', "%{$term}%");
                });
            })
            ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
            ->paginate(15)
            ->withQueryString();

        $categories = Category::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        return view('admin.products.index', compact('products', 'showTrashed', 'categories'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('admin.products.create', $this->formOptions());
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validated();

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create([
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'series_id' => $data['series_id'] ?? null,
                'sku' => $data['sku'] ?? null,
                'slug' => $data['slug'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
                'status' => $data['status'],
            ]);

            $this->syncColors($product, $data);
            $this->syncMeasures($product, $data);
            $this->syncVariants($product, $data);
            $this->storeUploadedImages($product, $request);

            return $product;
        });

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product created successfully.');
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', Product::class);

        $product->load(['colors', 'measures', 'variants', 'images']);

        return view('admin.products.edit', array_merge($this->formOptions(), compact('product')));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', Product::class);

        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $product) {
            $product->update([
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'series_id' => $data['series_id'] ?? null,
                'sku' => $data['sku'] ?? null,
                'slug' => $data['slug'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
                'status' => $data['status'],
            ]);

            $this->syncColors($product, $data);
            $this->syncMeasures($product, $data);
            $this->syncVariants($product, $data);
            $this->deleteSelectedImages($product, $data);
            $this->storeUploadedImages($product, $request);
        });

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', Product::class);

        $product->delete(); // Soft delete — images/files are kept until permanent deletion.

        return redirect()->route('admin.products.index')->with('status', 'Product moved to trash.');
    }

    public function restore(int $productId): RedirectResponse
    {
        $this->authorize('update', Product::class);

        Product::onlyTrashed()->findOrFail($productId)->restore();

        return redirect()->route('admin.products.index')->with('status', 'Product restored.');
    }

    public function forceDelete(int $productId): RedirectResponse
    {
        $this->authorize('delete', Product::class);

        $product = Product::onlyTrashed()->with('images')->findOrFail($productId);

        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $product->forceDelete(); // Cascades to product_colors, product_measures, product_variants, product_images rows.

        return redirect()->route('admin.products.index')->with('status', 'Product permanently deleted.');
    }

    /**
     * Dropdown/checkbox data shared by the create and edit forms.
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get(),
            'subcategories' => Subcategory::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get(),
            'seriesList' => Series::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get(),
            'colors' => Color::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get(),
            'measures' => Measure::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get(),
        ];
    }

    private function syncColors(Product $product, array $data): void
    {
        $product->colors()->sync($data['colors'] ?? []);
    }

    private function syncMeasures(Product $product, array $data): void
    {
        // 'measures' comes in as [measure_id => value, ...]; only keep rows with a real value.
        $values = collect($data['measures'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->mapWithKeys(fn ($value, $measureId) => [$measureId => ['value' => $value]]);

        $product->measures()->sync($values->all());
    }

    private function syncVariants(Product $product, array $data): void
    {
        // Simplest correct approach: replace all variant rows with what was submitted.
        $product->variants()->delete();

        $skus = $data['variant_sku'] ?? [];

        foreach ($skus as $index => $sku) {
            if (blank($sku)) {
                continue;
            }

            $product->variants()->create([
                'variant_sku' => $sku,
                'note' => [
                    'tr' => $data['variant_note_tr'][$index] ?? null,
                    'en' => $data['variant_note_en'][$index] ?? null,
                ],
            ]);
        }
    }

    private function storeUploadedImages(Product $product, Request $request): void
    {
        if (!$request->hasFile('images')) {
            return;
        }

        $nextSortOrder = $product->images()->max('sort_order') + 1;

        foreach ($request->file('images') as $file) {
            $path = $file->store("products/{$product->id}", 'public');

            $product->images()->create([
                'path' => $path,
                'sort_order' => $nextSortOrder++,
            ]);
        }
    }

    private function deleteSelectedImages(Product $product, array $data): void
    {
        $imageIds = $data['delete_images'] ?? [];

        if (empty($imageIds)) {
            return;
        }

        $images = ProductImage::whereIn('id', $imageIds)->where('product_id', $product->id)->get();

        foreach ($images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
    }
}
