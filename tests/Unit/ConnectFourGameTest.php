<?php

namespace Tests\Unit;

use App\Services\Games\ConnectFourGame;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConnectFourGameTest extends TestCase
{
    public function test_discs_fall_and_players_alternate(): void
    {
        $game = new ConnectFourGame;
        $state = $game->play($game->newGame(), 2);
        $this->assertSame('red', $state['board'][5][2]);
        $this->assertSame('yellow', $state['current_player']);
        $state = $game->play($state, 2);
        $this->assertSame('yellow', $state['board'][4][2]);
        $this->assertSame('red', $state['current_player']);
        $this->assertSame(2, $state['moves']);
    }

    #[DataProvider('winningMoves')]
    public function test_detects_victories(array $columns, string $winner): void
    {
        $game = new ConnectFourGame;
        $state = $game->newGame();
        foreach ($columns as $column) {
            $state = $game->play($state, $column);
        }
        $this->assertSame('won', $state['status']);
        $this->assertSame($winner, $state['winner']);
        $this->assertCount(4, $state['winning_cells']);
    }

    public static function winningMoves(): array
    {
        return [
            'horizontal' => [[0, 6, 1, 6, 2, 5, 3], 'red'],
            'vertical' => [[0, 1, 0, 1, 0, 1, 2, 1], 'yellow'],
            'diagonal rising right' => [[0, 1, 1, 2, 4, 2, 2, 3, 4, 3, 5, 3, 3], 'red'],
            'diagonal rising left' => [[6, 5, 5, 4, 2, 4, 4, 3, 2, 3, 1, 3, 3], 'red'],
        ];
    }

    public function test_rejects_a_full_column(): void
    {
        $game = new ConnectFourGame;
        $state = $game->newGame();
        for ($i = 0; $i < 6; $i++) {
            $state = $game->play($state, 0);
        }
        $this->expectException(ValidationException::class);
        $game->play($state, 0);
    }

    public function test_rejects_moves_after_victory(): void
    {
        $game = new ConnectFourGame;
        $state = $game->newGame();
        foreach ([0, 6, 1, 6, 2, 5, 3] as $column) {
            $state = $game->play($state, $column);
        }
        $this->expectException(ValidationException::class);
        $game->play($state, 4);
    }

    public function test_full_board_without_alignment_is_a_draw(): void
    {
        $game = new ConnectFourGame;
        $state = $game->newGame();
        $state['board'] = array_map(
            fn (string $row) => array_map(fn (string $cell) => $cell === 'R' ? 'red' : 'yellow', str_split($row)),
            ['RRYYRRY', 'YYRRYYR', 'YYRRYYR', 'RRYYRRY', 'RRYYRRY', 'YYRRYYR'],
        );
        $state['board'][0][0] = null;
        $state['moves'] = 41;
        $state = $game->play($state, 0);
        $this->assertSame('draw', $state['status']);
        $this->assertNull($state['winner']);
    }
}
