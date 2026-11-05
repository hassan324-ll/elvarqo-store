<?php

use App\Http\Controllers\CatalogueController as C;
use App\Http\Controllers\CatalogueImportController;
use App\Http\Controllers\CatalogueSitemapController;
use App\Http\Controllers\PublicCatalogueController as P;
use App\Http\Controllers\StockController as S;
use App\Http\Middleware\StaffOrigin;
use App\Http\Middleware\StaffPermission;
use App\Http\Middleware\StaffSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/catalogue')->group(function () {
    Route::get('sitemap', CatalogueSitemapController::class)->middleware('throttle:catalogue-read');
    Route::get('saved-products', [P::class, 'saved'])->middleware('throttle:catalogue-read');
    Route::get('products', [P::class, 'index'])->middleware('throttle:catalogue-read');
    Route::get('products/{slug}', [P::class, 'show'])->middleware('throttle:catalogue-read');
    Route::get('category-images/{id}/{image}', [P::class, 'categoryImage'])->whereNumber('id')->whereUuid('image')->middleware('throttle:catalogue-media');
    Route::get('navigation', [P::class, 'navigation'])->middleware('throttle:catalogue-read');
    Route::get('collections', [P::class, 'collections'])->middleware('throttle:catalogue-read');
    Route::get('media/{media}/{size}', [P::class, 'image'])->whereUuid('media')->whereIn('size', ['640', '1280', '1920'])->middleware('throttle:catalogue-media');
});
Route::prefix('v1/staff/catalogue')->middleware(['web', StaffOrigin::class, StaffSession::class, StaffPermission::class.':catalogue.view', 'throttle:240,1'])->group(function () {
    Route::get('products', [C::class, 'index']);
    Route::get('products/{id}', [C::class, 'show'])->whereNumber('id');
    Route::get('collections', [C::class, 'collections']);
    Route::get('category-images/{id}/{image}', [C::class, 'categoryImage'])->whereNumber('id')->whereUuid('image');
    Route::get('collections/{id}/products', [C::class, 'collectionProducts'])->whereNumber('id');
    Route::get('media/{media}/{size}', [C::class, 'image'])->whereUuid('media')->whereIn('size', ['640', '1280', '1920']);
    Route::get('locations', [S::class, 'locations']);
    Route::get('inventory', [S::class, 'inventory']);
    Route::get('movements', [S::class, 'history']);
    Route::middleware(StaffPermission::class.':catalogue.manage')->group(function () {
        Route::get('imports/template', [CatalogueImportController::class, 'template']);
        Route::get('imports', [CatalogueImportController::class, 'history']);
        Route::post('imports/preview', [CatalogueImportController::class, 'preview']);
        Route::get('imports/{batch}/items/{item}/images/{index}', [CatalogueImportController::class, 'image'])->whereUuid('batch')->whereNumber('item')->whereNumber('index');
        Route::get('imports/{batch}', [CatalogueImportController::class, 'show'])->whereUuid('batch');
        Route::post('imports/{batch}/items/{item}', [CatalogueImportController::class, 'run'])->whereUuid('batch')->whereNumber('item');
        Route::get('products/{id}/supplier', [CatalogueImportController::class, 'supplier'])->whereNumber('id');
        Route::post('products', [C::class, 'create']);
        Route::post('products/{id}', [C::class, 'update'])->whereNumber('id');
        Route::post('products/{id}/delete', [C::class, 'destroy'])->whereNumber('id');
        Route::post('products/{id}/{action}', [C::class, 'transition'])->whereNumber('id')->whereIn('action', ['publish', 'unpublish', 'archive']);
        Route::post('collections', [C::class, 'saveCollection']);
        Route::post('collections/{id}', [C::class, 'saveCollection'])->whereNumber('id');
        Route::post('collections/{id}/archive', [C::class, 'archiveCollection'])->whereNumber('id');
        Route::post('collections/{id}/order', [C::class, 'collectionOrder'])->whereNumber('id');
        Route::post('products/{id}/media', [C::class, 'upload'])->whereNumber('id');
        Route::post('products/{id}/media-order', [C::class, 'mediaUpdate'])->whereNumber('id');
        Route::post('products/{id}/media/{media}/archive', [C::class, 'mediaArchive'])->whereNumber('id')->whereUuid('media');
        Route::post('locations', [S::class, 'createLocation']);
        Route::post('adjustments', [S::class, 'adjust']);
        Route::post('transfers', [S::class, 'transfer']);
    });
});
