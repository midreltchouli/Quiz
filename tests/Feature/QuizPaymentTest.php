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

    public function test_first_complete_game_finishes_before_upgrade_is_requested(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/quiz')->assertViewIs('games.players');
        $this->post(route('games.players.store', ['game' => 'quiz']), ['player_count' => 1, 'players' => ['Alice']])->assertRedirect('/quiz');
        $this->get('/quiz')->assertViewIs('quiz');
        for ($index = 0; $index < 10; $index++) {
            $question = $this->get('/quiz')->assertViewIs('quiz')->viewData('question');
            $this->post('/quiz/verified', ['question_id' => $question->id, 'reponse' => $question->answer])
                ->assertRedirect('/quiz');
            $this->post(route('quiz.continue'))->assertRedirect('/quiz');
        }
        $this->get('/quiz')->assertViewIs('quiz_finish')
            ->assertSessionHas('question_index', 10)->assertSessionHas('score', 10);
        $this->assertSame(10, $user->fresh()->quiz_questions_answered);
        $this->post('/quiz/restart');
        $this->get('/quiz')->assertViewIs('quiz_upgrade')->assertSee('1,00 EUR');
        $next = Question::findOrFail(session('quiz_question_ids.0'));
        $this->post('/quiz/verified', ['question_id' => $next->id, 'reponse' => $next->answer])
            ->assertSessionHas('question_index', 0)->assertSessionHas('score', 0);
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
        $question = $this->actingAs($user)->withSession(['question_index' => 10, 'score' => 4])->get('/quiz')
            ->assertViewIs('quiz')->assertViewHas('number', 11)->viewData('question');
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
        Question::whereNotIn('id', Question::inCategory('general')->orderBy('id')->limit(10)->pluck('id'))->delete();
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
        $user = User::factory()->create();
        $this->actingAs($user)->get('/quiz');
        $this->post(route('games.players.store', ['game' => 'quiz']), ['player_count' => 1, 'players' => ['Alice']]);
        $question = $this->get('/quiz')->viewData('question');
        $wrong = collect($question->options)->first(fn ($option) => $option !== $question->answer);
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
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'stripe_checkout_session_id' => 'cs_test_quiz',
            'amount' => 100,
            'currency' => 'eur',
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }

    private function checkoutUser(): User
    {
        $user = User::factory()->create(['quiz_questions_answered' => 10, 'quiz_checkout_session_id' => 'cs_test_quiz',
            'quiz_checkout_amount' => 100, 'quiz_checkout_currency' => 'eur']);
        $user->payments()->create([
            'stripe_checkout_session_id' => 'cs_test_quiz',
            'amount' => 100,
            'currency' => 'eur',
            'status' => 'pending',
        ]);

        return $user;
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
        $payment = $user->payments()->sole();
        $this->assertSame('paid', $payment->status);
        $paidAt = $payment->paid_at;
        $this->assertNotNull($paidAt);
        $this->travel(1)->minutes();
        $this->sendWebhook($this->paidSession($user))->assertNoContent();
        $this->assertTrue($upgradedAt->equalTo($user->fresh()->quiz_upgraded_at));
        $this->assertSame(1, $user->payments()->count());
        $this->assertTrue($paidAt->equalTo($payment->fresh()->paid_at));
        $this->actingAs($user)->withSession(['question_index' => 10, 'score' => 5])->get('/quiz')
            ->assertViewIs('quiz')->assertViewHas('number', 11);
    }

    public function test_unpaid_wrong_price_and_wrong_user_payments_do_not_unlock_access(): void
    {
        $user = $this->checkoutUser();
        foreach (['payment_status' => 'unpaid', 'amount_total' => 1, 'currency' => 'usd', 'client_reference_id' => '999', 'id' => 'cs_other'] as $key => $value) {
            $this->sendWebhook(array_replace($this->paidSession($user), [$key => $value]))->assertNoContent();
            $this->assertNull($user->fresh()->quiz_upgraded_at);
            $this->assertSame('pending', $user->payments()->sole()->status);
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
        $this->assertSame('failed', $user->payments()->sole()->status);
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_retry', 'url' => 'https://checkout.stripe.com/retry',
        ])]);
        $this->actingAs($user)->post(route('quiz.checkout'))->assertRedirect('https://checkout.stripe.com/retry');
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'stripe_checkout_session_id' => 'cs_retry',
            'status' => 'pending',
        ]);
    }

    public function test_success_return_verifies_payment_with_stripe_and_preserves_progress(): void
    {
        $user = $this->checkoutUser();
        // Sessions started before payment history existed must still be recorded.
        $user->payments()->delete();
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_test_quiz' => Http::response($this->paidSession($user))]);
        $this->actingAs($user)->withSession(['question_index' => 10, 'score' => 6])
            ->get(route('quiz.payment.success', ['session_id' => 'cs_test_quiz']))
            ->assertRedirect('/quiz')->assertSessionHas('question_index', 10)->assertSessionHas('score', 6);
        $this->assertNotNull($user->fresh()->quiz_upgraded_at);
        $this->assertSame('paid', $user->payments()->sole()->status);
        $this->get(route('quiz.payment.success', ['session_id' => 'cs_other']))->assertForbidden();
    }

    public function test_expired_checkout_is_recorded_before_creating_a_new_attempt(): void
    {
        $user = $this->checkoutUser();
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_test_quiz' => Http::response(['status' => 'expired']),
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_retry', 'url' => 'https://checkout.stripe.com/retry',
            ]),
        ]);

        $this->actingAs($user)->post(route('quiz.checkout'))
            ->assertRedirect('https://checkout.stripe.com/retry');

        $this->assertDatabaseHas('payments', [
            'stripe_checkout_session_id' => 'cs_test_quiz', 'status' => 'expired',
        ]);
        $this->assertDatabaseHas('payments', [
            'stripe_checkout_session_id' => 'cs_retry', 'status' => 'pending',
        ]);
    }

    public function test_late_failure_does_not_overwrite_a_confirmed_payment(): void
    {
        $user = $this->checkoutUser();
        $session = $this->paidSession($user);
        $session['payment_intent'] = 'pi_test_quiz';
        $this->sendWebhook($session)->assertNoContent();
        $this->sendWebhook($session, 'checkout.session.async_payment_failed')->assertNoContent();

        $payment = $user->payments()->sole();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('pi_test_quiz', $payment->stripe_payment_intent_id);
        $this->assertNotNull($user->fresh()->quiz_upgraded_at);
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
