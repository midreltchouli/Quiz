<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\User;
use Database\Seeders\QuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_quiz_is_not_displayed_as_completed(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['question_index' => 0, 'score' => 0])
            ->get('/quiz')
            ->assertOk()
            ->assertViewIs('quiz_empty')
            ->assertSessionMissing('question_index');
    }

    public function test_seeded_questions_start_the_quiz_and_are_not_duplicated(): void
    {
        $this->seed(QuestionSeeder::class);
        $total = Question::count();
        $this->seed(QuestionSeeder::class);
        $this->assertGreaterThanOrEqual(1000, $total);
        $this->assertSame($total, Question::count());

        $this->actingAs(User::factory()->create())->get('/quiz')->assertViewIs('games.players');
        $this->post(route('games.players.store', ['game' => 'quiz']), ['player_count' => 1, 'players' => ['Alice']])->assertRedirect('/quiz');
        $this->get('/quiz')
            ->assertOk()
            ->assertViewIs('quiz')
            ->assertViewHas('number', 1)
            ->assertViewHas('total', 10);
    }

    public function test_the_results_are_displayed_after_the_last_question(): void
    {
        $this->seed(QuestionSeeder::class);
        $total = Question::inCategory('general')->count();

        $this->actingAs(User::factory()->create())
            ->withSession(['question_index' => $total, 'score' => 12])
            ->get('/quiz')
            ->assertOk()
            ->assertViewIs('quiz_finish')
            ->assertViewHas('score', 12)
            ->assertViewHas('total', $total);
    }

    public function test_start_again_resets_a_completed_quiz_to_the_first_question(): void
    {
        $this->seed(QuestionSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->withSession(['question_index' => Question::inCategory('general')->count(), 'score' => 12, 'resultat' => 'Previous answer'])
            ->get('/quiz')
            ->assertViewIs('quiz_finish')
            ->assertSee(route('quiz.restart'), false);

        $this->get('/quiz')->assertViewIs('quiz_finish')->assertViewHas('score', 12);

        $this->post(route('quiz.restart'))
            ->assertRedirect('/quiz')
            ->assertSessionHas('question_index', 0)
            ->assertSessionHas('score', 0)
            ->assertSessionMissing('quiz_question_ids')
            ->assertSessionMissing('resultat');

        $this->get('/quiz')
            ->assertOk()
            ->assertViewIs('quiz')
            ->assertViewHas('number', 1)
            ->assertViewHas('question', fn ($question) => $question->id === session('quiz_question_ids.0'))
            ->assertSessionHas('score', 0);
    }

    public function test_question_order_is_stable_on_refresh_and_has_no_repetitions(): void
    {
        $this->seed(QuestionSeeder::class);
        Question::whereNotIn('id', Question::inCategory('general')->orderBy('id')->limit(12)->pluck('id'))->delete();
        $user = User::factory()->create(['quiz_upgraded_at' => now()]);
        $this->actingAs($user)->post('/games/quiz/players', ['player_count' => 1, 'players' => ['Alice'], 'length' => 20]);

        $first = $this->get('/quiz')->viewData('question');
        $ids = session('quiz_question_ids');
        $this->assertCount(12, $ids);
        $this->assertSame(count($ids), count(array_unique($ids)));
        $this->assertEqualsCanonicalizing(Question::pluck('id')->all(), $ids);
        $this->get('/quiz')->assertViewHas('question', fn ($question) => $question->id === $first->id);
        $this->assertSame($ids, session('quiz_question_ids'));

        foreach ($ids as $index => $id) {
            $question = $this->get('/quiz')->assertViewHas('number', $index + 1)->viewData('question');
            $this->assertSame($id, $question->id);
            $this->post('/quiz/verified', ['question_id' => $id, 'reponse' => $question->answer])
                ->assertSessionHasNoErrors()->assertSessionHas('question_index', $index + 1);
            $this->post(route('quiz.continue'))->assertRedirect('/quiz');
        }

        $this->get('/quiz')->assertViewIs('quiz_finish')->assertViewHas('score', 12);
        $this->assertSame(12, $user->fresh()->quiz_questions_answered);
    }

    public function test_answers_follow_the_saved_order_and_new_questions_wait_until_next_game(): void
    {
        $this->seed(QuestionSeeder::class);
        $total = 10;
        $ids = Question::inCategory('general')->orderByDesc('id')->limit(10)->pluck('id')->all();
        $this->actingAs(User::factory()->create())
            ->post('/games/quiz/players', ['player_count' => 1, 'players' => ['Alice']]);
        QuizAttempt::findOrFail(session('quiz_attempt_id'))->update(['question_ids' => $ids]);
        $current = Question::findOrFail($ids[0]);
        $other = Question::findOrFail($ids[1]);

        Question::create(['question' => 'An added question', 'options' => ['Yes', 'No'], 'answer' => 'Yes']);
        $this->get('/quiz')->assertViewHas('total', $total)
            ->assertViewHas('question', fn ($question) => $question->id === $current->id);
        $this->post('/quiz/verified', ['question_id' => $other->id, 'reponse' => $current->answer])
            ->assertSessionHas('question_index', 0)->assertSessionHas('score', 0);
        $this->post('/quiz/verified', ['question_id' => $current->id, 'reponse' => $current->answer])
            ->assertSessionHas('question_index', 1)->assertSessionHas('score', 1);
        $previous = session('quiz_attempt_id');
        $this->post('/quiz/restart');
        $this->assertNotSame($previous, session('quiz_attempt_id'));
        $this->get('/quiz')->assertViewHas('total', 10);
    }

    public function test_all_seeded_questions_have_complete_translations_and_valid_answers(): void
    {
        $this->seed(QuestionSeeder::class);
        foreach (Question::cursor() as $question) {
            foreach (['en', 'fr', 'de'] as $locale) {
                $this->assertArrayHasKey($locale, $question->translations);
                $localized = $question->forLocale($locale);
                $this->assertNotEmpty($localized->question);
                $this->assertCount(4, array_unique($localized->options));
                $this->assertContains($localized->answer, $localized->options);
            }
        }
    }

    public function test_changing_language_translates_the_same_question_and_checks_the_localized_answer(): void
    {
        $this->seed(QuestionSeeder::class);
        $user = User::factory()->create(['locale' => 'en']);
        $this->actingAs($user)->post('/games/quiz/players', ['player_count' => 1, 'players' => ['Alice']]);
        $english = $this->get('/quiz')->viewData('question');
        $ids = session('quiz_question_ids');
        $stored = Question::findOrFail($english->id);

        foreach (['fr', 'de', 'en'] as $locale) {
            $this->patch(route('preferences.update'), ['locale' => $locale, 'return_to' => 'home']);
            $response = $this->get('/quiz')->assertViewIs('quiz');
            $localized = $response->viewData('question');
            $this->assertSame($english->id, $localized->id);
            $this->assertSame($stored->translations[$locale]['question'], $localized->question);
            $this->assertSame($stored->translations[$locale]['options'], $localized->options);
            $this->assertSame($ids, session('quiz_question_ids'));
        }

        $this->patch(route('preferences.update'), ['locale' => 'fr']);
        $french = $this->get('/quiz')->viewData('question');
        $this->post('/quiz/verified', ['question_id' => $french->id, 'reponse' => $french->answer])
            ->assertSessionHasNoErrors()->assertSessionHas('score', 1)->assertSessionHas('question_index', 1);
        $this->assertSame($stored->getRawOriginal('question'), $stored->fresh()->question);
    }

    public function test_answer_validation_uses_the_selected_language(): void
    {
        $this->seed(QuestionSeeder::class);
        $question = Question::where('bank_key', 'original:31')->firstOrFail();
        $user = User::factory()->create(['locale' => 'fr']);
        $this->actingAs($user)->post('/games/quiz/players', ['player_count' => 1, 'players' => ['Alice']]);
        QuizAttempt::findOrFail(session('quiz_attempt_id'))->update(['question_ids' => [$question->id]]);
        $this->get('/quiz')
            ->assertSee('Cœur')->assertDontSee('>Heart<', false);
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => 'Heart'])
            ->assertSessionHasErrors('reponse')->assertSessionHas('question_index', 0);
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => 'Cœur'])
            ->assertSessionHas('score', 1)->assertSessionHas('question_index', 1);
        $this->assertSame(1, $user->fresh()->quiz_questions_answered);
    }
}
