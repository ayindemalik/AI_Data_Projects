<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Turns a Product model into the plain arrays the mobile API sends.
 *
 * It lives here, not in a controller, because THREE places need the exact same
 * product shape: the chat answer's product cards, the catalog list, and the
 * spec sheet. Keeping one copy means the phone never sees two different ideas
 * of what "a product" looks like.
 *
 * Every text field is already resolved for the current locale (see the
 * ApiLocale middleware), so the app never has to understand the {"tr": ...,
 * "en": ...} JSON columns.
 */
class ProductPresenter
{
    /**
     * The small version: what fits on a card in a chat answer or a search hit.
     */
    public static function card(Product $product): array
    {
        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->translate('name'),
            'code' => $product->sku_new,
            'series' => $product->series?->translate('name'),
            'subcategory' => $product->subcategory?->translate('name'),
            'color' => $product->color?->translate('name'),
            'dimensions' => $product->dimensions,
            'image' => self::absolute($product->images->first()?->url),
        ];
    }

    /**
     * The full version: everything the product screen shows.
     * Expects the relations to be loaded already (see CatalogController::show).
     */
    public static function detail(Product $product): array
    {
        return array_merge(self::card($product), [
            'category' => $product->category?->translate('name'),
            'product_type' => $product->productType?->translate('name'),
            'description' => self::plainText($product->translate('description')),

            // The cover image is first, so the gallery is simply "all of them".
            'images' => $product->images
                ->map(fn ($image) => self::absolute($image->url))
                ->filter()
                ->values()
                ->all(),

            // Width / Height / ... — an open list of rows, not fixed columns,
            // so a new measurement type needs no app update.
            'measures' => $product->measures->map(fn ($measure) => [
                'label' => $measure->translate('name'),
                'value' => trim($measure->pivot->value.' '.$measure->unit),
            ])->values()->all(),

            // Dealer codes for the same product, with the note the admin
            // panel stores beside each one.
            'variants' => $product->variants->map(fn ($variant) => [
                'code' => $variant->variant_sku,
                'note' => $variant->translate('note'),
            ])->values()->all(),

            // PDFs: datasheets, drawings, warranties. The app opens these in
            // the phone's browser rather than trying to render them itself.
            'documents' => $product->documents->map(fn (Document $document) => [
                'type' => $document->type,
                'title' => $document->translate('title') ?: $document->type,
                'url' => self::absolute($document->fileUrl()),
            ])->filter(fn ($row) => $row['url'] !== null)->values()->all(),
        ]);
    }

    /**
     * Make a URL usable from a phone.
     *
     * Legacy catalog images are already absolute CDN links and pass straight
     * through. Uploaded files come back built from APP_URL, which on a dev
     * machine is "http://localhost" — a phone would look for that on itself
     * and find nothing. So anything relative or localhost-flavoured is rebuilt
     * on the host the phone actually called.
     */
    public static function absolute(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = request()->getSchemeAndHttpHost();

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return preg_replace('#^https?://(localhost|127\.0\.0\.1)(:\d+)?#i', $host, $url);
        }

        return rtrim($host, '/').'/'.ltrim($url, '/');
    }

    /**
     * Descriptions were imported from a website, so they still carry HTML.
     * The app shows plain text, so tags are stripped here once instead of in
     * the app.
     */
    private static function plainText(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return null;
        }

        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html));

        return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode($text)));
    }
}
