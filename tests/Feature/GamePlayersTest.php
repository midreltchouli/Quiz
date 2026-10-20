<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Database\Seeders\QuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePlayersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_configure_players_and_unknown_games_are_rejected(): void
    {
        $this->get('/games/quiz/players')->assertRedirect('/login');
        $this->post('/games/connect-four/players')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/games/unknown/players')->assertNotFound();
    }

    public function test_each_game_initially_shows_player_selection(): void
    {
        $this->seed(QuestionSeeder::class);
        $this->actingAs(User::factory()->create(['locale' => 'fr']));
        foreach (['/quiz', '/puissance-4'] as $url) {
            $this->get($url)->assertOk()->assertViewIs('games.players')->assertSee('Choisir les joueurs');
        }
        $this->get('/games/quiz/players?player_count=4')->assertOk()->assertSee('value="4" selected', false);
    }

    public function test_connect_four_uses_the_selected_names_and_preserves_them_on_restart(): void
    {
        $this->actingAs(User::factory()->create(['locale' => 'fr']));
        $this->post('/games/connect-four/players', ['player_count' => 2, 'players' => ['Alice', 'Bob']])
            ->assertRedirect('/puissance-4')->assertSessionHas('connect_four_players', ['Alice', 'Bob']);
        $this->get('/puissance-4')->assertSee('À Alice de jouer');
        $this->post('/puissance-4/play', ['column' => 0, 'move' => 0]);
        $this->get('/puissance-4')->assertSee('À Bob de jouer');
        $this->post('/puissance-4/restart')->assertSessionHas('connect_four.moves', 0)
            ->assertSessionHas('connect_four_players', ['Alice', 'Bob']);
    }

    public function test_connect_four_cannot_start_with_one_or_more_than_two_players(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([['Alice'], ['Alice', 'Bob', 'Claire']] as $players) {
            $this->post('/games/connect-four/players', ['player_count' => count($players), 'players' => $players])
                ->assertSessionHasErrors('player_count')->assertSessionMissing('connect_four');
        }
    }

    public function test_names_and_counts_are_validated_before_replacing_a_game(): void
    {
        $this->seed(QuestionSeeder::class);
        $this->actingAs(User::factory()->create());
        foreach ([
            ['player_count' => 0, 'players' => []],
            ['player_count' => 7, 'players' => array_fill(0, 7, 'Alice')],
            ['player_count' => 2, 'players' => ['Alice']],
            ['player_count' => 2, 'players' => ['Alice', 'alice']],
            ['player_count' => 2, 'players' => ['Alice', '   ']],
            ['player_count' => 1, 'players' => [str_repeat('a', 41)]],
        ] as $input) {
            $this->post('/games/quiz/players', $input)->assertSessionHasErrors()->assertSessionMissing('quiz_players');
        }
    }

    public function test_quiz_alternates_players_tracks_scores_and_finishes_with_equal_rounds(): void
    {
        $this->seed(QuestionSeeder::class);
        // An odd bank of five questions gives two players two questions each.
        Question::whereNotIn('id', Question::orderBy('id')->limit(5)->pluck('id'))->delete();
        $user = User::factory()->create();
        $this->actingAs($user)->post('/games/quiz/players', ['player_count' => 2, 'players' => ['Alice', 'Bob']]);
        $questions = Question::orderBy('id')->get();
        foreach ($questions->take(4) as $index => $question) {
            $this->get('/quiz')->assertViewHas('currentPlayer', $index % 2)->assertViewHas('total', 4);
            $answer = $index % 2 === 0 ? $question->answer : collect($question->options)->first(fn ($option) => $option !== $question->answer);
            $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $answer])->assertRedirect('/quiz');
        }
        $this->get('/quiz')->assertViewIs('quiz_finish')->assertSee('Winner(s): Alice')
            ->assertSessionHas('quiz_players.0.score', 2)->assertSessionHas('quiz_players.1.score', 0)
            ->assertSessionHas('quiz_players.0.answered', 2)->assertSessionHas('quiz_players.1.answered', 2);
        $this->post('/quiz/verified', ['question_id' => $questions[4]->id, 'reponse' => $questions[4]->answer])
            ->assertSessionHas('question_index', 4);
        $this->assertSame(4, $user->fresh()->quiz_questions_answered);
    }

    public function test_solo_and_six_player_quizzes_are_supported(): void
    {
        $this->seed(QuestionSeeder::class);
        $this->actingAs(User::factory()->create());
        foreach ([['Alice'], ['Alice', 'Bob', 'Claire', 'David', 'Emma', 'Fred']] as $players) {
            $this->post('/games/quiz/players', ['player_count' => count($players), 'players' => $players])
                ->assertSessionHasNoErrors()->assertRedirect('/quiz');
            $this->get('/quiz')->assertViewIs('quiz')->assertViewHas('players', fn ($value) => count($value) === count($players));
        }
    }

    public function test_reconfiguration_is_isolated_and_does_not_reset_account_allowance(): void
    {
        $this->seed(QuestionSeeder::class);
        $user = User::factory()->create(['quiz_questions_answered' => 8]);
        $this->actingAs($user)->post('/games/quiz/players', ['player_count' => 2, 'players' => ['Alice', 'Bob']]);
        $question = Question::first();
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $question->answer]);
        $this->post('/games/connect-four/players', ['player_count' => 2, 'players' => ['Claire', 'David']])
            ->assertSessionHas('quiz_players.0.score', 1)->assertSessionHas('question_index', 1);
        $this->post('/puissance-4/play', ['column' => 0, 'move' => 0]);
        $this->get('/games/quiz/players')->assertOk()->assertSessionHas('question_index', 1);
        $this->post('/games/quiz/players', ['player_count' => 1, 'players' => ['Emma']])
            ->assertSessionHas('connect_four.moves', 1)->assertSessionHas('connect_four_players', ['Claire', 'David'])
            ->assertSessionHas('quiz_players.0.score', 0);
        $this->assertSame(9, $user->fresh()->quiz_questions_answered);
    }

    public function test_shared_free_limit_pauses_multiplayer_and_keeps_the_next_turn(): void
    {
        $this->seed(QuestionSeeder::class);
        config(['billing.free_question_limit' => 1]);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/games/quiz/players', ['player_count' => 2, 'players' => ['Alice', 'Bob']]);
        $question = Question::first();
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $question->answer]);
        $this->get('/quiz')->assertViewIs('quiz_upgrade')->assertSee('Alice')->assertSee('Bob');
        $next = Question::orderBy('id')->skip(1)->first();
        $this->post('/quiz/verified', ['question_id' => $next->id, 'reponse' => $next->answer])
            ->assertSessionHas('question_index', 1)->assertSessionHas('quiz_players.1.answered', 0);
        $user->forceFill(['quiz_upgraded_at' => now()])->save();
        $this->get('/quiz')->assertViewIs('quiz')->assertViewHas('currentPlayer', 1);
    }

    public function test_quiz_restart_resets_each_score_and_duplicate_answers_do_not_advance_turns(): void
    {
        $this->seed(QuestionSeeder::class);
        $this->actingAs(User::factory()->create())->post('/games/quiz/players', ['player_count' => 2, 'players' => ['Alice', 'Bob']]);
        $question = Question::first();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $question->answer]);
        }
        $this->get('/quiz')->assertSessionHas('question_index', 1)->assertSessionHas('quiz_players.0.score', 1)
            ->assertSessionHas('quiz_players.1.answered', 0);
        $this->post('/quiz/restart')->assertSessionHas('quiz_players.0.name', 'Alice')
            ->assertSessionHas('quiz_players.0.score', 0)->assertSessionHas('quiz_players.0.answered', 0)
            ->assertSessionHas('question_index', 0);
    }

    public function test_games_cannot_accept_moves_before_player_selection(): void
    {
        $this->seed(QuestionSeeder::class);
        $this->actingAs(User::factory()->create());
        $question = Question::first();
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $question->answer])
            ->assertSessionMissing('question_index');
        $this->post('/puissance-4/play', ['column' => 0, 'move' => 0])->assertSessionMissing('connect_four');
    }

    public function test_insufficient_questions_do_not_replace_an_existing_configuration(): void
    {
        $this->seed(QuestionSeeder::class);
        $this->actingAs(User::factory()->create())->post('/games/quiz/players', ['player_count' => 1, 'players' => ['Alice']]);
        Question::where('id', '!=', Question::min('id'))->delete();
        $this->from('/games/quiz/players')->post('/games/quiz/players', ['player_count' => 2, 'players' => ['Bob', 'Claire']])
            ->assertSessionHasErrors('player_count')->assertSessionHas('quiz_players.0.name', 'Alice');
    }

    public function test_named_winner_is_shown_and_player_names_are_escaped(): void
    {
        $this->actingAs(User::factory()->create());
        $name = '<b>Alice</b>';
        $this->post('/games/connect-four/players', ['player_count' => 2, 'players' => [$name, 'Bob']]);
        foreach ([0, 6, 1, 6, 2, 5, 3] as $move => $column) {
            $this->post('/puissance-4/play', ['column' => $column, 'move' => $move]);
        }
        $this->get('/puissance-4')->assertOk()->assertSee($name.' wins!')
            ->assertDontSee($name, false)->assertSessionHas('connect_four.status', 'won');
    }
}
