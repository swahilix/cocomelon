<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AiProductDescriptionController;
use App\Http\Controllers\Admin\BuyingGuideController as AdminBuyingGuideController;
use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\ConversionReportController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GtinController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\HeroSlideController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductReelController;
use App\Http\Controllers\Admin\ProductIntelligenceController;
use App\Http\Controllers\Admin\DataResetController;
use App\Http\Controllers\Admin\ProductStockController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\StockEntryController;
use App\Http\Controllers\Admin\ProductReviewController as AdminProductReviewController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\StockMovementController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SystemMonitorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyingGuideController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\LocalStoreController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductContactEventController;
use App\Http\Controllers\ServiceInquiryController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/shop', [StorefrontController::class, 'catalog'])->name('catalog');
Route::get('/shop/category/{category}', [StorefrontController::class, 'productCategory'])->name('categories.products');
Route::get('/compare', CompareController::class)->name('compare');
Route::get('/electronics-shop-dodoma', LocalStoreController::class)->name('local.store');
Route::get('/guides', [BuyingGuideController::class, 'index'])->name('guides.index');
Route::get('/guides/{buyingGuide}', [BuyingGuideController::class, 'show'])->name('guides.show');
Route::get('/products/{product}', [StorefrontController::class, 'product'])->name('products.show');
Route::post('/products/{product}/contact-events', [ProductContactEventController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('products.contact-events.store');
Route::get('/services', [StorefrontController::class, 'services'])->name('services');
Route::get('/services/category/{category}', [StorefrontController::class, 'serviceCategory'])->name('categories.services');
Route::get('/services/{service}', [StorefrontController::class, 'service'])->name('services.show');
Route::post('/services/{service}/inquiry', [ServiceInquiryController::class, 'store'])->name('services.inquiry');
Route::get('/pages/{page}', [PageController::class, 'show'])->whereIn('page', ['about', 'contact', 'faq', 'shipping', 'returns', 'warranty', 'privacy', 'terms'])->name('pages.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
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

    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('account/profile', [AccountController::class, 'updateProfile'])->middleware('throttle:20,1')->name('account.profile');
    Route::patch('account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1')->name('account.password');

    // Hub pages that group links to existing admin pages. Each card is shown
    // only to staff who have permission to open the page it links to.
    Route::inertia('tools', 'Admin/Tools/Index')->name('tools');
    Route::inertia('settings', 'Admin/Settings/Index')->name('settings');

    Route::post('ai/format-product-description', AiProductDescriptionController::class)->middleware(['permission:manage_storefront', 'throttle:6,1'])->name('ai.format-product-description');

    Route::middleware('permission:manage_storefront')->group(function (): void {
        Route::get('conversions/export', [ConversionReportController::class, 'export'])->name('conversions.export');
        Route::get('conversions', ConversionReportController::class)->name('conversions');
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
        Route::get('reviews', [AdminProductReviewController::class, 'index'])->name('reviews.index');
        Route::patch('reviews/{productReview}', [AdminProductReviewController::class, 'update'])->name('reviews.update');
        Route::get('gtin', [GtinController::class, 'index'])->name('gtin.index');
        Route::patch('gtin/{product}', [GtinController::class, 'update'])->name('gtin.update');

        Route::resource('guides', AdminBuyingGuideController::class)->except('show');

        Route::get('catalog', [AdminCatalogController::class, 'index'])->name('catalog.index');
        Route::post('catalog/categories', [AdminCatalogController::class, 'category'])->name('catalog.categories.store');
        Route::match(['post', 'patch'], 'catalog/categories/{category:id}', [AdminCatalogController::class, 'updateCategory'])->name('catalog.categories.update');
        Route::delete('catalog/categories/{category:id}', [AdminCatalogController::class, 'destroyCategory'])->name('catalog.categories.destroy');
        Route::post('catalog/categories/{category}/restore', [AdminCatalogController::class, 'restoreCategory'])->whereNumber('category')->name('catalog.categories.restore');
        Route::post('catalog/brands', [AdminCatalogController::class, 'brand'])->name('catalog.brands.store');
        Route::match(['post', 'patch'], 'catalog/brands/{brand}', [AdminCatalogController::class, 'updateBrand'])->name('catalog.brands.update');
        Route::delete('catalog/brands/{brand}', [AdminCatalogController::class, 'destroyBrand'])->name('catalog.brands.destroy');
        Route::post('catalog/brands/{brand}/restore', [AdminCatalogController::class, 'restoreBrand'])->whereNumber('brand')->name('catalog.brands.restore');

        Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::patch('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
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

    // Smart product recognition and market price checks (suggestions only).
    Route::post('products/lookup/barcode', [ProductIntelligenceController::class, 'barcode'])->middleware(['permission:manage_storefront', 'throttle:30,1'])->name('products.lookup.barcode');
    Route::post('products/lookup/image', [ProductIntelligenceController::class, 'image'])->middleware(['permission:manage_storefront', 'throttle:10,1'])->name('products.lookup.image');
    Route::post('market-price', [ProductIntelligenceController::class, 'marketPrice'])->middleware(['permission:manage_storefront,manage_purchases,adjust_stock', 'throttle:15,1'])->name('market-price');

    // Step 2 of product entry: opening stock, purchases and selling prices.
    Route::get('products/{product}/stock', [ProductStockController::class, 'show'])->middleware('permission:manage_storefront,manage_purchases,adjust_stock,view_inventory')->name('products.stock');
    Route::post('products/{product}/price', [ProductStockController::class, 'updatePrice'])->middleware(['permission:manage_storefront', 'sensitive:product.price_updated,product,products,route:product'])->name('products.price');

    // Add stock: opening stock or purchases for any products, separate from product details.
    Route::get('stock/entry', [StockEntryController::class, 'show'])->middleware('permission:adjust_stock,manage_purchases')->name('stock.entry');
    Route::post('stock/opening', [StockEntryController::class, 'opening'])->middleware(['permission:adjust_stock', 'sensitive:stock.opening_posted,product,products,input:_none'])->name('stock.opening.store');

    // Administrators only (checked in the controller): back up and clear transaction data.
    Route::get('system/reset', [DataResetController::class, 'show'])->name('system.reset.show');
    Route::post('system/reset/backup', [DataResetController::class, 'backup'])->middleware('throttle:5,1')->name('system.reset.backup');
    Route::get('system/reset/backups/{file}', [DataResetController::class, 'download'])->name('system.reset.download');
    Route::post('system/reset', [DataResetController::class, 'reset'])->middleware('throttle:5,1')->name('system.reset.run');

    // Purchases list and purchase orders (items + quantities to buy, printed without prices).
    Route::get('purchases', [PurchaseController::class, 'index'])->middleware('permission:manage_purchases,view_costs')->name('purchases.index');
    Route::middleware('permission:manage_purchases')->prefix('purchase-orders')->name('purchase-orders.')->group(function (): void {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('create', [PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('sensitive:purchase_order.created,purchase_order,purchase_orders,input:_none')->name('store');
        Route::get('{purchaseOrder}', [PurchaseOrderController::class, 'show'])->whereNumber('purchaseOrder')->name('show');
        Route::get('{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->whereNumber('purchaseOrder')->name('edit');
        Route::put('{purchaseOrder}', [PurchaseOrderController::class, 'update'])->whereNumber('purchaseOrder')->middleware('sensitive:purchase_order.updated,purchase_order,purchase_orders,route:purchaseOrder')->name('update');
        Route::post('{purchaseOrder}/sent', [PurchaseOrderController::class, 'markSent'])->whereNumber('purchaseOrder')->name('sent');
        Route::post('{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->whereNumber('purchaseOrder')->middleware('sensitive:purchase_order.cancelled,purchase_order,purchase_orders,route:purchaseOrder')->name('cancel');
        Route::get('{purchaseOrder}/export', [PurchaseOrderController::class, 'export'])->whereNumber('purchaseOrder')->name('export');
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
