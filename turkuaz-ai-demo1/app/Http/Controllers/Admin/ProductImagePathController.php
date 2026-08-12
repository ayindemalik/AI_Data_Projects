<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductType;
use App\Models\Series;
use App\Models\Subcategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Flat, editable view of every product_images.path in the catalog.
 *
 * One table row per image row, so a product holding four photos appears four
 * times and each path is edited and saved on its own. Products with no image
 * row at all still get a row, with an empty path that store() turns into a
 * new product_images record.
 *
 * A row saves the whole line — the product's own fields as well as the path —
 * so a mis-filed photo and the catalogue data explaining it get fixed in one
 * pass. Saves go over fetch() and return JSON, so editing never costs a page
 * reload or the operator's place in the table.
 *
 * Paths are stored exactly as typed: ProductImage::getUrlAttribute() returns
 * absolute http(s) URLs unchanged and resolves anything else against the
 * 'public' disk, so both legacy CDN URLs and uploaded relative paths are valid.
 */
class ProductImagePathController extends Controller
{
    /** Path catalog:merge-media writes for a product with no real photo. */
    private const PLACEHOLDER = '/images/placeholder-product.svg';

    public function index(): View
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['images', 'category', 'subcategory', 'productType', 'series'])
            ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
            ->get();

        // Flattened here rather than looped in Blade: a product with no images
        // still owes one row, which is awkward to express in the template.
        $rows = collect();

        foreach ($products as $product) {
            $images = $product->images->isEmpty() ? [null] : $product->images->all();

            foreach ($images as $image) {
                $rows->push(['product' => $product, 'image' => $image]);
            }
        }

        $categories = Category::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();
        $subcategories = Subcategory::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();
        $productTypes = ProductType::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();
        $series = Series::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        return view('admin.product-image-paths.index', [
            'rows' => $rows,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'productTypes' => $productTypes,
            'series' => $series,
            // Same lists again as flat arrays. The row selects ship with only
            // their current option and pull the rest from here on first focus —
            // 727 rows x 104 options is far too much markup to render up front.
            'taxonomy' => [
                'category_id' => $categories->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name['tr'] ?? '', 'parent' => null,
                ])->values(),
                'subcategory_id' => $subcategories->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name['tr'] ?? '', 'parent' => $s->category_id,
                ])->values(),
                'product_type_id' => $productTypes->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name['tr'] ?? '', 'parent' => $p->subcategory_id,
                ])->values(),
                'series_id' => $series->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name['tr'] ?? '', 'parent' => null,
                ])->values(),
            ],
        ]);
    }

    /**
     * Save one line: the product's own fields plus this existing image row's path.
     */
    public function update(Request $request, ProductImage $productImage): JsonResponse
    {
        $this->authorize('update', Product::class);

        $product = $productImage->product;

        if (!$product) {
            return response()->json(['message' => 'This image row has no product. Reload the page.'], 422);
        }

        $data = $this->validateLine($request, $product, withProductId: false);
        $path = trim($data['path']);

        // Guard the table invariant the assign screen also maintains: a product
        // holds EITHER real photos OR a lone placeholder, never a mix.
        if ($this->isPlaceholder($path)
            && ProductImage::where('product_id', $productImage->product_id)
                ->whereKeyNot($productImage->id)
                ->exists()) {

            return response()->json([
                'message' => 'This product has other image rows, so this one cannot be turned back '
                    . 'into the placeholder. Delete the row instead.',
            ], 422);
        }

        DB::transaction(function () use ($product, $productImage, $data, $path) {
            $this->applyProductFields($product, $data);
            $productImage->update(['path' => $path]);
        });

        return response()->json($this->payload($productImage->refresh(), $product->refresh(), 'Saved.'));
    }

    /**
     * Save one line for a product that has no image row yet, creating the row.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('update', Product::class);

        $product = Product::find($request->input('product_id'));

        if (!$product) {
            return response()->json(['message' => 'Unknown product. Reload the page.'], 422);
        }

        $data = $this->validateLine($request, $product, withProductId: true);
        $path = trim($data['path']);

        $image = DB::transaction(function () use ($product, $data, $path) {
            $this->applyProductFields($product, $data);

            $image = ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'sort_order' => 0,
            ]);

            // A real photo retires any placeholder the product was still carrying.
            if (!$this->isPlaceholder($path)) {
                ProductImage::where('product_id', $product->id)
                    ->whereKeyNot($image->id)
                    ->where('path', 'like', '%placeholder-product%')
                    ->delete();
            }

            return $image;
        });

        return response()->json($this->payload($image, $product->refresh(), 'Image row created.'), 201);
    }

    /**
     * Drop one image row from its product, and the stored file with it.
     *
     * The product itself is never touched — only its link to this photo. If the
     * row was the product's last, the placeholder goes back so the product keeps
     * showing up as "no photo" rather than disappearing from every image report.
     */
    public function destroy(ProductImage $productImage): JsonResponse
    {
        $this->authorize('delete', Product::class);

        $deletedId = $productImage->id;
        $productId = $productImage->product_id;
        $path = $productImage->path;

        [$fileDeleted, $replacement] = DB::transaction(function () use ($productImage, $productId, $path) {
            $productImage->delete();

            $fileDeleted = $this->deleteFileIfOrphaned($path);

            if (ProductImage::where('product_id', $productId)->exists()) {
                return [$fileDeleted, null];
            }

            return [$fileDeleted, ProductImage::create([
                'product_id' => $productId,
                'path' => self::PLACEHOLDER,
                'sort_order' => 0,
            ])];
        });

        $message = 'Image row deleted.';

        if ($fileDeleted) {
            $message .= ' The stored file was removed too.';
        }
        if ($replacement) {
            $message .= ' That was the product\'s last image, so the placeholder is back.';
        }

        return response()->json([
            'deleted_id' => $deletedId,
            'file_deleted' => $fileDeleted,
            'replacement' => $replacement ? [
                'id' => $replacement->id,
                'path' => $replacement->path,
                'url' => $replacement->url,
                'is_placeholder' => true,
            ] : null,
            'message' => $message,
        ]);
    }

    private function validateLine(Request $request, Product $product, bool $withProductId): array
    {
        return $request->validate([
            'product_id' => $withProductId ? ['required', 'integer', 'exists:products,id'] : ['nullable'],
            'path' => ['required', 'string', 'max:255'],
            // sku is uniquely indexed, so it has to ignore this product's own row.
            'sku' => ['nullable', 'string', 'max:255', "unique:products,sku,{$product->id}"],
            'sku_new' => ['nullable', 'string', 'max:255'],
            'name_tr' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:subcategories,id'],
            'product_type_id' => ['nullable', 'integer', 'exists:product_types,id'],
            'series_id' => ['nullable', 'integer', 'exists:series,id'],
        ], [
            'sku.unique' => 'Another product already uses that SKU.',
            'name_tr.required' => 'The product needs a name.',
        ]);
    }

    private function applyProductFields(Product $product, array $data): void
    {
        // name is a translation map; only the Turkish entry is editable here, so
        // whatever is in 'en' has to survive the write.
        $name = $product->name ?? [];
        $name['tr'] = trim($data['name_tr']);

        $product->update([
            // '' would collide on the unique index the moment a second product
            // is blanked, so an empty SKU has to land as NULL.
            'sku' => $this->nullIfBlank($data['sku'] ?? null),
            'sku_new' => $this->nullIfBlank($data['sku_new'] ?? null),
            'name' => $name,
            'category_id' => $data['category_id'] ?? null,
            'subcategory_id' => $data['subcategory_id'] ?? null,
            'product_type_id' => $data['product_type_id'] ?? null,
            'series_id' => $data['series_id'] ?? null,
        ]);
    }

    /**
     * Remove the backing file, but only when it is really this row's to remove:
     * remote CDN URLs are not ours, the placeholder is shared catalog-wide, and
     * an uploaded file may still be referenced by another product's row.
     */
    private function deleteFileIfOrphaned(string $path): bool
    {
        if ($path === '' || Str::startsWith($path, ['http://', 'https://']) || $this->isPlaceholder($path)) {
            return false;
        }

        if (ProductImage::where('path', $path)->exists()) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Matched the same loose way as ProductController@index so every screen
     * agrees on what counts as "has a photo".
     */
    private function isPlaceholder(string $path): bool
    {
        return str_contains($path, 'placeholder-product');
    }

    /**
     * Everything the row needs to redraw itself — including the resolved
     * taxonomy names, which also feed the table's filter/search values.
     */
    private function payload(ProductImage $image, Product $product, string $message): array
    {
        $product->load(['category', 'subcategory', 'productType', 'series']);

        return [
            'id' => $image->id,
            'path' => $image->path,
            'url' => $image->url,
            'is_placeholder' => $this->isPlaceholder($image->path),
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'sku_new' => $product->sku_new,
                'name_tr' => $product->name['tr'] ?? '',
                'category_id' => $product->category_id,
                'subcategory_id' => $product->subcategory_id,
                'product_type_id' => $product->product_type_id,
                'series_id' => $product->series_id,
                'category' => $product->category?->name['tr'] ?? '',
                'subcategory' => $product->subcategory?->name['tr'] ?? '',
                'product_type' => $product->productType?->name['tr'] ?? '',
                'series' => $product->series?->name['tr'] ?? '',
            ],
            'message' => $message,
        ];
    }
}
