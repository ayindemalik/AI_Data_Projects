<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Re-assigns existing product_images rows to the right product.
 *
 * The catalog carries far fewer photos than products, so the same legacy CDN
 * image legitimately serves several SKUs (a base code and its -97/-61 colour
 * variants). This screen moves a row rather than copying it: assign() updates
 * product_images.product_id in place, so an image always leaves the product it
 * came from.
 *
 * Table invariant, set up by catalog:merge-media and maintained here: a product
 * holds EITHER real image rows OR exactly one placeholder row — never both.
 * assign() drops the target's placeholder once it receives a real photo, and
 * gives a stripped source a placeholder back, which keeps the products index
 * "No photo" badge and the dashboard's ?missing=image filter honest.
 */
class ProductImageController extends Controller
{
    /** Path catalog:merge-media writes for a product with no real photo. */
    private const PLACEHOLDER = '/images/placeholder-product.svg';

    public function index(): View
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['images', 'category', 'series'])
            ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
            ->get();

        // Every real row, not just the distinct paths: a photo shared by two
        // products appears twice, once per owner, so each copy can be moved
        // (or left alone) on its own.
        $images = ProductImage::query()
            ->tap($this->realImages(...))
            ->with('product:id,sku,name')
            ->orderBy('path')
            ->orderBy('id')
            ->get();

        // How many real photos each owner holds, so the view can warn when a
        // move would take a product's last one.
        $ownerTotals = $images->groupBy('product_id')->map->count();

        // Same path on more than one product — flagged in the list so a variant
        // pair isn't split up by accident.
        $sharedPaths = $images->groupBy('path')
            ->filter(fn ($rows) => $rows->pluck('product_id')->unique()->count() > 1)
            ->keys()
            ->flip();

        return view('admin.product-images.index', compact('products', 'images', 'ownerTotals', 'sharedPaths'));
    }

    public function assign(Request $request): RedirectResponse
    {
        $this->authorize('update', Product::class);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['integer', 'exists:product_images,id'],
        ], [
            'product_id.required' => 'Pick the product to assign to, on the left.',
            'image_ids.required' => 'Pick at least one image to assign, on the right.',
        ]);

        $result = DB::transaction(function () use ($data) {
            $targetId = (int) $data['product_id'];

            // Placeholder rows are never movable — they are bookkeeping, not photos.
            $images = ProductImage::whereIn('id', $data['image_ids'])
                ->tap($this->realImages(...))
                ->lockForUpdate()
                ->get();

            $sourceIds = $images->pluck('product_id')
                ->unique()
                ->reject(fn ($id) => (int) $id === $targetId);

            $sortOrder = (int) ProductImage::where('product_id', $targetId)
                ->tap($this->realImages(...))
                ->max('sort_order');

            foreach ($images as $image) {
                $image->update(['product_id' => $targetId, 'sort_order' => ++$sortOrder]);
            }

            if ($images->isNotEmpty()) {
                ProductImage::where('product_id', $targetId)
                    ->where('path', self::PLACEHOLDER)
                    ->delete();
            }

            // Restore the placeholder on any source left with nothing at all.
            $emptied = $sourceIds
                ->reject(fn ($id) => ProductImage::where('product_id', $id)->exists())
                ->each(fn ($id) => ProductImage::create([
                    'product_id' => $id,
                    'path' => self::PLACEHOLDER,
                    'sort_order' => 0,
                ]));

            return ['moved' => $images->count(), 'emptied' => $emptied->all()];
        });

        if ($result['moved'] === 0) {
            return back()->withErrors('Nothing was assigned — the selected rows are placeholders, not real photos.');
        }

        $target = Product::find($data['product_id']);
        $status = "Assigned {$result['moved']} image(s) to {$this->label($target)}.";

        if ($result['emptied'] !== []) {
            $emptied = Product::whereIn('id', $result['emptied'])->get()
                ->map(fn ($p) => $this->label($p))->implode(', ');

            $status .= " Left without a photo, now back on the placeholder: {$emptied}.";
        }

        return redirect()->route('admin.product-images.index')->with('status', $status);
    }

    /**
     * Real photos only. Matched the same loose way as ProductController@index
     * so both screens agree on what counts as "has a photo".
     */
    private function realImages(Builder $query): void
    {
        $query->where('path', 'not like', '%placeholder-product%');
    }

    private function label(?Product $product): string
    {
        if (!$product) {
            return 'a deleted product';
        }

        $name = $product->name['tr'] ?? '—';

        return $product->sku ? "{$name} [{$product->sku}]" : $name;
    }
}
