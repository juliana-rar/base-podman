<?php

use App\Models\Task;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'message' => 'Hola des de Laravel! 🚀',
        'time' => now()->toDateTimeString(),
    ]);
});

// Llegeix totes les tasques de la base de dades MySQL i les retorna com a JSON.
Route::get('/tasks', function () {
    return Task::orderBy('id')->get();
});
