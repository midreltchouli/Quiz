<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class Quizcontroller extends Controller
{
    public function index(Request $request)
    {
        $questions = Question::orderBy('id')->get();
        if ($questions->isEmpty()) {
            $request->session()->forget(['question_index', 'score']);

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
        if ($players !== []) {
            // Complete rounds give every participant the same number of questions.
            $questions = $questions->take(intdiv($questions->count(), count($players)) * count($players));
        }
        $index = $request->session()->get('question_index', 0);
        if ($players !== [] && $index >= $questions->count()) {
            return view('quiz_finish', ['score' => $request->session()->get('score'), 'total' => $questions->count(), 'players' => $players]);
        }
        if ($request->user()->refresh()->hasReachedFreeQuizLimit()) {
            return view('quiz_upgrade', [
                'limit' => config('billing.free_question_limit'),
                'price' => number_format(config('billing.amount') / 100, 2, ',', ' ').' '.strtoupper(config('billing.currency')),
                'paymentReady' => filled(config('billing.stripe_secret')) && filled(config('billing.stripe_webhook_secret')),
            ]);
        }

        if ($players === []) {
            return view('games.players', ['gameType' => 'quiz']);
        }

        return view('quiz', ['question' => $questions[$index], 'number' => $index + 1, 'total' => $questions->count(), 'players' => $players, 'currentPlayer' => $index % count($players)]);
    }

    public function restart(Request $request)
    {
        $request->session()->forget(['question_index', 'score', 'resultat']);
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
        $total = intdiv(Question::count(), count($players)) * count($players);
        if ($index >= $total) {
            return redirect()->route('quiz');
        }
        $question = Question::orderBy('id')->skip($index)->first();
        if (! $question) {
            return redirect()->route('quiz');
        }
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

        return redirect()->route('quiz')->with('resultat', $players[$currentPlayer]['name'].' : '.$feedback);
    }
}
