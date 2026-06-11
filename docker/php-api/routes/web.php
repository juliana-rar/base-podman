<?php

use App\Http\Controllers\PostController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SlideImageController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', WelcomeController::class)->name('home');

// Detall públic d'un post (URL amb el slug del títol)
Route::get('posts/{post:slug}', [PostController::class, 'show'])->name('posts.show');

Route::middleware(['auth', 'verified'])->group(function () {
    // El dashboard només és per a admins; l'usuari normal va a reservar.
    Route::get('dashboard', function () {
        return auth()->user()->isAdmin()
            ? Inertia::render('Dashboard')
            : redirect()->route('reservar');
    })->name('dashboard');

    // Reserva d'hores (usuari normal)
    Route::get('reservar', [SlotController::class, 'index'])->name('reservar');
    Route::post('reservas', [ReservationController::class, 'store'])->name('reservas.store');
    Route::delete('reservas/{reservation}', [ReservationController::class, 'destroy'])->name('reservas.destroy');

    // Panell d'administració (només admin)
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('horas', [SlotController::class, 'manage'])->name('horas');
        Route::post('horas', [SlotController::class, 'store'])->name('horas.store');
        Route::delete('horas/{slot}', [SlotController::class, 'destroy'])->name('horas.destroy');

        Route::get('posts', [PostController::class, 'manage'])->name('posts');
        Route::post('posts', [PostController::class, 'store'])->name('posts.store');
        Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
        Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

        // Catàleg d'etiquetes
        Route::get('etiquetes', [TagController::class, 'index'])->name('etiquetes');
        Route::post('tags', [TagController::class, 'store'])->name('tags.store');
        Route::put('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

        // Imatges del carrusel de presentació
        Route::get('imatges', [SlideImageController::class, 'index'])->name('imatges');
        Route::post('imatges', [SlideImageController::class, 'store'])->name('imatges.store');
        Route::put('imatges/{image}', [SlideImageController::class, 'update'])->name('imatges.update');
        Route::delete('imatges/{image}', [SlideImageController::class, 'destroy'])->name('imatges.destroy');

        // Serveis
        Route::get('serveis', [ServiceController::class, 'index'])->name('serveis');
        Route::post('serveis', [ServiceController::class, 'store'])->name('serveis.store');
        Route::post('serveis/{service}', [ServiceController::class, 'update'])->name('serveis.update');
        Route::delete('serveis/{service}', [ServiceController::class, 'destroy'])->name('serveis.destroy');

        // Historial de totes les reserves fetes
        Route::get('reserves', [ReservationController::class, 'history'])->name('reserves');
    });
});

require __DIR__.'/settings.php';
