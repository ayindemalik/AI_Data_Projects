<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Public spec sheet for one product, opened from a chat answer's product card
 * or from the catalog search page.
 *
 * Product codes are public catalog data and are shown to everyone, guests
 * included — the assistant is told the same thing, so the answer and the page
 * beside it never disagree about what may be said.
 */
class ProductController extends Controller
{
    public function show(Product $product): View
    {
        // A draft or retired product has no business being linkable.
        abort_unless($product->status === 'active', 404);

        $product->load([
            'images', 'category', 'subcategory', 'productType',
            'series', 'color', 'measures', 'variants',
            'documents' => fn ($q) => $q->where('status', 'active'),
        ]);

        return view('customer.product', [
            'product' => $product,
            // images() already sorts the cover first.
            'cover' => $product->images->first(),
            'gallery' => $product->images->skip(1)->values(),
        ]);
    }
}
