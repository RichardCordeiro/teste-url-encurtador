<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MetricsController;
use App\Models\Link;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $linksDashboard = Link::query()
        ->where('user_id', Auth::id())
        ->latest('id')
        ->take(10)
        ->get();
    return view('dashboard', compact('linksDashboard'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // MVC
    Route::get('/links', [LinkController::class, 'index'])->name('links.index');
    Route::post('/links', [LinkController::class, 'store'])->middleware('throttle:30,1')->name('links.store');
    Route::delete('/links/{link}', [LinkController::class, 'destroy'])->name('links.destroy');

    // API JSON mínima
    Route::get('/api/links', [LinkController::class, 'apiIndex'])->name('api.links.index');
    Route::post('/api/links', [LinkController::class, 'apiStore'])->middleware('throttle:30,1')->name('api.links.store');
    Route::get('/api/links/{link}', [LinkController::class, 'apiShow'])->name('api.links.show');
    Route::post('/links/{link}/expire', [LinkController::class, 'expire'])->name('links.expire');
    Route::get('/links/poll', [LinkController::class, 'poll'])->name('links.poll');
    Route::get('/links/{link}/qrcode', [LinkController::class, 'qrcode'])->name('links.qrcode');

    // Metrics API
    Route::get('/metrics/summary', [MetricsController::class, 'summary'])->name('metrics.summary');
    Route::get('/metrics/top', [MetricsController::class, 'top'])->name('metrics.top');
});

Route::get('/s/{slug}', [LinkController::class, 'redirect'])->name('links.redirect');

require __DIR__.'/auth.php';
