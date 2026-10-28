<?php

namespace Tests\Feature\Settings;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['billing.stripe_secret' => 'sk_test_example']);
        Http::preventStrayRequests();
    }

    private function purchase(User $user, array $attributes = []): Payment
    {
        return $user->payments()->create(array_merge([
            'stripe_checkout_session_id' => 'cs_test_'.uniqid(),
            'stripe_payment_intent_id' => 'pi_test_purchase',
            'amount' => 100,
            'currency' => 'eur',
            'status' => 'paid',
            'paid_at' => now(),
        ], $attributes));
    }

    private function intent(array $attributes = []): array
    {
        return array_replace([
            'id' => 'pi_test_purchase',
            'status' => 'succeeded',
            'amount_received' => 100,
            'currency' => 'eur',
            'latest_charge' => ['receipt_url' => 'https://pay.stripe.com/receipts/payment/test-receipt'],
        ], $attributes);
    }

    public function test_purchases_and_receipts_require_login(): void
    {
        $this->get(route('purchases.index'))->assertRedirect(route('login'));
        $this->get(route('purchases.receipt', 1))->assertRedirect(route('login'));
    }

    public function test_only_own_purchases_are_shown_and_menu_order_is_correct(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);
        $purchase = $this->purchase($user);
        $other = $this->purchase(User::factory()->create());

        $this->actingAs($user)->get(route('purchases.index'))->assertOk()
            ->assertSeeInOrder(['Profil', 'Achats et abonnement', 'Paramètres'])
            ->assertSee('Mes achats')
            ->assertSee($purchase->stripe_checkout_session_id)
            ->assertDontSee($other->stripe_checkout_session_id)
            ->assertSee(route('purchases.receipt', $purchase->id))
            ->assertSee('1,00 EUR');
        Http::assertNothingSent();
    }

    public function test_empty_history_and_german_labels_are_displayed(): void
    {
        $this->actingAs(User::factory()->create(['locale' => 'de']))
            ->get(route('purchases.index'))->assertOk()
            ->assertSee('Meine Käufe')->assertSee('Du hast noch keine Käufe.')
            ->assertSee('Abonnements werden hier später verfügbar sein.');
    }

    public function test_search_and_status_filters_stay_scoped_to_the_account(): void
    {
        $user = User::factory()->create();
        $match = $this->purchase($user, ['stripe_checkout_session_id' => 'cs_test_matching']);
        $hidden = $this->purchase($user, ['status' => 'pending', 'paid_at' => null]);
        $other = $this->purchase(User::factory()->create(), ['stripe_payment_intent_id' => 'pi_matching']);

        $this->actingAs($user)->get(route('purchases.index', ['q' => 'matching', 'status' => 'paid']))
            ->assertOk()->assertSee($match->stripe_checkout_session_id)
            ->assertDontSee($hidden->stripe_checkout_session_id)
            ->assertDontSee($other->stripe_checkout_session_id);
        $this->get(route('purchases.index', ['q' => 'nonexistent']))->assertOk()
            ->assertSee('No purchases match your search.');
    }

    public function test_pagination_keeps_filters(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 11; $i++) {
            $this->purchase($user, ['stripe_checkout_session_id' => 'cs_page_'.$i]);
        }

        $this->actingAs($user)->get(route('purchases.index', ['q' => 'cs_page_', 'status' => 'paid']))
            ->assertOk()->assertViewHas('payments', fn ($payments) => $payments->count() === 10
                && str_contains($payments->nextPageUrl(), 'status=paid')
                && str_contains($payments->nextPageUrl(), 'q=cs_page_'));
    }

    public function test_other_accounts_receipts_are_inaccessible_without_contacting_stripe(): void
    {
        $purchase = $this->purchase(User::factory()->create());
        $this->actingAs(User::factory()->create())->get(route('purchases.receipt', $purchase->id))
            ->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_unconfirmed_purchases_have_no_receipt(): void
    {
        $user = User::factory()->create();
        $purchase = $this->purchase($user, ['status' => 'pending', 'paid_at' => null]);
        $this->actingAs($user)->get(route('purchases.index'))->assertOk()
            ->assertDontSee(route('purchases.receipt', $purchase->id));
        $this->get(route('purchases.receipt', $purchase->id))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_confirmed_purchase_opens_its_stripe_receipt(): void
    {
        $user = User::factory()->create();
        $purchase = $this->purchase($user);
        Http::fake(['api.stripe.com/v1/payment_intents/*' => Http::response($this->intent())]);

        $this->actingAs($user)->get(route('purchases.receipt', $purchase->id))
            ->assertRedirect('https://pay.stripe.com/receipts/payment/test-receipt');
        Http::assertSent(fn ($request) => $request['expand'] === ['latest_charge']);
    }

    public function test_receipt_can_recover_a_missing_intent_from_the_checkout_session(): void
    {
        $user = User::factory()->create();
        $purchase = $this->purchase($user, ['stripe_payment_intent_id' => null]);
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/*' => Http::response(['payment_intent' => 'pi_test_purchase']),
            'api.stripe.com/v1/payment_intents/*' => Http::response($this->intent()),
        ]);

        $this->actingAs($user)->get(route('purchases.receipt', $purchase->id))
            ->assertRedirect('https://pay.stripe.com/receipts/payment/test-receipt');
        Http::assertSentCount(2);
    }

    public function test_missing_receipts_provider_errors_and_unexpected_urls_show_a_message(): void
    {
        $user = User::factory()->create();
        $purchase = $this->purchase($user);
        $this->actingAs($user);
        foreach ([
            Http::response([], 500),
            Http::response($this->intent(['latest_charge' => null])),
            Http::response($this->intent(['latest_charge' => ['receipt_url' => 'https://example.com/receipt']])),
            Http::response($this->intent(['amount_received' => 250])),
        ] as $response) {
            Http::fake(['api.stripe.com/v1/payment_intents/*' => $response]);
            $this->get(route('purchases.receipt', $purchase->id))->assertRedirect(route('purchases.index'))
                ->assertSessionHas('purchase_error');
        }
        $this->assertSame('paid', $purchase->fresh()->status);
    }

    public function test_missing_stripe_configuration_does_not_break_purchase_history(): void
    {
        config(['billing.stripe_secret' => null]);
        $user = User::factory()->create();
        $purchase = $this->purchase($user);
        $this->actingAs($user)->get(route('purchases.index'))->assertOk();
        $this->get(route('purchases.receipt', $purchase->id))->assertRedirect(route('purchases.index'))
            ->assertSessionHas('purchase_error');
        Http::assertNothingSent();
    }
}
