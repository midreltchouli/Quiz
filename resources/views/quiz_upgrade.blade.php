<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Upgrade your account') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 3rem 1rem; background: #f4f4f8; color: #202039; font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }}; }
        .card { max-width: 520px; margin: auto; padding: 2rem; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.08); }
        h1 { font-size: 1.5rem; }
        p { line-height: 1.6; }
        a { color: #4f46e5; }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 20px; background: #eef0ff; color: #4f46e5; }
        .price { font-size: 2rem; font-weight: 700; margin-bottom: 0; }
        button { width: 100%; padding: 14px; border: 0; border-radius: 8px; background: #4f46e5; color: white; font: inherit; cursor: pointer; }
        button:hover { background: #4338ca; }
        button:disabled { background: #e9e9f2; color: #68687c; cursor: not-allowed; }
        a:focus-visible, button:focus-visible { outline: 3px solid #818cf8; outline-offset: 4px; }
        .notice { padding: 12px; background: #eef0ff; border-radius: 8px; }
        .error { background: #fff0f0; color: #9f2424; }
    </style>
</head>
<body>
    <main class="card">
        <a href="{{ route('accueil') }}">{{ __('Back to home') }}</a>
        <p><span class="badge">{{ __('Free version') }}</span></p>
        <h1>{{ __('Continue with the full quiz') }}</h1>
        <p>{{ __('You have used your :limit free questions. Upgrade your account to answer the remaining questions.', ['limit' => $limit]) }}</p>
        <p>{{ __('Your progress and score are saved.') }}</p>
        @if(session('quiz_players'))
            <h2>{{ __('Player scores') }}</h2>
            <ul>
                @foreach(session('quiz_players') as $player)
                    <li>{{ $player['name'] }} : {{ $player['score'] }} / {{ $player['answered'] }}</li>
                @endforeach
            </ul>
        @endif
        <p><a href="{{ route('games.players', ['game' => 'quiz']) }}">{{ __('Change players') }}</a></p>
        @if (session('resultat') && (auth()->user()->quiz_notifications ?? true))
            <p class="notice" role="status">{{ session('resultat') }}</p>
        @endif
        @if (session('payment_error'))
            <p class="notice error" role="alert">{{ session('payment_error') }}</p>
        @endif
        @if (session('payment_status'))
            <p class="notice" role="status">{{ session('payment_status') }}</p>
        @endif
        <p class="price">{{ $price }}</p>
        <p>{{ __('One-time payment for full access to this quiz.') }}</p>
        <form method="POST" action="{{ route('quiz.checkout') }}">
            @csrf
            <button type="submit" @disabled(! $paymentReady)>{{ __('Upgrade your account') }} — {{ $price }}</button>
        </form>
        @if (! $paymentReady)
            <p>{{ __('Payment is not available yet.') }}</p>
        @endif
    </main>
</body>
</html>
