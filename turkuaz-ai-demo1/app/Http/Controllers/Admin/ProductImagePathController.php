<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductType;
use App\Models\Series;
use App\Models\Subcategory;
use App\Models\Color;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            ->with(['images', 'category', 'subcategory', 'productType', 'series', 'color'])
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
        $colors = Color::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        return view('admin.product-image-paths.index', [
            'rows' => $rows,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'productTypes' => $productTypes,
            'series' => $series,
            'colors' => $colors,
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
                 'color_id' => $colors->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name['tr'] ?? '', 'parent' => null,
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
                // New rows are related by default; the code only takes this one
                // as its cover when nothing else is holding that spot.
                'product_image' => ProductImage::RELATED,
                'sort_order' => 0,
            ]);

            // A real photo retires any placeholder the product was still carrying.
            if (!$this->isPlaceholder($path)) {
                ProductImage::where('product_id', $product->id)
                    ->whereKeyNot($image->id)
                    ->where('path', 'like', '%placeholder-product%')
                    ->delete();
            }

            $this->ensureCover($product->sku_new, $product->id);

            return $image->refresh();
        });

        return response()->json($this->payload($image, $product->refresh(), 'Image row created.'), 201);
    }

    /**
     * Flip one image row between cover and related, on its own.
     *
     * Promoting a row demotes every other image sharing its product code
     * (products.sku_new), so a code always resolves to exactly one cover — that
     * grouping, rather than product_id, because one code can span several
     * product rows (colour and size variants) yet still wants one cover photo.
     */
    public function cover(Request $request, ProductImage $productImage): JsonResponse
    {
        $this->authorize('update', Product::class);

        $data = $request->validate([
            'product_image' => ['required', 'string', 'in:'.ProductImage::MAIN.','.ProductImage::RELATED],
        ]);

        $role = $data['product_image'];

        $demoted = DB::transaction(function () use ($productImage, $role) {
            $productImage->update(['product_image' => $role]);

            if ($role !== ProductImage::MAIN) {
                return collect();
            }

            $siblings = $this->siblingIds($productImage);

            if ($siblings->isNotEmpty()) {
                ProductImage::whereIn('id', $siblings)->update(['product_image' => ProductImage::RELATED]);
            }

            return $siblings;
        });

        return response()->json([
            'id' => $productImage->id,
            'product_image' => $role,
            // The table demotes these rows in place instead of reloading.
            'demoted' => $demoted->values(),
            'message' => $role === ProductImage::MAIN
                ? 'Set as cover.'.($demoted->isNotEmpty()
                    ? ' '.$demoted->count().' other image'.($demoted->count() === 1 ? '' : 's')
                        .' for this code set to related.'
                    : '')
                : 'Set as related.',
        ]);
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
        $code = $productImage->product?->sku_new;

        [$fileDeleted, $replacement, $promoted] = DB::transaction(function () use ($productImage, $productId, $path, $code) {
            $productImage->delete();

            $fileDeleted = $this->deleteFileIfOrphaned($path);

            $replacement = ProductImage::where('product_id', $productId)->exists()
                ? null
                : ProductImage::create([
                    'product_id' => $productId,
                    'path' => self::PLACEHOLDER,
                    'product_image' => ProductImage::RELATED,
                    'sort_order' => 0,
                ]);

            // Deleting the cover would otherwise leave the code with none. This
            // can promote the placeholder just created, so re-read it after.
            $promoted = $this->ensureCover($code, $productId);

            return [$fileDeleted, $replacement?->refresh(), $promoted];
        });

        $message = 'Image row deleted.';

        if ($fileDeleted) {
            $message .= ' The stored file was removed too.';
        }
        if ($replacement) {
            $message .= ' That was the product\'s last image, so the placeholder is back.';
        }
        if ($promoted && $promoted->id !== $replacement?->id) {
            $message .= ' That was the cover, so image #'.$promoted->id.' took over.';
        }

        return response()->json([
            'deleted_id' => $deletedId,
            'file_deleted' => $fileDeleted,
            'replacement' => $replacement ? [
                'id' => $replacement->id,
                'path' => $replacement->path,
                'url' => $replacement->url,
                'is_placeholder' => true,
                'product_image' => $replacement->fresh()->product_image,
            ] : null,
            // The table flips this row's dropdown to "cover" without a reload.
            'promoted' => $promoted?->id,
            'message' => $message,
        ]);
    }

    /** Every other image row filed under the same product code. */
    private function siblingIds(ProductImage $productImage): Collection
    {
        return $this->codeGroup($productImage->product?->sku_new, $productImage->product_id)
            ->whereKeyNot($productImage->id)
            ->pluck('id');
    }

    /**
     * All image rows sharing one product code.
     *
     * A blank code cannot group anything — matching on it would sweep in every
     * uncoded product — so those rows fall back to their own product.
     */
    private function codeGroup(?string $code, ?int $productId): Builder
    {
        $code = $this->nullIfBlank($code);

        return $code === null
            ? ProductImage::query()->where('product_id', $productId)
            : ProductImage::query()->whereIn('product_id', Product::where('sku_new', $code)->select('id'));
    }

    /**
     * Leave the code group with a cover, promoting its oldest row when the one
     * it had was just deleted, or when it never had one (a brand-new product's
     * first photo). Returns the row promoted, if any.
     */
    private function ensureCover(?string $code, ?int $productId): ?ProductImage
    {
        if ($this->codeGroup($code, $productId)->where('product_image', ProductImage::MAIN)->exists()) {
            return null;
        }

        $candidate = $this->codeGroup($code, $productId)->orderBy('id')->first();

        $candidate?->update(['product_image' => ProductImage::MAIN]);

        return $candidate;
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
            'color_id' => ['nullable', 'integer', 'exists:colors,id'],
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
            'color_id' => $data['color_id'] ?? null,
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
        $product->load(['category', 'subcategory', 'productType', 'series', 'color']);

        return [
            'id' => $image->id,
            'path' => $image->path,
            'url' => $image->url,
            'is_placeholder' => $this->isPlaceholder($image->path),
            'product_image' => $image->product_image,
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'sku_new' => $product->sku_new,
                'name_tr' => $product->name['tr'] ?? '',
                'category_id' => $product->category_id,
                'subcategory_id' => $product->subcategory_id,
                'product_type_id' => $product->product_type_id,
                'series_id' => $product->series_id,
                'color_id' => $product->color_id,
                'category' => $product->category?->name['tr'] ?? '',
                'subcategory' => $product->subcategory?->name['tr'] ?? '',
                'product_type' => $product->productType?->name['tr'] ?? '',
                'series' => $product->series?->name['tr'] ?? '',
                'color' => $product->color?->name['tr'] ?? '',
            ],
            'message' => $message,
        ];
    }
}
