<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayConnectFourRequest;
use App\Services\Games\ConnectFourGame;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConnectFourController extends Controller
{
    public function show(Request $request, ConnectFourGame $game): View
    {
        if (! $request->session()->has('connect_four_players')) {
            return view('games.players', ['gameType' => 'connect-four']);
        }
        $state = $request->session()->get('connect_four', $game->newGame());
        $request->session()->put('connect_four', $state);

        return view('games.connect-four', ['game' => $state, 'players' => $request->session()->get('connect_four_players')]);
    }

    public function play(PlayConnectFourRequest $request, ConnectFourGame $game): RedirectResponse
    {
        if (! $request->session()->has('connect_four_players')) {
            return to_route('connect-four.show');
        }
        $state = $request->session()->get('connect_four', $game->newGame());
        if ($request->integer('move') !== $state['moves']) {
            return to_route('connect-four.show')->withErrors(['column' => __('The board has changed. Please play again.')]);
        }

        $request->session()->put('connect_four', $game->play($state, $request->integer('column')));

        return to_route('connect-four.show');
    }

    public function restart(Request $request, ConnectFourGame $game): RedirectResponse
    {
        if (! $request->session()->has('connect_four_players')) {
            return to_route('connect-four.show');
        }
        $request->session()->put('connect_four', $game->newGame());

        return to_route('connect-four.show');
    }
}
