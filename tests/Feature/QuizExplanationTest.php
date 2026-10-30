<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Database\Seeders\QuestionExplanationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuizExplanationTest extends TestCase
{
    use RefreshDatabase;

    private function historyQuestion(string $key = 'challenge:montesquieu'): Question
    {
        $bank = json_decode(file_get_contents(database_path('seeders/data/challenge-questions.json')), true, flags: JSON_THROW_ON_ERROR);
        $entry = collect($bank['questions'])->firstWhere('key', $key);

        return Question::create($entry['translations']['en'] + [
            'bank_key' => $entry['key'], 'translations' => $entry['translations'],
            'category' => $entry['category'], 'difficulty' => $entry['difficulty'],
        ]);
    }

    private function start(Question $question, bool $last = false, ?User $user = null): User
    {
        $user ??= User::factory()->create(['locale' => 'de', 'quiz_upgraded_at' => now()]);
        $ids = [$question->id];
        if (! $last) {
            $next = Question::create(['question' => 'NEXT QUESTION MUST STAY HIDDEN', 'options' => ['A', 'B'], 'answer' => 'A']);
            $ids[] = $next->id;
        }
        $this->actingAs($user)->withSession([
            'quiz_players' => [['name' => 'Alice', 'score' => 0, 'answered' => 0]],
            'quiz_question_ids' => $ids, 'question_index' => 0, 'score' => 0,
            'quiz_category' => 'history',
        ]);

        return $user;
    }

    public function test_twenty_existing_questions_are_enriched_without_inserts_or_answer_changes(): void
    {
        $bank = json_decode(file_get_contents(database_path('seeders/data/history-explanations.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(20, $bank['questions']);
        $before = [];
        foreach (array_keys($bank['questions']) as $key) {
            $question = $this->historyQuestion($key);
            $before[$key] = [$question->question, $question->options, $question->answer];
        }
        $this->seed(QuestionExplanationSeeder::class);
        $this->seed(QuestionExplanationSeeder::class);
        $this->assertDatabaseCount('questions', 20);
        foreach (Question::all() as $question) {
            $this->assertSame($before[$question->bank_key], [$question->question, $question->options, $question->answer]);
            $this->assertStringStartsWith('https://', $question->source_url);
            foreach (['en', 'fr', 'de'] as $locale) {
                $this->assertNotEmpty($question->translations[$locale]['explanation']);
            }
        }
    }

    public function test_review_is_shown_only_after_answering_and_persists_until_continue(): void
    {
        Http::preventStrayRequests();
        $question = $this->historyQuestion();
        $this->seed(QuestionExplanationSeeder::class);
        $user = $this->start($question);
        $this->get('/quiz')->assertOk()->assertDontSee('Montesquieu schrieb')
            ->assertDontSee('href="https://de.wikipedia.org', false);
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => 'Montesquieu'])
            ->assertRedirect('/quiz')->assertSessionHas('question_index', 1);
        $this->get('/quiz')->assertOk()->assertSee('Montesquieu schrieb')
            ->assertSee('Quelle lesen')->assertSee($question->fresh()->source_url)
            ->assertDontSee('NEXT QUESTION MUST STAY HIDDEN');
        $this->get('/quiz')->assertSee('Montesquieu schrieb');
        $this->assertSame(1, $user->fresh()->quiz_questions_answered);
        $this->post(route('quiz.continue'))->assertRedirect('/quiz')->assertSessionMissing('quiz_review');
        $this->get('/quiz')->assertSee('NEXT QUESTION MUST STAY HIDDEN')->assertDontSee('Montesquieu schrieb');
        $this->assertSame(1, $user->fresh()->quiz_questions_answered);
        Http::assertNothingSent();
    }

    public function test_last_question_explanation_is_readable_before_results(): void
    {
        $question = $this->historyQuestion();
        $this->seed(QuestionExplanationSeeder::class);
        $this->start($question, last: true);
        $wrong = $question->forLocale('de')->options[0];
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $wrong]);
        $this->get('/quiz')->assertOk()->assertSee('Die Antwort ist falsch.')
            ->assertSee('Montesquieu schrieb')->assertDontSee('Quiz abgeschlossen!');
        $this->post(route('quiz.continue'));
        $this->get('/quiz')->assertViewIs('quiz_finish')->assertViewHas('score', 0)->assertDontSee('Montesquieu schrieb');
    }

    public function test_free_limit_does_not_hide_the_last_free_answers_explanation(): void
    {
        config(['billing.free_question_limit' => 1]);
        $question = $this->historyQuestion();
        $this->seed(QuestionExplanationSeeder::class);
        $this->start($question, user: User::factory()->create(['locale' => 'fr']));
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => 'Montesquieu']);
        $this->get('/quiz')->assertOk()->assertSee('Montesquieu a écrit')
            ->assertSee('https://www.bnf.fr/fr/le-manuscrit-de-lesprit-des-lois-par-montesquieu')
            ->assertDontSee('name="locale"', false);
        $this->post(route('quiz.continue'));
        $this->get('/quiz')->assertViewIs('quiz_upgrade')->assertDontSee('Montesquieu a écrit');
    }

    public function test_disabling_feedback_skips_the_review_and_reset_clears_it(): void
    {
        $question = $this->historyQuestion();
        $this->seed(QuestionExplanationSeeder::class);
        $user = User::factory()->create(['quiz_notifications' => false, 'quiz_upgraded_at' => now()]);
        $this->start($question, user: $user);
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => 'Montesquieu'])
            ->assertSessionMissing('quiz_review');
        $this->get('/quiz')->assertSee('NEXT QUESTION MUST STAY HIDDEN');
        $this->withSession(['quiz_review' => ['question_id' => $question->id]])
            ->post(route('quiz.restart'))->assertSessionMissing('quiz_review');
    }

    public function test_unsafe_source_links_are_not_rendered_and_explanation_text_is_escaped(): void
    {
        $question = $this->historyQuestion();
        $translations = $question->translations;
        $translations['de']['explanation'] = '<script>alert("test")</script>';
        $question->update(['translations' => $translations, 'source_url' => 'javascript:alert(1)']);
        $this->start($question);
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => 'Montesquieu']);
        $this->get('/quiz')->assertOk()->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert', false)->assertDontSee('javascript:alert', false);
    }

    public function test_continue_requires_login(): void
    {
        $this->post(route('quiz.continue'))->assertRedirect(route('login'));
    }
}
