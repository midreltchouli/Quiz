<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Games\ConnectFourGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectFourTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['connect_four_players' => ['Alice', 'Bob']]);
    }

    public function test_guests_cannot_access_or_change_a_game(): void
    {
        $this->get('/puissance-4')->assertRedirect('/login');
        $this->post('/puissance-4/play', ['column' => 0, 'move' => 0])->assertRedirect('/login');
        $this->post('/puissance-4/restart')->assertRedirect('/login');
    }

    public function test_game_is_linked_from_home_and_renders_in_french(): void
    {
        $this->actingAs(User::factory()->create(['locale' => 'fr']));
        $this->get('/accueil')->assertOk()->assertSee(route('connect-four.show'));
        $this->get('/puissance-4')->assertOk()->assertViewIs('games.connect-four')
            ->assertSee('Puissance 4')->assertSee('À Alice de jouer')
            ->assertSessionHas('connect_four.moves', 0);
    }

    public function test_play_and_restart_preserve_quiz_progress_and_billing(): void
    {
        $user = User::factory()->create(['quiz_questions_answered' => 10]);
        $this->actingAs($user)->withSession(['question_index' => 8, 'score' => 6]);
        $this->get('/puissance-4')->assertOk();
        $this->post('/puissance-4/play', ['column' => 3, 'move' => 0])
            ->assertRedirect('/puissance-4')->assertSessionHas('connect_four.board.5.3', 'red');
        $this->get('/puissance-4')->assertOk()->assertSessionHas('connect_four.moves', 1);
        $this->post('/puissance-4/restart')->assertRedirect('/puissance-4')
            ->assertSessionHas('connect_four.moves', 0)
            ->assertSessionHas('connect_four', fn (array $state) => $state['board'][5][3] === null)
            ->assertSessionHas('question_index', 8)->assertSessionHas('score', 6);
        $this->assertSame(10, $user->fresh()->quiz_questions_answered);
    }

    public function test_invalid_columns_and_stale_moves_do_not_change_board(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/puissance-4');
        foreach ([-1, 7, 'invalid'] as $column) {
            $this->from('/puissance-4')->post('/puissance-4/play', ['column' => $column, 'move' => 0])
                ->assertSessionHasErrors('column')->assertSessionHas('connect_four.moves', 0);
        }
        $this->post('/puissance-4/play', ['column' => 0, 'move' => 0])->assertSessionHas('connect_four.moves', 1);
        $this->post('/puissance-4/play', ['column' => 1, 'move' => 0])
            ->assertSessionHasErrors('column')->assertSessionHas('connect_four.moves', 1);
    }

    public function test_full_columns_and_finished_games_show_errors(): void
    {
        $this->actingAs(User::factory()->create());
        $game = new ConnectFourGame;
        $state = $game->newGame();
        for ($move = 0; $move < 6; $move++) {
            $state = $game->play($state, 0);
        }
        $this->withSession(['connect_four' => $state])->from('/puissance-4')
            ->post('/puissance-4/play', ['column' => 0, 'move' => 6])
            ->assertSessionHasErrors('column')->assertSessionHas('connect_four.moves', 6);

        $state = $game->newGame();
        foreach ([0, 6, 1, 6, 2, 5, 3] as $column) {
            $state = $game->play($state, $column);
        }
        $this->withSession(['connect_four' => $state])->get('/puissance-4')->assertOk()->assertSee('winning');
        $this->post('/puissance-4/play', ['column' => 4, 'move' => 7])
            ->assertSessionHasErrors('column')->assertSessionHas('connect_four.moves', 7);
    }
}
