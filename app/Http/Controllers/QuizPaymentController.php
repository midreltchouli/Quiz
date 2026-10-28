<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class QuizPaymentController extends Controller
{
    private function stripe()
    {
        return Http::withToken(config('billing.stripe_secret'))->asForm()->timeout(15);
    }

    //the checkout method is used to initiate the payment process for a quiz upgrade.
    //  It checks if the user has reached their free quiz limit and if the Stripe configuration is set up correctly.
    //  If both conditions are met, it creates a new checkout session with Stripe or reuses an existing one if available. 
    // The user is then redirected to the Stripe checkout page to complete the payment.   
    public function checkout(Request $request)
    {
        if (! $request->user()->refresh()->hasReachedFreeQuizLimit()) {
            return redirect()->route('quiz');
        }
        if (blank(config('billing.stripe_secret')) || blank(config('billing.stripe_webhook_secret'))) {
            return redirect()->route('quiz')->with('payment_error', __('Payment is not available yet.'));
        }

        try {
            // Serialize attempts and reuse an open checkout to avoid charging twice.
            $url = DB::transaction(function () use ($request) {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                if ($user->quiz_upgraded_at) {
                    return null;
                }
                if ($user->quiz_checkout_session_id) {
                    $existing = $this->stripe()->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($user->quiz_checkout_session_id))->throw()->json();
                    if ($existing['status'] === 'expired') {
                        $user->payments()
                            ->where('stripe_checkout_session_id', $user->quiz_checkout_session_id)
                            ->where('status', 'pending')
                            ->update(['status' => 'expired']);
                    }
                    if ($existing['status'] === 'open') {
                        return $existing['url'];
                    }
                    if ($existing['status'] === 'complete') {
                        $this->activate($existing);

                        return null;
                    }
                }
                $amount = (int) config('billing.amount');
                $currency = config('billing.currency');
                $session = $this->stripe()->post('https://api.stripe.com/v1/checkout/sessions', [
                    'mode' => 'payment',
                    'customer_email' => $user->email,
                    'client_reference_id' => (string) $user->id,
                    'metadata' => ['user_id' => (string) $user->id, 'purpose' => 'quiz_upgrade'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => $amount,
                            'product_data' => ['name' => __('Full quiz access')],
                        ],
                        'quantity' => 1,
                    ]],
                    // Omit payment_method_types: Stripe uses the methods enabled in its dashboard.
                    'success_url' => route('quiz.payment.success').'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('quiz'),
                ])->throw()->json();
                $user->forceFill([
                    'quiz_checkout_session_id' => $session['id'],
                    'quiz_checkout_amount' => $amount,
                    'quiz_checkout_currency' => $currency,
                ])->save();

                $user->payments()->create([
                    'stripe_checkout_session_id' => $session['id'],
                    'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'pending',
                ]);

                return $session['url'];
            });

            return $url ? redirect()->away($url) : redirect()->route('quiz')->with('payment_status', __('Payment is being confirmed. Please try again shortly.'));
        } catch (Throwable $exception) {
            // Do not expose provider responses or secret credentials to the browser.
            return redirect()->route('quiz')->with('payment_error', __('Payment could not be started. Please try again.'));
        }
    }

    // The success method is called after the user completes the payment on Stripe's checkout page.
    // It validates the session ID returned by Stripe and checks if it matches the user's stored session ID.
    //  If the session is valid, it retrieves the session details from Stripe and activates the user's quiz
    public function success(Request $request)
    {
        $request->validate(['session_id' => ['required', 'string', 'max:255']]);
        abort_unless($request->query('session_id') === $request->user()->quiz_checkout_session_id, 403);
        if (blank(config('billing.stripe_secret'))) {
            return redirect()->route('quiz')->with('payment_error', __('Payment is not available yet.'));
        }
        try {
            $session = $this->stripe()->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($request->query('session_id')))->throw()->json();
            $paid = $this->activate($session);

            return redirect()->route('quiz')->with('payment_status', $paid
                ? __('Your quiz access is unlocked.')
                : __('Payment is being confirmed. Please try again shortly.'));
        } catch (Throwable $exception) {
            return redirect()->route('quiz')->with('payment_error', __('Payment is being confirmed. Please try again shortly.'));
        }
    }

    // The webhook method handles incoming webhook events from Stripe.
    // It verifies the authenticity of the webhook request using the Stripe signature and secret.
    //  If the event is a successful payment, it activates the user's quiz access.
    public function webhook(Request $request)
    {
        $secret = config('billing.stripe_webhook_secret');
        abort_if(blank($secret), 503);
        $parts = [];
        foreach (explode(',', $request->header('Stripe-Signature', '')) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]][] = $pair[1];
            }
        }
        $timestamp = $parts['t'][0] ?? '';
        abort_unless(ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300, 400);
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        $valid = false;
        foreach ($parts['v1'] ?? [] as $signature) {
            $valid = $valid || hash_equals($expected, $signature);
        }
        abort_unless($valid, 400);
        $event = json_decode($request->getContent(), true);
        abort_unless(is_array($event), 400);
        if (in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $this->activate($event['data']['object'] ?? []);
        }
        if (($event['type'] ?? '') === 'checkout.session.async_payment_failed') {
            $session = $event['data']['object'] ?? [];
            if (($session['metadata']['purpose'] ?? '') === 'quiz_upgrade') {
                Payment::where('stripe_checkout_session_id', $session['id'] ?? '')
                    ->where('user_id', $session['metadata']['user_id'] ?? null)
                    ->where('status', 'pending')
                    ->update(['status' => 'failed']);

                User::whereKey($session['metadata']['user_id'] ?? null)
                    ->where('quiz_checkout_session_id', $session['id'] ?? '')
                    ->whereNull('quiz_upgraded_at')
                    ->update(['quiz_checkout_session_id' => null, 'quiz_checkout_amount' => null, 'quiz_checkout_currency' => null]);
            }
        }

        return response()->noContent();
    }

    // The activate method is responsible for activating the user's quiz access after a successful payment.
    // It checks the payment session details to ensure that the payment was successful and that the session corresponds to the correct user and payment purpose.
    // If all checks pass, it updates the user's record to mark the quiz as upgraded and returns true. Otherwise, it returns false.
    private function activate(array $session): bool
    {
        if (($session['mode'] ?? '') !== 'payment'
            || ($session['status'] ?? '') !== 'complete'
            || ($session['payment_status'] ?? '') !== 'paid'
            || ($session['metadata']['purpose'] ?? '') !== 'quiz_upgrade') {
            return false;
        }

        return DB::transaction(function () use ($session) {
            $user = User::whereKey($session['metadata']['user_id'] ?? null)->lockForUpdate()->first();
            if (! $user || $user->quiz_checkout_session_id !== ($session['id'] ?? null)
                || (string) $user->id !== ($session['client_reference_id'] ?? '')
                || $user->quiz_checkout_amount !== ($session['amount_total'] ?? null)
                || $user->quiz_checkout_currency !== ($session['currency'] ?? null)) {
                return false;
            }
            // Keep the payment history and access activation in the same transaction.
            $payment = $user->payments()->firstOrNew([
                'stripe_checkout_session_id' => $session['id'],
            ]);
            $payment->fill([
                'stripe_payment_intent_id' => $session['payment_intent'] ?? $payment->stripe_payment_intent_id,
                'amount' => $session['amount_total'],
                'currency' => $session['currency'],
                'status' => 'paid',
                'paid_at' => $payment->paid_at ?? $user->quiz_upgraded_at ?? now(),
            ])->save();

            if (! $user->quiz_upgraded_at) {
                $user->forceFill(['quiz_upgraded_at' => now()])->save();
            }

            return true;
        });
    }
}
