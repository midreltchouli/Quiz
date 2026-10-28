<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\User;
use App\Services\Games\QuizCategories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class Quizcontroller extends Controller
{
    public function index(Request $request)
    {
        if (! Question::exists()) {
            $request->session()->forget(['question_index', 'score', 'quiz_question_ids']);

            return view('quiz_empty');
        }
        // Keep solo games started before player selection was introduced.
        if (! $request->session()->has('quiz_players') && $request->session()->has('question_index')) {
            $request->session()->put('quiz_players', [[
                'name' => $request->user()->name,
                'score' => $request->session()->get('score', 0),
                'answered' => $request->session()->get('question_index', 0),
            ]]);
        }
        $players = $request->session()->get('quiz_players', []);
        $questionIds = $this->questionIds($request, max(1, count($players)));
        $index = $request->session()->get('question_index', 0);
        if ($players !== [] && $index >= count($questionIds)) {
            return view('quiz_finish', ['score' => $request->session()->get('score'), 'total' => count($questionIds), 'players' => $players]);
        }
        if ($request->user()->refresh()->hasReachedFreeQuizLimit()) {
            return view('quiz_upgrade', [
                'limit' => config('billing.free_question_limit'),
                'price' => number_format(config('billing.amount') / 100, 2, ',', ' ').' '.strtoupper(config('billing.currency')),
                'paymentReady' => filled(config('billing.stripe_secret')) && filled(config('billing.stripe_webhook_secret')),
            ]);
        }

        if ($players === []) {
            return view('games.players', ['gameType' => 'quiz', 'quizCategories' => QuizCategories::choices()]);
        }

        $question = Question::find($questionIds[$index]);
        if (! $question) {
            return view('quiz_empty');
        }
        $question = $question->forLocale(app()->getLocale());

        return view('quiz', ['question' => $question, 'number' => $index + 1, 'total' => count($questionIds), 'players' => $players, 'currentPlayer' => $index % count($players)]);
    }

    public function restart(Request $request)
    {
        $request->session()->forget(['question_index', 'score', 'resultat', 'quiz_question_ids']);
        $players = $request->session()->get('quiz_players', []);
        if ($players !== []) {
            foreach ($players as &$player) {
                $player['score'] = 0;
                $player['answered'] = 0;
            }
            unset($player);
            $request->session()->put(['quiz_players' => $players, 'question_index' => 0, 'score' => 0]);
        }

        return redirect()->route('quiz');
    }

    public function verified(Request $request)
    {
        $players = $request->session()->get('quiz_players', []);
        if ($players === []) {
            return redirect()->route('quiz');
        }
        if ($request->user()->refresh()->hasReachedFreeQuizLimit()) {
            return redirect()->route('quiz');
        }
        $index = $request->session()->get('question_index', 0);
        $questionIds = $this->questionIds($request, count($players));
        if ($index >= count($questionIds)) {
            return redirect()->route('quiz');
        }
        $question = Question::find($questionIds[$index]);
        if (! $question) {
            return redirect()->route('quiz');
        }
        $question = $question->forLocale(app()->getLocale());
        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'reponse' => ['required', 'string', Rule::in($question->options)],
        ]);
        if ((int) $validated['question_id'] !== $question->id) {
            return redirect()->route('quiz');
        }
        $accepted = DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($user->hasReachedFreeQuizLimit()) {
                return false;
            }
            $user->increment('quiz_questions_answered');

            return true;
        });
        if (! $accepted) {
            return redirect()->route('quiz');
        }
        $correct = $validated['reponse'] === $question->answer;
        $currentPlayer = $index % count($players);
        $players[$currentPlayer]['answered']++;
        if ($correct) {
            $players[$currentPlayer]['score']++;
        }
        $request->session()->put('quiz_players', $players);
        if ($correct) {
            $request->session()->increment('score');
        }
        $request->session()->increment('question_index');

        $feedback = $correct
            ? __('The answer is correct.')
            : __('The answer is incorrect. The correct answer is: :answer', ['answer' => $question->answer]);
        $explanation = $question->translations[app()->getLocale()]['explanation'] ?? null;
        if ($explanation) {
            $feedback .= ' '.$explanation;
        }

        return redirect()->route('quiz')->with('resultat', $players[$currentPlayer]['name'].' : '.$feedback);
    }

    private function questionIds(Request $request, int $playerCount): array
    {
        if (! $request->session()->has('quiz_question_ids')) {
            // Preserve the order of older games already in progress.
            $query = Question::inCategory($request->session()->get('quiz_category', 'general'));
            if ($request->session()->get('question_index', 0) > 0) {
                $query->orderBy('id');
            } else {
                $query->inRandomOrder();
            }
            $ids = $query->pluck('id')->all();
            // Complete rounds give every participant the same number of questions.
            $ids = array_slice($ids, 0, intdiv(count($ids), $playerCount) * $playerCount);
            $request->session()->put('quiz_question_ids', $ids);
        }

        return $request->session()->get('quiz_question_ids');
    }
}
