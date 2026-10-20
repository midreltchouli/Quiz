<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Database\Seeders\QuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuizPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(QuestionSeeder::class);
        config(['billing.free_question_limit' => 10, 'billing.amount' => 100, 'billing.currency' => 'eur',
            'billing.stripe_secret' => 'sk_test_example', 'billing.stripe_webhook_secret' => 'whsec_example']);
        Http::preventStrayRequests();
    }

    public function test_tenth_answer_shows_upgrade_and_keeps_progress(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/quiz')->assertViewIs('games.players');
        $this->post(route('games.players.store', ['game' => 'quiz']), ['player_count' => 1, 'players' => ['Alice']])->assertRedirect('/quiz');
        $this->get('/quiz')->assertViewIs('quiz');
        foreach (Question::orderBy('id')->limit(10)->get() as $question) {
            $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $question->answer])
                ->assertRedirect('/quiz');
        }
        $this->get('/quiz')->assertViewIs('quiz_upgrade')->assertSee('1,00 EUR')
            ->assertSessionHas('question_index', 10)->assertSessionHas('score', 10);
        $this->assertSame(10, $user->fresh()->quiz_questions_answered);
        $next = Question::orderBy('id')->skip(10)->first();
        $this->post('/quiz/verified', ['question_id' => $next->id, 'reponse' => $next->answer])
            ->assertSessionHas('question_index', 10)->assertSessionHas('score', 10);
        $this->assertSame(10, $user->fresh()->quiz_questions_answered);
    }

    public function test_restarting_or_a_new_session_does_not_reset_free_allowance(): void
    {
        $user = User::factory()->create(['quiz_questions_answered' => 10]);
        $this->actingAs($user)->withSession(['question_index' => 10, 'score' => 7]);
        $this->post(route('quiz.restart'))->assertRedirect('/quiz');
        $this->get('/quiz')->assertViewIs('quiz_upgrade');
        $this->flushSession();
        $this->get('/quiz')->assertViewIs('quiz_upgrade');
        $this->assertSame(10, $user->fresh()->quiz_questions_answered);
    }

    public function test_paid_accounts_can_answer_question_eleven(): void
    {
        $user = User::factory()->create(['quiz_questions_answered' => 10, 'quiz_upgraded_at' => now()]);
        $question = Question::orderBy('id')->skip(10)->first();
        $this->actingAs($user)->withSession(['question_index' => 10, 'score' => 4])->get('/quiz')
            ->assertViewIs('quiz')->assertViewHas('number', 11);
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $question->answer])
            ->assertSessionHas('question_index', 11)->assertSessionHas('score', 5);
    }

    public function test_free_limit_and_upgrade_price_are_configurable(): void
    {
        config(['billing.free_question_limit' => 2, 'billing.amount' => 250]);
        $user = User::factory()->create(['quiz_questions_answered' => 2]);
        $this->actingAs($user)->get('/quiz')->assertViewIs('quiz_upgrade')
            ->assertViewHas('limit', 2)->assertSee('2,50 EUR');
    }

    public function test_quizzes_with_at_most_ten_questions_can_be_finished_for_free(): void
    {
        Question::where('id', '>', Question::orderBy('id')->skip(9)->first()->id)->delete();
        $user = User::factory()->create(['quiz_questions_answered' => 10]);
        $this->actingAs($user)->withSession(['question_index' => 10, 'score' => 8])->get('/quiz')
            ->assertViewIs('quiz_finish')->assertViewHas('total', 10)->assertViewHas('score', 8);
    }

    public function test_guests_cannot_submit_answers_or_start_checkout(): void
    {
        $this->post('/quiz/verified')->assertRedirect(route('login'));
        $this->post(route('quiz.checkout'))->assertRedirect(route('login'));
    }

    public function test_answers_are_checked_on_the_server_and_stale_answers_are_ignored(): void
    {
        $question = Question::orderBy('id')->first();
        $wrong = collect($question->options)->first(fn ($option) => $option !== $question->answer);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/quiz');
        $this->post(route('games.players.store', ['game' => 'quiz']), ['player_count' => 1, 'players' => ['Alice']]);
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $wrong, 'bonne_reponse' => $wrong])
            ->assertSessionHas('score', 0)->assertSessionHas('question_index', 1);
        $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $question->answer]);
        $this->assertSame(1, $user->fresh()->quiz_questions_answered);
    }

    public function test_checkout_uses_server_price_and_dynamic_payment_methods(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_quiz', 'url' => 'https://checkout.stripe.com/test'])]);
        $user = User::factory()->create(['quiz_questions_answered' => 10]);
        $this->actingAs($user)->post(route('quiz.checkout'), ['amount' => 1, 'user_id' => 999])
            ->assertRedirect('https://checkout.stripe.com/test');
        Http::assertSent(fn ($request) => $request['mode'] === 'payment'
            && $request['line_items'][0]['price_data']['unit_amount'] === 100
            && $request['line_items'][0]['price_data']['currency'] === 'eur'
            && $request['metadata']['user_id'] === (string) $user->id
            && ! isset($request['payment_method_types']));
        $this->assertNull($user->fresh()->quiz_upgraded_at);
        $this->assertSame('cs_test_quiz', $user->fresh()->quiz_checkout_session_id);
    }

    private function checkoutUser(): User
    {
        return User::factory()->create(['quiz_questions_answered' => 10, 'quiz_checkout_session_id' => 'cs_test_quiz',
            'quiz_checkout_amount' => 100, 'quiz_checkout_currency' => 'eur']);
    }

    public function test_existing_open_checkout_is_reused(): void
    {
        $user = $this->checkoutUser();
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_test_quiz' => Http::response([
            'status' => 'open', 'url' => 'https://checkout.stripe.com/existing',
        ])]);
        $this->actingAs($user)->post(route('quiz.checkout'))->assertRedirect('https://checkout.stripe.com/existing');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
        $this->assertNull($user->fresh()->quiz_upgraded_at);
    }

    private function paidSession(User $user): array
    {
        return ['id' => 'cs_test_quiz', 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'paid',
            'amount_total' => 100, 'currency' => 'eur', 'client_reference_id' => (string) $user->id,
            'metadata' => ['user_id' => (string) $user->id, 'purpose' => 'quiz_upgrade']];
    }

    private function sendWebhook(array $session, string $type = 'checkout.session.completed', int $age = 0)
    {
        $payload = json_encode(['type' => $type, 'data' => ['object' => $session]], JSON_THROW_ON_ERROR);
        $timestamp = time() - $age;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_example');

        return $this->call('POST', route('stripe.webhook'), [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature], $payload);
    }

    public function test_signed_paid_webhook_unlocks_quiz_even_without_browser_return_and_is_idempotent(): void
    {
        $user = $this->checkoutUser();
        $this->sendWebhook($this->paidSession($user))->assertNoContent();
        $upgradedAt = $user->fresh()->quiz_upgraded_at;
        $this->assertNotNull($upgradedAt);
        $this->travel(1)->minutes();
        $this->sendWebhook($this->paidSession($user))->assertNoContent();
        $this->assertTrue($upgradedAt->equalTo($user->fresh()->quiz_upgraded_at));
        $this->actingAs($user)->withSession(['question_index' => 10, 'score' => 5])->get('/quiz')
            ->assertViewIs('quiz')->assertViewHas('number', 11);
    }

    public function test_unpaid_wrong_price_and_wrong_user_payments_do_not_unlock_access(): void
    {
        $user = $this->checkoutUser();
        foreach (['payment_status' => 'unpaid', 'amount_total' => 1, 'currency' => 'usd', 'client_reference_id' => '999', 'id' => 'cs_other'] as $key => $value) {
            $this->sendWebhook(array_replace($this->paidSession($user), [$key => $value]))->assertNoContent();
            $this->assertNull($user->fresh()->quiz_upgraded_at);
        }
    }

    public function test_invalid_or_old_webhook_signatures_are_rejected(): void
    {
        $user = $this->checkoutUser();
        $this->postJson(route('stripe.webhook'), ['data' => ['object' => $this->paidSession($user)]])->assertStatus(400);
        $this->sendWebhook($this->paidSession($user), age: 301)->assertStatus(400);
        $this->assertNull($user->fresh()->quiz_upgraded_at);
    }

    public function test_delayed_payment_only_unlocks_on_confirmed_success(): void
    {
        $user = $this->checkoutUser();
        $this->sendWebhook(array_replace($this->paidSession($user), ['payment_status' => 'unpaid']))->assertNoContent();
        $this->assertNull($user->fresh()->quiz_upgraded_at);
        $this->sendWebhook($this->paidSession($user), 'checkout.session.async_payment_succeeded')->assertNoContent();
        $this->assertNotNull($user->fresh()->quiz_upgraded_at);
    }

    public function test_failed_delayed_payment_allows_a_new_checkout(): void
    {
        $user = $this->checkoutUser();
        $this->sendWebhook(array_replace($this->paidSession($user), ['payment_status' => 'unpaid']), 'checkout.session.async_payment_failed')
            ->assertNoContent();
        $this->assertNull($user->fresh()->quiz_upgraded_at);
        $this->assertNull($user->fresh()->quiz_checkout_session_id);
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_retry', 'url' => 'https://checkout.stripe.com/retry',
        ])]);
        $this->actingAs($user)->post(route('quiz.checkout'))->assertRedirect('https://checkout.stripe.com/retry');
    }

    public function test_success_return_verifies_payment_with_stripe_and_preserves_progress(): void
    {
        $user = $this->checkoutUser();
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_test_quiz' => Http::response($this->paidSession($user))]);
        $this->actingAs($user)->withSession(['question_index' => 10, 'score' => 6])
            ->get(route('quiz.payment.success', ['session_id' => 'cs_test_quiz']))
            ->assertRedirect('/quiz')->assertSessionHas('question_index', 10)->assertSessionHas('score', 6);
        $this->assertNotNull($user->fresh()->quiz_upgraded_at);
        $this->get(route('quiz.payment.success', ['session_id' => 'cs_other']))->assertForbidden();
    }

    public function test_missing_keys_and_provider_errors_keep_account_free(): void
    {
        $user = User::factory()->create(['quiz_questions_answered' => 10]);
        $this->actingAs($user);
        config(['billing.stripe_secret' => null]);
        $this->get('/quiz')->assertViewIs('quiz_upgrade')->assertSee('disabled', false);
        $this->post(route('quiz.checkout'))->assertRedirect('/quiz')->assertSessionHas('payment_error');
        Http::assertNothingSent();
        config(['billing.stripe_secret' => 'sk_test_example']);
        Http::fake(['api.stripe.com/*' => Http::response([], 500)]);
        $this->post(route('quiz.checkout'))->assertRedirect('/quiz')->assertSessionHas('payment_error');
        $this->assertNull($user->fresh()->quiz_upgraded_at);
    }
}
