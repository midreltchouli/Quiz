<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Throwable;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'expired'])],
        ]);

        $query = $request->user()->payments();
        if (filled($filters['q'] ?? null)) {
            $search = trim($filters['q']);
            $query->where(function ($query) use ($search) {
                $query->where('stripe_checkout_session_id', 'like', '%'.$search.'%')
                    ->orWhere('stripe_payment_intent_id', 'like', '%'.$search.'%');
            });
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return response()->view('settings', [
            'user' => $request->user(),
            'payments' => $query->latest('id')->simplePaginate(10)->withQueryString(),
            'filters' => $filters,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function receipt(Request $request, int $payment): RedirectResponse
    {
        // Scope the lookup to the current account before contacting Stripe.
        $purchase = $request->user()->payments()->findOrFail($payment);
        abort_unless($purchase->status === 'paid', 404);

        try {
            $secret = config('billing.stripe_secret');
            if (blank($secret)) {
                throw new \RuntimeException('Stripe is not configured.');
            }
            $stripe = Http::withToken($secret)->timeout(15);
            $intentId = $purchase->stripe_payment_intent_id;
            if (blank($intentId)) {
                $session = $stripe->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($purchase->stripe_checkout_session_id))
                    ->throw()->json();
                $intentId = $session['payment_intent'] ?? null;
            }
            if (! is_string($intentId) || ! str_starts_with($intentId, 'pi_')) {
                throw new \RuntimeException('Payment reference is unavailable.');
            }
            $intent = $stripe->get('https://api.stripe.com/v1/payment_intents/'.rawurlencode($intentId), [
                'expand' => ['latest_charge'],
            ])->throw()->json();

            $url = $intent['latest_charge']['receipt_url'] ?? null;
            if (($intent['status'] ?? '') !== 'succeeded'
                || ($intent['amount_received'] ?? null) !== $purchase->amount
                || ($intent['currency'] ?? '') !== $purchase->currency
                || ! is_string($url)
                || ! filter_var($url, FILTER_VALIDATE_URL)
                || parse_url($url, PHP_URL_SCHEME) !== 'https'
                || parse_url($url, PHP_URL_HOST) !== 'pay.stripe.com') {
                throw new \RuntimeException('Receipt is unavailable.');
            }

            return redirect()->away($url)->header('Cache-Control', 'private, no-store');
        } catch (Throwable $exception) {
            return redirect()->route('purchases.index')
                ->with('purchase_error', __('The receipt is unavailable right now. Please try again later.'));
        }
    }
}
