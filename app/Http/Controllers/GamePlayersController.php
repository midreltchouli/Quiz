<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Services\Games\ConnectFourGame;
use App\Services\Games\QuizCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GamePlayersController extends Controller
{
    public function show(Request $request, string $game): View
    {
        return view('games.players', ['gameType' => $game, 'quizCategories' => $game === 'quiz' ? QuizCategories::choices() : []]);
    }

    public function store(Request $request, string $game, ConnectFourGame $connectFour): RedirectResponse
    {
        $isQuiz = $game === 'quiz';
        $validated = $request->validate([
            'player_count' => ['required', 'integer', $isQuiz ? 'between:1,6' : 'in:2'],
            'players' => ['required', 'array', 'list', 'size:'.$request->integer('player_count')],
            'players.*' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'category' => $isQuiz ? ['sometimes', 'required', 'string', Rule::in(array_keys(config('quiz.categories')))] : ['exclude'],
        ]);

        if ($isQuiz) {
            $category = $validated['category'] ?? 'general';
            if (Question::inCategory($category)->count() < count($validated['players'])) {
                return back()->withInput()->withErrors(['player_count' => __('Not enough questions in this category for this number of players.')]);
            }
            $request->session()->put([
                'quiz_players' => array_map(fn (string $name) => ['name' => $name, 'score' => 0, 'answered' => 0], $validated['players']),
                'question_index' => 0,
                'score' => 0,
                'quiz_category' => $category,
            ]);
            $request->session()->forget(['resultat', 'quiz_question_ids']);
        } else {
            $request->session()->put([
                'connect_four_players' => $validated['players'],
                'connect_four' => $connectFour->newGame(),
            ]);
        }

        return to_route($isQuiz ? 'quiz' : 'connect-four.show');
    }
}
