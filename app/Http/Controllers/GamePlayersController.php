<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Services\Games\ConnectFourGame;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GamePlayersController extends Controller
{
    public function show(Request $request, string $game): View
    {
        return view('games.players', ['gameType' => $game]);
    }

    public function store(Request $request, string $game, ConnectFourGame $connectFour): RedirectResponse
    {
        $isQuiz = $game === 'quiz';
        $validated = $request->validate([
            'player_count' => ['required', 'integer', $isQuiz ? 'between:1,6' : 'in:2'],
            'players' => ['required', 'array', 'list', 'size:'.$request->integer('player_count')],
            'players.*' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
        ]);

        if ($isQuiz) {
            if (Question::count() < count($validated['players'])) {
                return back()->withInput()->withErrors(['player_count' => __('Not enough questions for this number of players.')]);
            }
            $request->session()->put([
                'quiz_players' => array_map(fn (string $name) => ['name' => $name, 'score' => 0, 'answered' => 0], $validated['players']),
                'question_index' => 0,
                'score' => 0,
            ]);
            $request->session()->forget('resultat');
        } else {
            $request->session()->put([
                'connect_four_players' => $validated['players'],
                'connect_four' => $connectFour->newGame(),
            ]);
        }

        return to_route($isQuiz ? 'quiz' : 'connect-four.show');
    }
}
