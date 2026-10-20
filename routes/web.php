<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConnectFourController;
use App\Http\Controllers\GamePlayersController;
use App\Http\Controllers\Quizcontroller;
use App\Http\Controllers\QuizPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'accueil' : 'login');
})->name('home');

Route::view('/accueil', 'home')->middleware('auth')->name('accueil');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::middleware('auth')->group(function () {
    Route::get('/games/{game}/players', [GamePlayersController::class, 'show'])->whereIn('game', ['quiz', 'connect-four'])->name('games.players');
    Route::post('/games/{game}/players', [GamePlayersController::class, 'store'])->whereIn('game', ['quiz', 'connect-four'])->block()->name('games.players.store');
    Route::get('/puissance-4', [ConnectFourController::class, 'show'])->name('connect-four.show');
    Route::post('/puissance-4/play', [ConnectFourController::class, 'play'])->block()->name('connect-four.play');
    Route::post('/puissance-4/restart', [ConnectFourController::class, 'restart'])->block()->name('connect-four.restart');
});

Route::get('/quiz', [Quizcontroller::class, 'index'])
    ->middleware(['auth'])->name('quiz');
// ->name('quiz')

Route::post('/quiz/verified', [Quizcontroller::class, 'verified'])->middleware('auth')->block()->name('quiz.verified');

Route::post('/quiz/upgrade/checkout', [QuizPaymentController::class, 'checkout'])
    ->middleware(['auth', 'throttle:10,1'])->name('quiz.checkout');
Route::get('/quiz/upgrade/success', [QuizPaymentController::class, 'success'])
    ->middleware(['auth', 'throttle:20,1'])->name('quiz.payment.success');
Route::post('/payments/stripe/webhook', [QuizPaymentController::class, 'webhook'])->name('stripe.webhook');

Route::post('/quiz/restart', [Quizcontroller::class, 'restart'])
    ->middleware(['auth'])
    ->block()
    ->name('quiz.restart');

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::post('/login', [AuthController::class, 'authenticate'])->name('login.store');
Route::get('/register', [AuthController::class, 'register'])->name('register');
Route::post('/register', [AuthController::class, 'store'])->name('register.store');
Route::post('/deconnexion', [AuthController::class, 'logout'])->name('quiz.logout');
