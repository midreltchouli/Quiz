<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuizProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        for ($i = 1; $i <= 45; $i++) {
            Question::create([
                'question' => 'Question '.$i, 'options' => ['Yes', 'No'], 'answer' => 'Yes',
                'category' => 'science', 'difficulty' => 'medium', 'child_friendly' => true,
                'translations' => ['en' => ['question' => 'Question '.$i, 'options' => ['Yes', 'No'], 'answer' => 'Yes', 'explanation' => 'A useful explanation.']],
                'source_url' => 'https://example.org/source',
            ]);
        }
    }

    private function finish(bool $wrong = false): QuizAttempt
    {
        while (true) {
            $response = $this->get('/quiz')->assertOk();
            if (QuizAttempt::findOrFail(session('quiz_attempt_id'))->completed_at) {
                $response->assertViewIs('quiz_finish');
                break;
            }
            $question = $response->viewData('question');
            $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $wrong ? 'No' : 'Yes'])->assertSessionHasNoErrors();
            $this->post(route('quiz.continue'));
        }

        return QuizAttempt::findOrFail(session('quiz_attempt_id'));
    }

    public function test_public_landing_has_account_actions_and_a_translated_demo_without_signup(): void
    {
        $this->get('/')->assertOk()->assertViewIs('welcome-games')->assertSee(route('register'), false)->assertSee(route('login'), false);
        $this->post(route('public.language'), ['locale' => 'fr'])->assertSessionHas('locale', 'fr');
        $this->get('/')->assertSee('Cinq minutes pour jouer.')->assertSee('Essayer 5 questions');
        $this->post(route('public.language'), ['locale' => 'invalid'])->assertSessionHasErrors('locale');
        $this->assertGuest();
    }

    public function test_registration_keeps_the_language_chosen_on_the_public_page(): void
    {
        $this->post(route('public.language'), ['locale' => 'fr']);
        $this->post(route('register.store'), [
            'name' => 'Camille', 'email' => 'camille@example.com', 'password' => 'password123',
        ])->assertRedirect(route('accueil'));
        $this->assertSame('fr', User::firstOrFail()->locale);
        $this->get('/accueil')->assertOk()->assertSee('Défi quotidien');
    }

    public function test_demo_checks_answers_and_duplicates_without_writing_account_progress(): void
    {
        $this->post(route('quiz.demo.start'))->assertRedirect(route('quiz.demo'));
        $question = $this->get(route('quiz.demo'))->assertOk()->viewData('question');
        $this->get(route('quiz.demo'))->assertDontSee('A useful explanation.');
        $this->post(route('quiz.demo.answer'), ['question_id' => $question->id, 'answer' => 'Invalid'])->assertSessionHasErrors('answer');
        $this->post(route('quiz.demo.answer'), ['question_id' => 999999, 'answer' => 'Yes'])->assertSessionHas('quiz_demo.index', 0);
        for ($i = 0; $i < 5; $i++) {
            $question = $this->get(route('quiz.demo'))->viewData('question');
            $payload = ['question_id' => $question->id, 'answer' => 'Yes'];
            $this->post(route('quiz.demo.answer'), $payload);
            $this->post(route('quiz.demo.answer'), $payload)->assertSessionHas('quiz_demo.index', $i + 1);
            $this->get(route('quiz.demo'))->assertSee('A useful explanation.');
            $this->post(route('quiz.demo.continue'));
        }
        $this->get(route('quiz.demo'))->assertSee('Your discovery round is complete!')->assertSee('5 / 5');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('quiz_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_short_games_have_equal_turns_and_length_is_validated(): void
    {
        $this->actingAs(User::factory()->create(['quiz_upgraded_at' => now()]));
        $this->post('/games/quiz/players', ['player_count' => 1, 'players' => ['Alice'], 'length' => 1000])->assertSessionHasErrors('length');
        foreach ([5, 10, 20] as $length) {
            $this->post('/games/quiz/players', ['player_count' => 2, 'players' => ['Alice', 'Bob'], 'length' => $length])->assertSessionHasNoErrors();
            $this->get('/quiz')->assertViewHas('total', $length * 2);
            $this->assertCount($length * 2, array_unique(session('quiz_question_ids')));
        }
    }

    public function test_trial_can_finish_twenty_questions_but_restart_cannot_reset_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/games/quiz/players', ['player_count' => 1, 'players' => ['Alice'], 'length' => 20]);
        $attempt = $this->finish();
        $this->assertTrue($attempt->is_trial);
        $this->assertSame(20, $attempt->score);
        $this->assertSame(20, $user->fresh()->quiz_questions_answered);
        $this->get('/quiz')->assertViewIs('quiz_finish')->assertSee(route('progress.show', $attempt), false);
        $this->get('/quiz');
        $this->assertDatabaseCount('quiz_attempts', 1);
        $this->post('/quiz/restart');
        $this->get('/quiz')->assertViewIs('quiz_upgrade');
        $this->assertSame(1, QuizAttempt::where('is_trial', true)->count());
    }

    public function test_progress_is_private_resumable_and_survives_bank_changes(): void
    {
        $user = User::factory()->create(['quiz_upgraded_at' => now()]);
        $this->actingAs($user)->post('/games/quiz/players', ['player_count' => 1, 'players' => ['Alice'], 'length' => 5]);
        $question = $this->get('/quiz')->viewData('question');
        $payload = ['question_id' => $question->id, 'reponse' => 'No'];
        $this->post('/quiz/verified', $payload);
        $this->post('/quiz/verified', $payload);
        $id = session('quiz_attempt_id');
        $this->assertCount(1, QuizAttempt::findOrFail($id)->answers);
        $this->flushSession();
        $this->get('/accueil')->assertSee('Pick up where you left off');
        $this->post(route('progress.resume', $id))->assertSessionHas('question_index', 1);
        $attempt = $this->finish(wrong: true);
        $this->get('/progress')->assertSee('0 %')->assertSee('0 / 5');
        $this->get(route('progress.show', $attempt))->assertSee('A useful explanation.')->assertSee('Mistakes to revisit');
        Question::query()->delete();
        $this->get(route('progress.show', $attempt))->assertOk()->assertSee('A useful explanation.');
        $this->actingAs(User::factory()->create());
        $this->get(route('progress.show', $attempt))->assertNotFound();
        $this->post(route('progress.resume', $attempt))->assertNotFound();
        $this->get('/progress')->assertDontSee('Alice');
        $this->assertSame(5, $user->fresh()->quiz_questions_answered);
    }

    public function test_daily_is_free_stable_and_unique_even_in_another_session(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(10));
        $user = User::factory()->create(['quiz_questions_answered' => 99]);
        $this->actingAs($user)->post(route('quiz.daily'));
        $ids = session('quiz_question_ids');
        $attempt = $this->finish();
        $this->assertCount(5, $ids);
        $this->assertSame(99, $user->fresh()->quiz_questions_answered);
        $this->flushSession();
        $this->post(route('quiz.daily'))->assertSessionHas('quiz_attempt_id', $attempt->id);
        $this->get('/quiz')->assertViewIs('quiz_finish');
        $this->assertDatabaseCount('quiz_attempts', 1);
        Question::create(['question' => 'New question', 'options' => ['Yes', 'No'], 'answer' => 'Yes', 'difficulty' => 'medium']);
        $this->actingAs(User::factory()->create())->post(route('quiz.daily'));
        $this->assertSame($ids, session('quiz_question_ids'));
        $this->assertSame(1, DB::table('daily_quizzes')->count());
        $this->travel(1)->days();
        $this->post(route('quiz.daily'));
        $this->get('/quiz')->assertViewIs('quiz')->assertViewHas('number', 1);
        $this->assertSame(2, DB::table('daily_quizzes')->count());
        Http::assertNothingSent();
    }

    public function test_history_escapes_snapshots_and_rejects_unsafe_source_links(): void
    {
        $question = Question::firstOrFail();
        $translations = $question->translations;
        $translations['en']['question'] = '<script>alert(1)</script>';
        $question->update(['source_url' => 'javascript:alert(1)', 'question' => '<script>alert(1)</script>', 'translations' => $translations]);
        $this->actingAs(User::factory()->create(['quiz_upgraded_at' => now()]));
        $this->post('/games/quiz/players', ['player_count' => 1, 'players' => ['Alice'], 'length' => 5]);
        $attempt = QuizAttempt::findOrFail(session('quiz_attempt_id'));
        $attempt->update(['question_ids' => [$question->id]]);
        $attempt = $this->finish();
        $this->get(route('progress.show', $attempt))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false)->assertDontSee('javascript:alert', false);
    }
}
