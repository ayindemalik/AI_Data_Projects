<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Series;
use App\Models\Subcategory;
use App\Support\JsonText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catalog search: the deterministic counterpart to the assistant.
 *
 * The assistant guesses intent from a sentence and can guess wrong; this page
 * lets someone drive the same catalog directly — type a few letters, narrow
 * with filters, and see exactly what matched and why. Results are the same
 * product cards the chat shows, linking to the same spec sheet.
 *
 * Searching happens over fetch() against query(), so typing never costs a page
 * reload; index() only ships the filter vocabulary.
 */
class SearchController extends Controller
{
    /** Kept small: this is a type-ahead, not a report. */
    private const PER_PAGE = 24;

    public function index(): View
    {
        $categories = Category::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();
        $subcategories = Subcategory::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();
        $productTypes = ProductType::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();
        $series = Series::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        // Colours nobody stocks would only ever produce an empty result set.
        $stocked = Product::query()->whereNotNull('color_id')->distinct()->pluck('color_id');
        $colors = Color::active()->whereIn('id', $stocked)->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        return view('customer.search', [
            'categories' => $categories,
            'subcategories' => $subcategories,
            'productTypes' => $productTypes,
            'series' => $series,
            'colors' => $colors,
            'total' => Product::where('status', 'active')->count(),
        ]);
    }

    /**
     * Live results for the current search box + filter state.
     */
    public function query(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer'],
            'subcategory_id' => ['nullable', 'integer'],
            'product_type_id' => ['nullable', 'integer'],
            'series_id' => ['nullable', 'integer'],
            'color_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string', 'in:name,newest,size'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $term = trim($data['q'] ?? '');

        $query = Product::query()
            ->with(['images', 'series', 'color', 'subcategory'])
            ->where('status', 'active')
            ->when($data['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($data['subcategory_id'] ?? null, fn ($q, $v) => $q->where('subcategory_id', $v))
            ->when($data['product_type_id'] ?? null, fn ($q, $v) => $q->where('product_type_id', $v))
            ->when($data['series_id'] ?? null, fn ($q, $v) => $q->where('series_id', $v))
            ->when($data['color_id'] ?? null, fn ($q, $v) => $q->where('color_id', $v))
            ->when($term !== '', fn ($q) => $this->applyTerm($q, $term));

        $query = match ($data['sort'] ?? 'name') {
            'newest' => $query->orderByDesc('id'),
            'size' => $query->orderBy('dimensions')->orderByRaw("JSON_EXTRACT(name, '$.tr') asc"),
            default => $query->orderByRaw("JSON_EXTRACT(name, '$.tr') asc"),
        };

        $results = $query->paginate(self::PER_PAGE, ['*'], 'page', $data['page'] ?? 1);

        return response()->json([
            'total' => $results->total(),
            'page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
            'products' => collect($results->items())->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name['tr'] ?? $p->name['en'] ?? '',
                'series' => $p->series?->name['tr'] ?? null,
                'subcategory' => $p->subcategory?->name['tr'] ?? null,
                'color' => $p->color?->name['tr'] ?? null,
                'dimensions' => $p->dimensions,
                'code' => $p->sku_new,
                'image' => $p->images->first()?->url,
                'url' => route('products.show', $p->slug),
            ])->values(),
        ]);
    }

    /**
     * Type-ahead suggestions: what the box offers before a full search runs.
     *
     * Series and categories come first because picking one narrows hundreds of
     * rows at once, which a raw keyword never does.
     */
    public function suggest(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $like = '%'.$term.'%';
        $suggestions = [];
        $nameMatches = JsonText::lower('name', 'tr').' LIKE LOWER(?)';

        foreach (Series::active()->whereRaw($nameMatches, [$like])->limit(3)->get() as $s) {
            $suggestions[] = ['type' => 'series', 'label' => $s->name['tr'] ?? '', 'id' => $s->id];
        }

        foreach (Subcategory::active()->whereRaw($nameMatches, [$like])->limit(3)->get() as $s) {
            $suggestions[] = ['type' => 'subcategory', 'label' => $s->name['tr'] ?? '', 'id' => $s->id];
        }

        // Product hits fill whatever room is left, so a specific search still
        // reaches the item itself rather than only its category.
        $room = max(0, 8 - count($suggestions));

        if ($room > 0) {
            $products = Product::query()
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereRaw($nameMatches, [$like])
                    ->orWhereRaw('LOWER(sku_new) LIKE LOWER(?)', [$like]))
                ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
                ->limit($room)
                ->get();

            foreach ($products as $p) {
                $suggestions[] = [
                    'type' => 'product',
                    'label' => $p->name['tr'] ?? '',
                    'code' => $p->sku_new,
                    'url' => route('products.show', $p->slug),
                ];
            }
        }

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     * Free-text matching across the fields a shopper actually types: the name,
     * the printed code, and the size.
     *
     * Each word must match somewhere (AND), so "aqua 60" narrows rather than
     * widening the way an OR over every word would.
     */
    private function applyTerm(Builder $query, string $term): Builder
    {
        $words = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach (array_slice($words, 0, 6) as $word) {
            $like = '%'.$word.'%';

            $query->where(function (Builder $q) use ($like) {
                // LOWER on both sides: MySQL collates JSON extractions
                // utf8mb4_bin, so "lavabo" would match nothing while "Lavabo"
                // matched everything. See App\Support\JsonText.
                $q->whereRaw(JsonText::lower('name', 'tr').' LIKE LOWER(?)', [$like])
                  ->orWhereRaw(JsonText::lower('name', 'en').' LIKE LOWER(?)', [$like])
                  ->orWhereRaw('LOWER(sku_new) LIKE LOWER(?)', [$like])
                  ->orWhereRaw('LOWER(sku) LIKE LOWER(?)', [$like])
                  ->orWhereRaw('LOWER(dimensions) LIKE LOWER(?)', [$like]);
            });
        }

        return $query;
    }
}
