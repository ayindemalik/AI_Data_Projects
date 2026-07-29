<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Color;
use App\Models\Document;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Series;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // ---- Headline counts (catalog + system) ----
        $stats = [
            'products'      => Product::count(),
            'productTypes'  => ProductType::count(),
            'categories'    => Category::count(),
            'subcategories' => Subcategory::count(),
            'series'        => Series::count(),
            'colors'        => Color::count(),
            'documents'     => class_exists(Document::class) ? Document::count() : 0,
            'users'         => User::count(),
        ];

        // ---- Data-quality: products needing attention ----
        // Each value links to the products index with a query flag the index
        // can filter on (see the optional controller filter note in INSTALL).
        $needsImage = Product::whereDoesntHave('images', function ($q) {
            $q->where('path', 'not like', '%placeholder-product%');
        })->count();

        $quality = [
            'no_image'       => $needsImage,
            'missing_kg'     => Product::whereNull('kg')->count(),
            'no_series'      => Product::whereNull('series_id')->count(),
            'no_type'        => Product::whereNull('product_type_id')->count(),
            'no_description' => Product::where(function ($q) {
                $q->whereNull('description')
                  ->orWhereRaw("JSON_EXTRACT(description, '$.tr') IS NULL")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(description, '$.tr')) = ''");
            })->count(),
        ];

        // ---- Products per category (for a chart) ----
        $byCategory = Category::query()
            ->leftJoin('products', 'products.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw("categories.name as name, COUNT(products.id) as total")
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->translate('name') ?? 'N/A',
                'total' => (int) $row->total,
            ]);

        // ---- Products by status (for a chart) ----
        $byStatus = Product::query()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status');

        // ---- Recent products ----
        $recentProducts = Product::with(['category', 'productType'])
            ->latest()
            ->take(8)
            ->get();

        // ---- System (kept from the original dashboard) ----
        $usersByRole = Role::withCount('users')->orderBy('name')->get();
        $recentUsers = User::with('role')->latest()->take(5)->get();

        return view('admin.dashboard', compact(
            'stats', 'quality', 'byCategory', 'byStatus',
            'recentProducts', 'usersByRole', 'recentUsers'
        ));
    }
}
