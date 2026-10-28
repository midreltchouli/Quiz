<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Database\Seeders\QuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizDifficultyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(QuestionSeeder::class);
        $this->actingAs(User::factory()->create(['locale' => 'fr', 'quiz_upgraded_at' => now()]));
    }

    public function test_standard_categories_exclude_introductory_questions_and_children_keep_them(): void
    {
        $printer = Question::where('bank_key', 'learning:printer')->firstOrFail();
        $this->assertSame('easy', $printer->difficulty);
        $this->assertTrue($printer->child_friendly);
        foreach (array_keys(config('quiz.categories')) as $category) {
            if ($category === 'kids') {
                continue;
            }
            $this->assertFalse(Question::inCategory($category)->where('difficulty', 'easy')->exists());
        }
        $this->post('/games/quiz/players', ['category' => 'technology', 'player_count' => 1, 'players' => ['Alice']]);
        $this->get('/quiz')->assertViewIs('quiz');
        $this->assertNotContains($printer->id, session('quiz_question_ids'));

        $this->post('/games/quiz/players', ['category' => 'kids', 'player_count' => 1, 'players' => ['Alice']]);
        $this->get('/quiz')->assertViewIs('quiz');
        $this->assertContains($printer->id, session('quiz_question_ids'));
    }

    public function test_new_challenges_strengthen_each_previously_small_category(): void
    {
        foreach (['history', 'nature', 'space', 'health', 'technology', 'sports'] as $category) {
            $this->assertGreaterThanOrEqual(24, Question::inCategory($category)->where('bank_key', 'like', 'challenge:%')->count(), $category);
        }
        $this->assertSame(60, Question::inCategory('math')->where('bank_key', 'like', 'challenge:%')->count());
        $this->assertFalse(Question::whereNotIn('difficulty', ['easy', 'medium', 'hard'])->exists());
    }

    public function test_math_answers_are_correct_for_representative_reasoning_exercises(): void
    {
        foreach ([
            'combinations-6' => '20', 'dice-7' => '1/6', 'derivative-3' => '12',
            'geometric-sum-2' => '80', 'determinant-3' => '6', 'logarithm-6' => '6',
            'without-replacement-3' => '1/7', 'hexadecimal-47' => '2F',
            'modular-5' => '2', 'arithmetic-sum-3' => '440',
        ] as $key => $answer) {
            $question = Question::where('bank_key', 'challenge:'.$key)->firstOrFail();
            foreach (['fr', 'en', 'de'] as $locale) {
                $this->assertSame($answer, $question->forLocale($locale)->answer);
                $this->assertNotEmpty($question->translations[$locale]['explanation']);
            }
        }
    }

    public function test_answer_feedback_explains_the_calculation_in_the_selected_language(): void
    {
        $question = Question::where('bank_key', 'challenge:combinations-6')->firstOrFail();
        $this->post('/games/quiz/players', ['category' => 'math', 'player_count' => 1, 'players' => ['Alice']]);
        $this->withSession(['quiz_question_ids' => [$question->id]])->get('/quiz')->assertSee('Difficile');
        $wrong = collect($question->options)->first(fn ($option) => $option !== $question->answer);
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $wrong])
            ->assertSessionHas('score', 0)->assertSessionHas('question_index', 1)
            ->assertSessionHas('resultat', fn ($message) => str_contains($message, 'C(6,3)') && str_contains($message, '20'));
    }
}
