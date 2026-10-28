<div class="purchases">
    <section class="card" aria-labelledby="subscription-title">
        <h2 id="subscription-title">{{ __('Subscription') }}</h2>
        <p>{{ __('Subscriptions will be available here later. Quiz access is currently a one-time purchase.') }}</p>
        <p class="notice">{{ __($user->quiz_upgraded_at ? 'Full quiz access is unlocked.' : 'Your account currently uses free quiz access.') }}</p>
    </section>

    <section class="card" aria-labelledby="purchases-title">
        <h2 id="purchases-title">{{ __('My purchases') }}</h2>
        <p>{{ __('Find your purchases and open the Stripe receipt for a confirmed payment.') }}</p>
        @if (session('purchase_error'))
            <p class="errors" role="alert">{{ session('purchase_error') }}</p>
        @endif
        @php
            $statuses = ['pending' => 'Awaiting confirmation', 'paid' => 'Paid', 'failed' => 'Failed', 'expired' => 'Expired'];
        @endphp
        <form class="purchase-search" method="GET" action="{{ route('purchases.index') }}" role="search">
            <div>
                <label for="purchase-query">{{ __('Search purchases') }}</label>
                <input id="purchase-query" type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Payment reference') }}">
            </div>
            <div>
                <label for="purchase-status">{{ __('Purchase status') }}</label>
                <select id="purchase-status" name="status">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="button" type="submit">{{ __('Search') }}</button>
            <a href="{{ route('purchases.index') }}">{{ __('Reset filters') }}</a>
        </form>
        <div class="purchase-list">
            @forelse ($payments as $payment)
                <article class="purchase-item">
                    <div class="purchase-heading">
                        <h3>{{ __('Full quiz access') }}</h3>
                        <span class="purchase-status purchase-status-{{ $payment->status }}">{{ __($statuses[$payment->status] ?? 'Unknown status') }}</span>
                    </div>
                    <dl class="purchase-data">
                        <div><dt>{{ __('Date') }}</dt><dd>{{ $payment->created_at->format('Y-m-d H:i') }}</dd></div>
                        <div><dt>{{ __('Amount') }}</dt><dd>{{ number_format($payment->amount / 100, 2, app()->getLocale() === 'en' ? '.' : ',', ' ') }} {{ strtoupper($payment->currency) }}</dd></div>
                        <div class="purchase-reference"><dt>{{ __('Payment reference') }}</dt><dd>{{ $payment->stripe_checkout_session_id }}</dd></div>
                        @if ($payment->paid_at)
                            <div><dt>{{ __('Paid on') }}</dt><dd>{{ $payment->paid_at->format('Y-m-d H:i') }}</dd></div>
                        @endif
                    </dl>
                    @if ($payment->status === 'paid')
                        <a class="action-button action-secondary" href="{{ route('purchases.receipt', $payment->id) }}">{{ __('View receipt') }}</a>
                    @else
                        <p>{{ __('A receipt is available only after payment confirmation.') }}</p>
                    @endif
                </article>
            @empty
                <p class="notice" role="status">{{ filled($filters['q'] ?? null) || filled($filters['status'] ?? null) ? __('No purchases match your search.') : __('You have no purchases yet.') }}</p>
            @endforelse
        </div>
        @if ($payments->hasPages())
            <nav class="purchase-pagination" aria-label="{{ __('Purchase pages') }}">
                @if ($payments->previousPageUrl())
                    <a class="action-button action-secondary" href="{{ $payments->previousPageUrl() }}">{{ __('Previous page') }}</a>
                @endif
                @if ($payments->nextPageUrl())
                    <a class="action-button action-secondary" href="{{ $payments->nextPageUrl() }}">{{ __('Next page') }}</a>
                @endif
            </nav>
        @endif
    </section>
</div>
