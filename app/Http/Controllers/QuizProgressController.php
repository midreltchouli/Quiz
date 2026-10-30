<?php

namespace App\Http\Controllers;

use App\Models\QuizAttempt;
use App\Services\Games\QuizProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizProgressController extends Controller
{
    public function home(Request $request): View
    {
        $query = QuizAttempt::where('user_id', $request->user()->id);

        return view('home', [
            'completedGames' => (clone $query)->whereNotNull('completed_at')->count(),
            'daily' => (clone $query)->whereDate('daily_date', now()->toDateString())->first(),
            'resume' => (clone $query)->whereNull('completed_at')->orderByDesc('is_trial')->latest('id')->first(),
        ]);
    }

    public function daily(Request $request, QuizProgress $progress): RedirectResponse
    {
        $progress->start($request, 'general', 5, [$request->user()->name], daily: true);

        return to_route('quiz');
    }

    public function resume(Request $request, QuizAttempt $attempt, QuizProgress $progress): RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);
        $request->session()->forget(['quiz_review', 'resultat']);
        $progress->restore($request, $attempt);

        return to_route('quiz');
    }

    public function index(Request $request): View
    {
        $query = QuizAttempt::where('user_id', $request->user()->id)->whereNotNull('completed_at');
        $total = 0;
        $correct = 0;
        $best = 0;
        // Stream aggregates rather than loading all snapshots into memory.
        foreach ((clone $query)->select(['score', 'question_ids'])->cursor() as $attempt) {
            $count = count($attempt->question_ids);
            $total += $count;
            $correct += $attempt->score;
            $best = max($best, (int) round(100 * $attempt->score / max(1, $count)));
        }

        return view('progress.index', [
            'attempts' => (clone $query)->latest('completed_at')->paginate(10),
            'games' => (clone $query)->count(), 'total' => $total, 'correct' => $correct, 'best' => $best,
        ]);
    }

    public function show(Request $request, QuizAttempt $attempt): View
    {
        abort_unless($attempt->user_id === $request->user()->id && $attempt->completed_at, 404);

        return view('progress.show', ['attempt' => $attempt]);
    }
}
