<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController; // Importe le contrôleur des conversations et des messages du chat.
use App\Http\Controllers\ConnectFourController;
use App\Http\Controllers\GamePlayersController;
use App\Http\Controllers\Quizcontroller;
use App\Http\Controllers\QuizDemoController;
use App\Http\Controllers\QuizPaymentController;
use App\Http\Controllers\QuizProgressController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('accueil') : view('welcome-games');
})->name('home');

Route::get('/accueil', [QuizProgressController::class, 'home'])->middleware('auth')->name('accueil');

Route::post('/language', function (Request $request) {
    $data = $request->validate(['locale' => ['required', Rule::in(['en', 'fr', 'de'])]]);
    $request->session()->put('locale', $data['locale']);

    return back();
})->name('public.language');

Route::get('/demo', [QuizDemoController::class, 'show'])->name('quiz.demo');
Route::post('/demo/start', [QuizDemoController::class, 'start'])->middleware('throttle:20,1')->block()->name('quiz.demo.start');
Route::post('/demo/answer', [QuizDemoController::class, 'answer'])->middleware('throttle:60,1')->block()->name('quiz.demo.answer');
Route::post('/demo/continue', [QuizDemoController::class, 'next'])->block()->name('quiz.demo.continue');

Route::middleware('auth')->group(function () {
    Route::post('/quiz/daily', [QuizProgressController::class, 'daily'])->block()->name('quiz.daily');
    Route::get('/progress', [QuizProgressController::class, 'index'])->name('progress.index');
    Route::get('/progress/{attempt}', [QuizProgressController::class, 'show'])->name('progress.show');
    Route::post('/progress/{attempt}/resume', [QuizProgressController::class, 'resume'])->block()->name('progress.resume');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::middleware('auth')->prefix('chat')->name('chat.')->group(function () { // auth exige une connexion ; prefix() ajoute /chat aux URL ; name() préfixe les noms des routes.
    Route::get('/recipients', [ChatController::class, 'recipients'])->middleware('throttle:60,1')->name('recipients');
    Route::post('/conversations/{conversation}/reply', [ChatController::class, 'reply'])->middleware('throttle:10,1')->name('reply');
    Route::get('/conversations', [ChatController::class, 'index'])->name('index'); // GET liste uniquement les conversations du compte connecté.
    Route::post('/conversations', [ChatController::class, 'store'])->middleware('throttle:10,1')->name('store'); // POST crée une conversation ; throttle limite à dix créations par minute et par compte.
    Route::get('/conversations/{conversation}', [ChatController::class, 'show'])->name('show'); // GET consulte l'historique après vérification des droits dans le contrôleur.
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:30,1')->name('send'); // POST envoie un message ; throttle limite à trente envois par minute et par compte.
}); // Fin du groupe des routes du chat ; les requêtes POST conservent la protection CSRF des routes web.

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
Route::post('/quiz/continue', [Quizcontroller::class, 'continue'])->middleware('auth')->block()->name('quiz.continue');

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

Route::post('/login', [AuthController::class, 'authenticate'])->middleware(['guest', 'throttle:login'])->name('login.store');
Route::get('/register', [AuthController::class, 'register'])->name('register');
Route::post('/register', [AuthController::class, 'store'])->name('register.store');
Route::post('/deconnexion', [AuthController::class, 'logout'])->name('quiz.logout');
