<?php

namespace Tests\Feature;

use App\Models\Question;
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
        $this->seed(QuestionSeeder::class);
        $this->assertSame(30, Question::count());

        $this->actingAs(User::factory()->create())->get('/quiz')
            ->assertOk()
            ->assertViewIs('quiz')
            ->assertViewHas('number', 1)
            ->assertViewHas('total', 30);
    }

    public function test_the_results_are_displayed_after_the_last_question(): void
    {
        $this->seed(QuestionSeeder::class);

        $this->actingAs(User::factory()->create())
            ->withSession(['question_index' => 30, 'score' => 12])
            ->get('/quiz')
            ->assertOk()
            ->assertViewIs('quiz_finish')
            ->assertViewHas('score', 12)
            ->assertViewHas('total', 30);
    }

    public function test_start_again_resets_a_completed_quiz_to_the_first_question(): void
    {
        $this->seed(QuestionSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->withSession(['question_index' => 30, 'score' => 12, 'resultat' => 'Previous answer'])
            ->get('/quiz')
            ->assertViewIs('quiz_finish')
            ->assertSee(route('quiz.restart'), false);

        $this->get('/quiz')->assertViewIs('quiz_finish')->assertViewHas('score', 12);

        $this->post(route('quiz.restart'))
            ->assertRedirect('/quiz')
            ->assertSessionMissing('question_index')
            ->assertSessionMissing('score')
            ->assertSessionMissing('resultat');

        $this->get('/quiz')
            ->assertOk()
            ->assertViewIs('quiz')
            ->assertViewHas('number', 1)
            ->assertViewHas('question', fn ($question) => $question->id === Question::min('id'))
            ->assertSessionHas('score', 0);
    }
}
