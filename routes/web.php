<?php

use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CsvExportController;
use App\Http\Controllers\Admin\PaymentProofController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\AfterSalesController;
use App\Http\Controllers\AiImageController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\FlashSaleCheckoutController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\FriendLinkController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PrivateMessageController;
use App\Http\Controllers\ProductCommentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPreferenceController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\UserCenterController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'show'])->name('install.show');
Route::post('/install/check-database', [InstallController::class, 'checkDatabase'])->name('install.check-database');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/tags/{tag:slug}', [ProductController::class, 'tag'])->name('tags.show');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/search', [SearchController::class, 'index'])->name('search.index');
Route::get('/friend-links', [FriendLinkController::class, 'index'])->name('friend-links.index');
Route::get('/ai-image', [AiImageController::class, 'index'])->name('ai-image.index');
Route::post('/ai-image/models', [AiImageController::class, 'models'])->name('ai-image.models');
Route::post('/ai-image/generate', [AiImageController::class, 'generate'])->name('ai-image.generate');
Route::post('/ai-image/stream', [AiImageController::class, 'stream'])->name('ai-image.stream');
Route::get('/users/{user:public_id}', [UserProfileController::class, 'show'])->name('users.show');
Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/announcements/{announcement:slug}', [AnnouncementController::class, 'show'])->name('announcements.show');
Route::get('/forum', [ForumController::class, 'index'])->name('forum.index');
Route::get('/forum/threads/create', [ForumController::class, 'createThread'])->middleware('auth')->name('forum.threads.create');
Route::post('/forum/threads', [ForumController::class, 'storeThreadFromIndex'])->middleware('auth')->name('forum.threads.store-global');
Route::get('/forum/{section:slug}/threads/create', [ForumController::class, 'createThread'])->middleware('auth')->name('forum.sections.threads.create');
Route::get('/forum/{section:slug}', [ForumController::class, 'section'])->name('forum.sections.show');
Route::get('/forum/{section:slug}/{thread:slug}', [ForumController::class, 'show'])->scopeBindings()->name('forum.threads.show');
Route::get('/p/{page:slug}', [PageController::class, 'show'])->name('pages.show');
Route::get('/shipments', [ShipmentController::class, 'show'])->middleware('auth')->name('shipments.show');
Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
Route::post('/support/sessions', [SupportTicketController::class, 'storeSession'])->name('support.sessions.store');
Route::get('/support/sessions/{session}', [SupportTicketController::class, 'showSession'])->name('support.sessions.show');
Route::get('/support/demands', [SupportTicketController::class, 'demand'])->name('support.demands');
Route::post('/support', [SupportTicketController::class, 'store'])->name('support.store');
Route::post('/support/messages', [SupportTicketController::class, 'sendMessage'])->name('support.messages.store');
Route::delete('/support/sessions/{session}', [SupportTicketController::class, 'destroySession'])->name('support.sessions.destroy');
Route::get('/support/messages/{message}/attachment', [SupportTicketController::class, 'attachment'])->name('support.messages.attachment');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::post('/cart/buy-now', [CartController::class, 'buyNow'])->name('cart.buy-now');
Route::patch('/cart/items/{variant}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{variant}', [CartController::class, 'destroy'])->name('cart.items.destroy');

Route::middleware('auth')->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/flash-sales/{flashSale}/reserve', [FlashSaleCheckoutController::class, 'reserve'])->name('flash-sales.reserve');
    Route::get('/flash-sales/checkout/{order}', [FlashSaleCheckoutController::class, 'create'])->name('flash-sales.checkout');
    Route::post('/flash-sales/checkout/{order}', [FlashSaleCheckoutController::class, 'store'])->name('flash-sales.store');
    Route::get('/user', [UserCenterController::class, 'show'])->name('user.center');
    Route::patch('/user/profile', [UserCenterController::class, 'updateProfile'])->name('user.profile.update');
    Route::post('/user/coupons', [UserCenterController::class, 'storeCoupon'])->name('user.coupons.store');
    Route::post('/user/addresses', [UserCenterController::class, 'storeAddress'])->name('user.addresses.store');
    Route::patch('/user/addresses/{address}', [UserCenterController::class, 'updateAddress'])->name('user.addresses.update');
    Route::post('/user/addresses/{address}/default', [UserCenterController::class, 'setDefaultAddress'])->name('user.addresses.default');
    Route::delete('/user/addresses/{address}', [UserCenterController::class, 'destroyAddress'])->name('user.addresses.destroy');
    Route::get('/user/{section}', [UserCenterController::class, 'section'])->name('user.section');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    Route::get('/orders/{order}/after-sales', [AfterSalesController::class, 'create'])->name('orders.after-sales');
    Route::post('/orders/{order}/after-sales', [AfterSalesController::class, 'store'])->name('orders.after-sales.store');
    Route::post('/orders/{order}/contact-support', [AfterSalesController::class, 'contactSupport'])->name('orders.contact-support');
    Route::post('/orders/{order}/payment-proof', [OrderController::class, 'uploadProof'])->name('orders.payment-proof');
    Route::post('/orders/{order}/confirm-receipt', [OrderController::class, 'confirmReceipt'])->name('orders.confirm-receipt');
    Route::post('/orders/{order}/digital-delivery/copied', [OrderController::class, 'markDigitalCopied'])->name('orders.digital-delivery.copied');
    Route::get('/orders/{order}/digital-delivery/{index}', [OrderController::class, 'downloadDigitalAttachment'])->name('orders.digital-delivery.download');
    Route::post('/products/{product:slug}/comments', [ProductCommentController::class, 'store'])->name('product-comments.store');
    Route::post('/products/{product:slug}/wishlist', [ProductPreferenceController::class, 'toggleWishlist'])->name('products.wishlist.toggle');
    Route::post('/products/{product:slug}/favorite', [ProductPreferenceController::class, 'toggleFavorite'])->name('products.favorite.toggle');
    Route::post('/products/{product:slug}/intent-vote', [VoteController::class, 'intent'])->name('votes.intent');
    Route::post('/products/{product:slug}/price-vote', [VoteController::class, 'price'])->name('votes.price');
    Route::get('/messages/{user:public_id}', [PrivateMessageController::class, 'thread'])->name('messages.thread');
    Route::post('/messages/{user:public_id}', [PrivateMessageController::class, 'store'])->name('messages.store');
    Route::post('/forum/{section:slug}/threads', [ForumController::class, 'storeThread'])->name('forum.threads.store');
    Route::patch('/forum/{section:slug}/{thread:slug}', [ForumController::class, 'updateThread'])->scopeBindings()->name('forum.threads.update');
    Route::delete('/forum/{section:slug}/{thread:slug}', [ForumController::class, 'deleteThread'])->scopeBindings()->name('forum.threads.destroy');
    Route::post('/forum/{section:slug}/{thread:slug}/pin', [ForumController::class, 'togglePin'])->scopeBindings()->name('forum.threads.pin');
    Route::post('/forum/{section:slug}/{thread:slug}/like', [ForumController::class, 'likeThread'])->scopeBindings()->name('forum.threads.like');
    Route::post('/forum/{section:slug}/{thread:slug}/share', [ForumController::class, 'shareThread'])->scopeBindings()->name('forum.threads.share');
    Route::post('/forum/{section:slug}/{thread:slug}/comments', [ForumController::class, 'storeComment'])->scopeBindings()->name('forum.comments.store');
    Route::patch('/forum/{section:slug}/{thread:slug}/comments/{comment}', [ForumController::class, 'updateComment'])->scopeBindings()->name('forum.comments.update');
    Route::delete('/forum/{section:slug}/{thread:slug}/comments/{comment}', [ForumController::class, 'deleteComment'])->scopeBindings()->name('forum.comments.destroy');
    Route::post('/forum/{section:slug}/{thread:slug}/comments/{comment}/like', [ForumController::class, 'likeComment'])->scopeBindings()->name('forum.comments.like');
});

Route::middleware(['auth', 'admin'])->prefix('admin/exports')->name('admin.exports.')->group(function (): void {
    Route::get('/products', [CsvExportController::class, 'products'])->name('products');
    Route::get('/customers', [CsvExportController::class, 'customers'])->name('customers');
});

Route::middleware(['auth', 'admin'])->prefix('admin/payment-proofs')->name('admin.payment-proofs.')->group(function (): void {
    Route::get('/orders/{order}', [PaymentProofController::class, 'show'])->name('show');
});

Route::middleware(['auth', 'admin'])->prefix('admin/report-exports')->name('admin.report-exports.')->group(function (): void {
    Route::get('/product-sales', [ReportExportController::class, 'productSales'])->name('product-sales');
    Route::get('/category-sales', [ReportExportController::class, 'categorySales'])->name('category-sales');
    Route::get('/profit-overview', [ReportExportController::class, 'profitOverview'])->name('profit-overview');
});

Route::middleware(['auth', 'admin'])->prefix('admin/backups')->name('admin.backups.')->group(function (): void {
    Route::get('/database', [BackupController::class, 'database'])->name('database');
    Route::get('/uploads', [BackupController::class, 'uploads'])->name('uploads');
});
