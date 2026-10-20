<?php

namespace App\Services\Games;

use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type Board array<int, array<int, 'red'|'yellow'|null>>
 * @phpstan-type GameState array{board: Board, current_player: 'red'|'yellow', winner: 'red'|'yellow'|null, status: 'playing'|'won'|'draw', winning_cells: list<array{int, int}>, moves: int}
 */
class ConnectFourGame
{
    /** @return GameState */
    public function newGame(): array
    {
        return [
            'board' => array_fill(0, 6, array_fill(0, 7, null)),
            'current_player' => 'red',
            'winner' => null,
            'status' => 'playing',
            'winning_cells' => [],
            'moves' => 0,
        ];
    }

    /**
     * @param  GameState  $state
     * @return GameState
     */
    public function play(array $state, int $column): array
    {
        if ($state['status'] !== 'playing') {
            throw ValidationException::withMessages(['column' => __('This game is over. Start a new game.')]);
        }

        if ($column < 0 || $column > 6 || $state['board'][0][$column] !== null) {
            throw ValidationException::withMessages(['column' => __('Choose a column with an empty space.')]);
        }

        for ($row = 5; $row >= 0; $row--) {
            if ($state['board'][$row][$column] === null) {
                $state['board'][$row][$column] = $state['current_player'];
                break;
            }
        }

        $state['moves']++;
        $state['winning_cells'] = $this->winningCells($state['board'], $row, $column);

        if ($state['winning_cells'] !== []) {
            $state['winner'] = $state['current_player'];
            $state['status'] = 'won';
        } elseif ($state['moves'] === 42) {
            $state['status'] = 'draw';
        } else {
            $state['current_player'] = $state['current_player'] === 'red' ? 'yellow' : 'red';
        }

        return $state;
    }

    /**
     * @param  Board  $board
     * @return list<array{int, int}>
     */
    private function winningCells(array $board, int $row, int $column): array
    {
        $player = $board[$row][$column];

        foreach ([[0, 1], [1, 0], [1, 1], [1, -1]] as [$dr, $dc]) {
            $cells = [[$row, $column]];
            foreach ([-1, 1] as $direction) {
                $r = $row + $dr * $direction;
                $c = $column + $dc * $direction;
                while ($r >= 0 && $r < 6 && $c >= 0 && $c < 7 && $board[$r][$c] === $player) {
                    $cells[] = [$r, $c];
                    $r += $dr * $direction;
                    $c += $dc * $direction;
                }
            }
            if (count($cells) >= 4) {
                return $cells;
            }
        }

        return [];
    }
}
