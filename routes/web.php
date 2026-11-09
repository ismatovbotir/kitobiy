<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\TelegramLoginController;
use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\Catalog\CatalogVisibilityController;
use App\Http\Controllers\Catalog\NewsController;
use App\Http\Controllers\Catalog\SitemapController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SwitchLibraryController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->middleware('guest')->name('home');
Route::view('/about', 'about')->name('about');

// Public catalog: shared book facts only (App\Support\PublicCatalog).
Route::middleware('throttle:120,1')->prefix('catalog')->name('catalog.')->controller(CatalogController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/books/{edition}/{slug?}', 'book')->whereNumber('edition')->name('books.show');
    Route::get('/people/{author}/{slug?}', 'person')->whereNumber('author')->name('people.show');
    Route::get('/publishers/{publisher}/{slug?}', 'publisher')->whereNumber('publisher')->name('publishers.show');
    Route::get('/genres/{genre:slug}', 'genre')->name('genres.show');
});
Route::middleware('throttle:120,1')->prefix('news')->name('news.')->controller(NewsController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{post:slug}', 'show')->name('show');
});
Route::get('/sitemap.xml', SitemapController::class)->middleware('throttle:30,1')->name('sitemap');

Route::post('/locale/{locale}', LocaleController::class)->name('locale.update');

Route::livewire('/invitations/{token}', 'pages::invitations.show')->name('invitations.show');
Route::livewire('/gift/{token}', 'pages::gift.show')->middleware('throttle:60,1')->name('gift.show');
Route::livewire('/transfers/{token}', 'pages::transfers.show')->middleware('throttle:60,1')->name('transfers.show');
Route::livewire('/collectors/{token}', 'pages::collectors.show')->middleware('throttle:60,1')->name('collectors.show');
Route::livewire('/c/{publicId}', 'pages::copies.found')->middleware('throttle:60,1')->name('copies.found');
Route::livewire('/loans/{token}', 'pages::loans.show')->middleware('throttle:60,1')->name('loans.show');

Route::post('/telegram/webhook', TelegramWebhookController::class)->middleware('throttle:120,1')->name('telegram.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/auth/telegram/callback', TelegramLoginController::class)->middleware('throttle:10,1')->name('telegram.login');
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/register', 'pages::auth.register')->name('register');
    Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('password.request');
    Route::livewire('/reset-password/{token}', 'pages::auth.reset-password')->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/confirm', 'pages::auth.confirm')->name('confirmation.notice');
    Route::post('/logout', LogoutController::class)->name('logout');
});

Route::middleware(['auth', 'confirmed'])->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('/books', 'pages::books.index')->name('books.index');
    Route::livewire('/books/add', 'pages::books.create')->name('books.create');
    Route::livewire('/books/duplicates', 'pages::books.duplicates')->name('books.duplicates');
    Route::livewire('/books/{copy}', 'pages::books.show')->whereNumber('copy')->name('books.show');
    Route::livewire('/library/members', 'pages::library.members')->name('library.members');
    Route::livewire('/library/wishlist', 'pages::library.wishlist')->name('library.wishlist');
    Route::livewire('/library/found', 'pages::library.found')->name('library.found');
    Route::livewire('/library/loans', 'pages::library.loans')->name('library.loans');
    Route::livewire('/library/labels', 'pages::library.labels')->name('library.labels');
    Route::livewire('/books/{copy}/offer', 'pages::market.create')->whereNumber('copy')->name('market.create');
    Route::livewire('/market', 'pages::market.index')->name('market.index');
    Route::livewire('/market/{listing}', 'pages::market.show')->whereNumber('listing')->name('market.show');
    Route::livewire('/messages', 'pages::messages.index')->name('messages.index');
    Route::livewire('/messages/{conversation}', 'pages::messages.show')->whereNumber('conversation')->name('messages.show');
    Route::livewire('/profile', 'pages::profile.edit')->name('profile.edit');
    Route::livewire('/libraries', 'pages::library.index')->name('libraries.index');
    Route::post('/libraries/{library}/switch', SwitchLibraryController::class)->name('libraries.switch');
    Route::livewire('/publisher', 'pages::publisher.index')->name('publisher.index');
    Route::livewire('/publisher/{publisher}', 'pages::publisher.books')->whereNumber('publisher')->name('publisher.books');
    Route::livewire('/publisher/{publisher}/books/add', 'pages::publisher.book')->whereNumber('publisher')->name('publisher.books.create');
    Route::livewire('/publisher/{publisher}/books/{edition}', 'pages::publisher.book')->whereNumber(['publisher', 'edition'])->scopeBindings()->name('publisher.books.edit');
});

Route::middleware(['auth', 'confirmed', 'can:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('/authors', 'pages::admin.authors.index')->name('authors.index');
    Route::livewire('/authors/{author}', 'pages::admin.authors.edit')->whereNumber('author')->name('authors.edit');
    Route::livewire('/publishers', 'pages::admin.publishers.index')->name('publishers.index');
    Route::livewire('/publishers/create', 'pages::admin.publishers.edit')->name('publishers.create');
    Route::livewire('/publishers/claims', 'pages::admin.publishers.claims')->name('publishers.claims');
    Route::livewire('/publishers/{publisher}', 'pages::admin.publishers.edit')->whereNumber('publisher')->name('publishers.edit');
    Route::livewire('/genres', 'pages::admin.genres.index')->name('genres.index');
    Route::livewire('/reports', 'pages::admin.reports.index')->name('reports.index');
    Route::livewire('/telegram', 'pages::admin.telegram')->name('telegram');
    Route::livewire('/news', 'pages::admin.news.index')->name('news.index');
    Route::livewire('/news/create', 'pages::admin.news.edit')->name('news.create');
    Route::livewire('/news/{post:id}/edit', 'pages::admin.news.edit')->name('news.edit');
    Route::post('/catalog/{type}/{id}/visibility', CatalogVisibilityController::class)->whereIn('type', ['books', 'people', 'publishers'])->whereNumber('id')->name('catalog.visibility');
    Route::livewire('/labels', 'pages::admin.labels.index')->name('labels.index');
    Route::livewire('/labels/create', 'pages::admin.labels.edit')->name('labels.create');
    Route::livewire('/labels/{labelTemplate}/edit', 'pages::admin.labels.edit')->name('labels.edit');
});
