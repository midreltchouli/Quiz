<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Quizcontroller;
use App\Http\Controllers\AuthController;
Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::get('/quiz', [Quizcontroller::class, 'index'])
    ->middleware(['auth']);
   //->name('quiz')

Route::post ('/quiz/verified', [Quizcontroller::class, 'verified']);

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::post('/login', [AuthController::class, 'authenticate']);
Route::get('/register', [AuthController::class, 'register']);
Route::post('/register', [AuthController::class, 'store']);
Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout');