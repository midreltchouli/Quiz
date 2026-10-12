<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Quizcontroller;
use App\Http\Controllers\AuthController;
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'accueil' : 'login');
})->name('home');

Route::view('/accueil', 'home')->middleware('auth')->name('accueil');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::get('/quiz', [Quizcontroller::class, 'index'])
    ->middleware(['auth'])->name('quiz');
   //->name('quiz')

Route::post ('/quiz/verified', [Quizcontroller::class, 'verified']);

Route::post('/quiz/restart', [Quizcontroller::class, 'restart'])
    ->middleware(['auth'])
    ->name('quiz.restart');

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::post('/login', [AuthController::class, 'authenticate'])->name('login.store');
Route::get('/register', [AuthController::class, 'register'])->name('register');
Route::post('/register', [AuthController::class, 'store'])->name('register.store');
Route::post('/deconnexion', [AuthController::class, 'logout'])->name('quiz.logout');
