<?php

use App\Http\Controllers\Admin\AiProductDescriptionController;
use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GtinController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\HeroSlideController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductReelController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\StockMovementController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SystemMonitorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ServiceInquiryController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/shop', [StorefrontController::class, 'catalog'])->name('catalog');
Route::get('/shop/category/{category}', [StorefrontController::class, 'productCategory'])->name('categories.products');
Route::get('/products/{product}', [StorefrontController::class, 'product'])->name('products.show');
Route::get('/services', [StorefrontController::class, 'services'])->name('services');
Route::get('/services/category/{category}', [StorefrontController::class, 'serviceCategory'])->name('categories.services');
Route::get('/services/{service}', [StorefrontController::class, 'service'])->name('services.show');
Route::post('/services/{service}/inquiry', [ServiceInquiryController::class, 'store'])->name('services.inquiry');
Route::get('/pages/{page}', [PageController::class, 'show'])->whereIn('page', ['about', 'contact', 'shipping', 'returns', 'privacy', 'terms'])->name('pages.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
Route::post('/cart/{product}', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{product}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('sensitive:online_order.checkout,order,orders,input:_none')->name('checkout.store');
Route::get('/order-success/{orderNumber}', [CheckoutController::class, 'success'])->name('checkout.success');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/google-merchant-feed.xml', [FeedController::class, 'merchant'])->name('feeds.merchant');
Route::get('/sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::post('ai/format-product-description', AiProductDescriptionController::class)->middleware(['permission:manage_storefront', 'throttle:6,1'])->name('ai.format-product-description');

    Route::middleware('permission:manage_storefront')->group(function (): void {
        Route::get('carousel', [HeroSlideController::class, 'index'])->name('carousel.index');
        Route::post('carousel', [HeroSlideController::class, 'store'])->name('carousel.store');
        Route::match(['post', 'patch'], 'carousel/{heroSlide}', [HeroSlideController::class, 'update'])->name('carousel.update');
        Route::delete('carousel/{heroSlide}', [HeroSlideController::class, 'destroy'])->name('carousel.destroy');
        Route::post('carousel/{heroSlide}/restore', [HeroSlideController::class, 'restore'])->whereNumber('heroSlide')->name('carousel.restore');

        Route::get('reels', [ProductReelController::class, 'index'])->name('reels.index');
        Route::post('reels', [ProductReelController::class, 'store'])->name('reels.store');
        Route::match(['post', 'patch'], 'reels/{productReel}', [ProductReelController::class, 'update'])->name('reels.update');
        Route::delete('reels/{productReel}', [ProductReelController::class, 'destroy'])->name('reels.destroy');
        Route::post('reels/{productReel}/restore', [ProductReelController::class, 'restore'])->whereNumber('productReel')->name('reels.restore');

        Route::resource('products', AdminProductController::class)->except('show');
        Route::post('products/{product}/restore', [AdminProductController::class, 'restore'])->whereNumber('product')->name('products.restore');
        Route::get('gtin', [GtinController::class, 'index'])->name('gtin.index');
        Route::patch('gtin/{product}', [GtinController::class, 'update'])->name('gtin.update');

        Route::get('catalog', [AdminCatalogController::class, 'index'])->name('catalog.index');
        Route::post('catalog/categories', [AdminCatalogController::class, 'category'])->name('catalog.categories.store');
        Route::match(['post', 'patch'], 'catalog/categories/{category:id}', [AdminCatalogController::class, 'updateCategory'])->name('catalog.categories.update');
        Route::delete('catalog/categories/{category:id}', [AdminCatalogController::class, 'destroyCategory'])->name('catalog.categories.destroy');
        Route::post('catalog/categories/{category}/restore', [AdminCatalogController::class, 'restoreCategory'])->whereNumber('category')->name('catalog.categories.restore');
        Route::post('catalog/brands', [AdminCatalogController::class, 'brand'])->name('catalog.brands.store');
        Route::match(['post', 'patch'], 'catalog/brands/{brand}', [AdminCatalogController::class, 'updateBrand'])->name('catalog.brands.update');
        Route::delete('catalog/brands/{brand}', [AdminCatalogController::class, 'destroyBrand'])->name('catalog.brands.destroy');
        Route::post('catalog/brands/{brand}/restore', [AdminCatalogController::class, 'restoreBrand'])->whereNumber('brand')->name('catalog.brands.restore');
    });

    Route::middleware('permission:manage_services')->group(function (): void {
        Route::resource('services', AdminServiceController::class)->except('show');
        Route::post('services/{service}/restore', [AdminServiceController::class, 'restore'])->whereNumber('service')->name('services.restore');
    });

    Route::middleware('permission:manage_suppliers')->group(function (): void {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::match(['post', 'patch'], 'suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    Route::get('stock', [StockController::class, 'index'])->middleware('permission:view_inventory,manage_purchases')->name('stock.index');
    Route::get('stock/inventory', InventoryController::class)->middleware('permission:view_inventory')->name('stock.inventory');
    Route::post('stock/purchases', [StockController::class, 'purchase'])->middleware(['permission:manage_purchases', 'sensitive:purchase.created,stock_purchase,stock_purchases,input:_none'])->name('stock.purchases.store');
    Route::get('stock/purchases/{purchase}', [PurchaseController::class, 'show'])->middleware('permission:manage_purchases,view_costs')->whereNumber('purchase')->name('stock.purchases.show');
    Route::patch('stock/purchases/{purchase}', [PurchaseController::class, 'update'])->middleware(['permission:manage_purchases', 'sensitive:purchase.updated,stock_purchase,stock_purchases,route:purchase'])->whereNumber('purchase')->name('stock.purchases.update');
    Route::post('stock/purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->middleware(['permission:receive_purchases', 'sensitive:purchase.received,stock_purchase,stock_purchases,route:purchase'])->whereNumber('purchase')->name('stock.purchases.receive');
    Route::post('stock/purchases/{purchase}/payments', [PurchaseController::class, 'payment'])->middleware(['permission:manage_purchases', 'sensitive:purchase.payment_posted,stock_purchase,stock_purchases,route:purchase'])->whereNumber('purchase')->name('stock.purchases.payments.store');
    Route::post('stock/purchases/{purchase}/returns', [PurchaseController::class, 'returnItems'])->middleware(['permission:manage_purchases', 'sensitive:purchase.return_posted,stock_purchase,stock_purchases,route:purchase'])->whereNumber('purchase')->name('stock.purchases.returns.store');
    Route::post('stock/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->middleware(['permission:manage_purchases', 'sensitive:purchase.cancelled,stock_purchase,stock_purchases,route:purchase'])->whereNumber('purchase')->name('stock.purchases.cancel');
    Route::post('stock/adjustments', [StockController::class, 'adjustment'])->middleware(['permission:adjust_stock', 'sensitive:stock.adjusted,product,products,input:product_id'])->name('stock.adjustments.store');
    Route::post('stock/movements/{movement}/reverse', [StockMovementController::class, 'reverse'])->middleware(['permission:adjust_stock', 'sensitive:stock.adjustment_reversed,stock_movement,stock_movements,route:movement'])->whereNumber('movement')->name('stock.movements.reverse');
    Route::post('stock/expenses', [StockController::class, 'expense'])->middleware(['permission:manage_expenses', 'sensitive:expense.legacy_created,expense,expenses,input:_none'])->name('stock.expenses.store');
    Route::post('stock/pos', [StockController::class, 'pos'])->middleware(['permission:use_pos', 'sensitive:pos.legacy_sale,pos_sale,pos_sales,input:_none'])->name('stock.pos.store');

    Route::middleware('permission:manage_orders')->group(function (): void {
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}', [AdminOrderController::class, 'update'])->middleware('sensitive:online_order.status_updated,order,orders,route:order')->name('orders.update');
    });

    Route::middleware('permission:manage_inquiries')->group(function (): void {
        Route::get('inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
        Route::patch('inquiries/{inquiry}', [InquiryController::class, 'update'])->name('inquiries.update');
    });

    Route::middleware('permission:manage_merchant')->group(function (): void {
        Route::get('merchant', [MerchantController::class, 'index'])->name('merchant.index');
        Route::post('merchant/settings', [MerchantController::class, 'updateSettings'])->name('merchant.settings');
        Route::post('merchant/credentials', [MerchantController::class, 'uploadCredentials'])->name('merchant.credentials');
        Route::post('merchant/register-gcp', [MerchantController::class, 'registerGcp'])->name('merchant.register-gcp');
        Route::post('merchant/test', [MerchantController::class, 'test'])->name('merchant.test');
        Route::post('merchant/sync-all', [MerchantController::class, 'syncAll'])->name('merchant.sync-all');
        Route::post('merchant/products/{product}', [MerchantController::class, 'sync'])->name('merchant.sync');
    });

    Route::get('system-monitor', SystemMonitorController::class)->middleware('permission:view_system_monitor')->name('system-monitor');
});
