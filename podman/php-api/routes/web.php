<?php

use App\Http\Controllers\BusinessHourController;
use App\Http\Controllers\CancellationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceOptionController;
use App\Http\Controllers\SlideImageController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\StockCategoryController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', WelcomeController::class)->name('home');

// Detall públic d'un post (URL amb el slug del títol)
Route::get('posts/{post:slug}', [PostController::class, 'show'])->name('posts.show');

Route::middleware(['auth', 'verified'])->group(function () {
    // El dashboard és per a admins i per al personal amb alguna pantalla atorgada;
    // l'usuari normal va a reservar.
    Route::get('dashboard', function () {
        $user = auth()->user();

        return $user->isAdmin() || count($user->accessibleScreens()) > 0
            ? Inertia::render('Dashboard')
            : redirect()->route('reservar');
    })->name('dashboard');

    // Reserva d'hores (usuari normal)
    Route::get('reservar', [SlotController::class, 'index'])->name('reservar');
    Route::get('reserves', [SlotController::class, 'reserves'])->name('reserves');

    // Xat de l'usuari amb el negoci (el seu propi fil).
    Route::get('xat', [ChatController::class, 'show'])->name('xat');
    Route::post('xat', [ChatController::class, 'send'])->name('xat.send');

    // Valoracions fetes pels usuaris (pantalla «reviews»).
    Route::get('reserves-admin', [ReservationController::class, 'reviews'])->middleware('screen:reviews')->name('reserves-admin');
    Route::post('reserves-admin/{reservation}/publica', [ReservationController::class, 'toggleReviewPublished'])
        ->middleware('screen:reviews')->name('reserves-admin.toggle');
    Route::post('reservas', [ReservationController::class, 'store'])->name('reservas.store');
    Route::post('reservas/{reservation}/valoracio', [ReservationController::class, 'review'])->name('reservas.review');
    Route::delete('reservas/{reservation}', [ReservationController::class, 'destroy'])->name('reservas.destroy');

    // Panell d'administració. Cada secció es protegeix per la seva «pantalla»:
    // els admins hi tenen accés total; el personal, segons les pantalles del seu rol.
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('screen:hores')->group(function () {
            Route::get('horas', [SlotController::class, 'manage'])->name('horas');
            Route::post('horas', [SlotController::class, 'store'])->name('horas.store');
            Route::delete('horas/{slot}', [SlotController::class, 'destroy'])->name('horas.destroy');
        });

        Route::middleware('screen:posts')->group(function () {
            Route::get('posts', [PostController::class, 'manage'])->name('posts');
            Route::post('posts', [PostController::class, 'store'])->name('posts.store');
            Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
            Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

            // Catàleg d'etiquetes (gestionat des de la pàgina de Posts)
            Route::post('tags', [TagController::class, 'store'])->name('tags.store');
            Route::put('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
            Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
        });

        Route::middleware('screen:imatges')->group(function () {
            Route::get('imatges', [SlideImageController::class, 'index'])->name('imatges');
            Route::post('imatges', [SlideImageController::class, 'store'])->name('imatges.store');
            Route::put('imatges/{image}', [SlideImageController::class, 'update'])->name('imatges.update');
            Route::delete('imatges/{image}', [SlideImageController::class, 'destroy'])->name('imatges.destroy');
        });

        Route::middleware('screen:serveis')->group(function () {
            Route::get('serveis', [ServiceController::class, 'index'])->name('serveis');
            Route::post('serveis', [ServiceController::class, 'store'])->name('serveis.store');
            Route::post('serveis/{service}', [ServiceController::class, 'update'])->name('serveis.update');
            Route::delete('serveis/{service}', [ServiceController::class, 'destroy'])->name('serveis.destroy');

            Route::post('serveis-categories', [ServiceCategoryController::class, 'store'])->name('serveis.categories.store');
            Route::post('serveis-categories/{serviceCategory}', [ServiceCategoryController::class, 'update'])->name('serveis.categories.update');
            Route::delete('serveis-categories/{serviceCategory}', [ServiceCategoryController::class, 'destroy'])->name('serveis.categories.destroy');

            Route::post('serveis-options', [ServiceOptionController::class, 'store'])->name('serveis.options.store');
            Route::post('serveis-options/{serviceOption}', [ServiceOptionController::class, 'update'])->name('serveis.options.update');
            Route::delete('serveis-options/{serviceOption}', [ServiceOptionController::class, 'destroy'])->name('serveis.options.destroy');
        });

        Route::middleware('screen:stock')->group(function () {
            Route::get('stock', [StockController::class, 'index'])->name('stock');
            Route::post('stock', [StockController::class, 'store'])->name('stock.store');
            Route::post('stock/{stock}', [StockController::class, 'update'])->name('stock.update');
            Route::delete('stock/{stock}', [StockController::class, 'destroy'])->name('stock.destroy');

            Route::post('stock-categories', [StockCategoryController::class, 'store'])->name('stock.categories.store');
            Route::post('stock-categories/{stockCategory}', [StockCategoryController::class, 'update'])->name('stock.categories.update');
            Route::delete('stock-categories/{stockCategory}', [StockCategoryController::class, 'destroy'])->name('stock.categories.destroy');
        });

        Route::middleware('screen:empleats')->group(function () {
            Route::get('empleats', [EmployeeController::class, 'index'])->name('empleats');
            Route::post('empleats', [EmployeeController::class, 'store'])->name('empleats.store');
            Route::post('empleats/{employee}', [EmployeeController::class, 'update'])->name('empleats.update');
            Route::delete('empleats/{employee}', [EmployeeController::class, 'destroy'])->name('empleats.destroy');
        });

        Route::middleware('screen:reserves')->group(function () {
            Route::get('reserves', [ReservationController::class, 'history'])->name('reserves');
            Route::put('reserves/{reservation}', [ReservationController::class, 'update'])->name('reserves.update');
        });

        Route::middleware('screen:cancellacions')->group(function () {
            Route::get('cancellacions', [CancellationController::class, 'index'])->name('cancellacions');
        });

        Route::middleware('screen:xat')->group(function () {
            Route::get('xat', [ChatController::class, 'adminIndex'])->name('xat');
            Route::post('xat/{user}', [ChatController::class, 'adminSend'])->name('xat.send');
        });

        Route::middleware('screen:informacio')->group(function () {
            Route::get('informacio', [BusinessHourController::class, 'index'])->name('informacio');
            Route::put('informacio', [BusinessHourController::class, 'update'])->name('informacio.update');
        });

        // Gestió d'usuaris, rols i permisos: només administradors.
        Route::middleware('admin')->group(function () {
            Route::get('usuaris', [UserController::class, 'index'])->name('usuaris');
            Route::put('usuaris/{user}', [UserController::class, 'update'])->name('usuaris.update');
            Route::delete('usuaris/{user}', [UserController::class, 'destroy'])->name('usuaris.destroy');

            Route::post('rols', [RoleController::class, 'store'])->name('rols.store');
            Route::put('rols/{role}', [RoleController::class, 'update'])->name('rols.update');
            Route::delete('rols/{role}', [RoleController::class, 'destroy'])->name('rols.destroy');
        });
    });
});

require __DIR__.'/settings.php';
