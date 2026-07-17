<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChatHistoryController;
use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\Admin\ColorController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentCategoryController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\MeasureController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SeriesController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubcategoryController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:administrator'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('users', UserController::class)->except('show');

        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('chat-history', [ChatHistoryController::class, 'index'])->name('chat-history.index');
        Route::get('chat-history/{chatSession}', [ChatHistoryController::class, 'show'])->name('chat-history.show');

        Route::resource('products', ProductController::class)->except('show');
        Route::post('products/{id}/restore', [ProductController::class, 'restore'])->name('products.restore')->whereNumber('id');
        Route::delete('products/{id}/force-delete', [ProductController::class, 'forceDelete'])->name('products.force-delete')->whereNumber('id');

        foreach (['categories' => CategoryController::class,
                  'subcategories' => SubcategoryController::class,
                  'collections' => CollectionController::class,
                  'series' => SeriesController::class,
                  'colors' => ColorController::class,
                  'measures' => MeasureController::class,
                  'documents' => DocumentController::class,
                  'document-categories' => DocumentCategoryController::class] as $uri => $controller) {

            Route::resource($uri, $controller)->except('show');
            Route::post("{$uri}/{id}/restore", [$controller, 'restore'])->name("{$uri}.restore")->whereNumber('id');
            Route::delete("{$uri}/{id}/force-delete", [$controller, 'forceDelete'])->name("{$uri}.force-delete")->whereNumber('id');
        }
    });
