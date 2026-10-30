<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use App\Services\Games\QuizCategories;
use Database\Seeders\QuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(QuestionSeeder::class);
        $this->actingAs(User::factory()->create(['quiz_upgraded_at' => now()]));
    }

    public function test_categories_show_real_counts_and_every_theme_can_support_six_players(): void
    {
        $choices = QuizCategories::choices();
        $this->assertCount(13, $choices);
        $this->assertSame(Question::inCategory('general')->count(), $choices['general']['count']);
        $this->assertSame(Question::where('child_friendly', true)->count(), $choices['kids']['count']);
        $this->assertSame(0, Question::whereNull('category')->count());
        foreach ($choices as $key => $choice) {
            $this->assertGreaterThanOrEqual(24, $choice['count'], $key);
            $this->assertSame(Question::inCategory($key)->count(), $choice['count']);
        }
        $this->get('/quiz')->assertViewIs('games.players')->assertViewHas('quizCategories', $choices)
            ->assertSee('Choose a category')->assertSee('Kids discovery');
    }

    public function test_every_category_filters_the_random_order_and_survives_restart(): void
    {
        foreach (array_keys(config('quiz.categories')) as $category) {
            $this->post('/games/quiz/players', ['category' => $category, 'player_count' => 2, 'players' => ['Alice', 'Bob']])
                ->assertSessionHasNoErrors()->assertSessionHas('quiz_category', $category);
            $expected = intdiv(min(Question::inCategory($category)->count(), 20), 2) * 2;
            $response = $this->get('/quiz')->assertViewIs('quiz')->assertViewHas('total', $expected);
            $ids = session('quiz_question_ids');
            $this->assertCount($expected, $ids);
            $this->assertCount($expected, array_unique($ids));
            $this->assertSame($expected, Question::inCategory($category)->whereIn('id', $ids)->count());
            $this->assertSame($ids[0], $response->viewData('question')->id);

            $this->post('/quiz/restart')->assertSessionHas('quiz_category', $category);
            $this->get('/quiz')->assertViewHas('total', $expected);
            $this->assertSame($expected, Question::inCategory($category)->whereIn('id', session('quiz_question_ids'))->count());
        }
    }

    public function test_invalid_or_unavailable_categories_do_not_replace_the_current_game(): void
    {
        $this->post('/games/quiz/players', ['category' => 'geography', 'player_count' => 1, 'players' => ['Alice']]);
        $this->get('/quiz');
        $ids = session('quiz_question_ids');
        $this->post('/games/quiz/players', ['category' => 'unknown', 'player_count' => 1, 'players' => ['Bob']])
            ->assertSessionHasErrors('category')->assertSessionHas('quiz_category', 'geography')
            ->assertSessionHas('quiz_players.0.name', 'Alice')->assertSessionHas('quiz_question_ids', $ids);
        Question::where('category', 'history')->delete();
        $this->post('/games/quiz/players', ['category' => 'history', 'player_count' => 1, 'players' => ['Bob']])
            ->assertSessionHasErrors('player_count')->assertSessionHas('quiz_category', 'geography')
            ->assertSessionHas('quiz_question_ids', $ids);
    }

    public function test_questions_outside_the_selected_category_cannot_be_submitted(): void
    {
        $this->post('/games/quiz/players', ['category' => 'nature', 'player_count' => 1, 'players' => ['Alice']]);
        $question = $this->get('/quiz')->viewData('question');
        $outside = Question::where('category', 'history')->firstOrFail();
        $this->post('/quiz/verified', ['question_id' => $outside->id, 'reponse' => $question->answer])
            ->assertSessionHas('question_index', 0)->assertSessionHas('score', 0);
        $this->assertSame(0, auth()->user()->fresh()->quiz_questions_answered);
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $question->answer])
            ->assertSessionHas('question_index', 1)->assertSessionHas('score', 1);
    }

    public function test_category_and_question_follow_language_changes_without_resetting_progress(): void
    {
        $this->post('/games/quiz/players', ['category' => 'health', 'player_count' => 1, 'players' => ['Alice']]);
        $question = $this->get('/quiz')->viewData('question');
        foreach (['fr' => 'Santé et corps humain', 'de' => 'Gesundheit und Körper'] as $locale => $label) {
            $this->patch(route('preferences.update'), ['locale' => $locale]);
            $response = $this->get('/quiz')->assertSee($label)->assertSessionHas('quiz_category', 'health');
            $localized = $response->viewData('question');
            $this->assertSame($question->id, $localized->id);
            $this->assertSame($question->translations[$locale]['question'], $localized->question);
        }
    }

    public function test_childrens_selection_contains_only_curated_accessible_questions(): void
    {
        $this->post('/games/quiz/players', ['category' => 'kids', 'player_count' => 1, 'players' => ['Alice']]);
        $this->get('/quiz')->assertViewIs('quiz');
        $questions = Question::whereIn('id', session('quiz_question_ids'))->get();
        $this->assertTrue($questions->every(fn ($question) => $question->child_friendly));
        $this->assertGreaterThan(3, $questions->pluck('category')->unique()->count());
        $this->assertSame(0, $questions->whereIn('category', ['chemical-symbols', 'atomic-numbers'])->count());
    }
}
