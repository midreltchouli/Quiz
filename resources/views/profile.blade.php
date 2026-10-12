<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('My profile') }} — {{ __('Game space') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }}; background: #f4f4f8; color: #202039; }
        .container { max-width: 1040px; margin: auto; padding: 24px; }
        header, nav { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        header { justify-content: space-between; padding-bottom: 24px; border-bottom: 1px solid #e0e0e5; }
        a { color: #4f46e5; }
        .brand { font-weight: 800; font-size: 1.2rem; color: #202039; text-decoration: none; }
        main { margin: 36px auto; }
        .narrow { max-width: 640px; margin: auto; }
        .card { background: white; border: 1px solid #e0e0e5; border-radius: 14px; padding: 24px; margin: 20px 0; }
        h1 { font-size: 2rem; } h2 { margin-top: 0; font-size: 1.2rem; }
        p { color: #62627a; line-height: 1.6; overflow-wrap: anywhere; }
        label { display: block; font-weight: 600; margin: 16px 0 6px; }
        input, select { width: 100%; padding: 12px; border: 1px solid #dcdce3; border-radius: 8px; font: inherit; background: white; color: #202039; }
        .button { display: inline-block; margin-top: 18px; padding: 12px 20px; border: 0; border-radius: 8px; background: #4f46e5; color: white; font: inherit; text-decoration: none; cursor: pointer; }
        .button:hover { background: #4338ca; }
        input:focus-visible, select:focus-visible, button:focus-visible, summary:focus-visible, a:focus-visible { outline: 3px solid #818cf8; outline-offset: 3px; }
        summary { cursor: pointer; font-size: .95rem; font-weight: 600; }
        li { margin: 12px 0; line-height: 1.6; }
        .notice { background: #eef0ff; color: #3730a3; padding: 14px; border-radius: 8px; }
        .errors { background: #fff0f0; color: #9f2424; padding: 14px; border-radius: 8px; }
        .checkbox-row { display: flex; align-items: center; gap: 10px; font-weight: 400; }
        .checkbox-row input { width: auto; }
        .profile-photo { display: block; width: 88px; height: 88px; object-fit: cover; border-radius: 50%; margin: 12px 0; }
        .danger { background: #b91c1c; }
        .settings-layout { display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 24px; }
        .settings-nav { display: flex; flex-direction: column; align-items: stretch; gap: 6px; }
        .settings-nav a { padding: 12px 16px; border-radius: 8px; color: #62627a; text-decoration: none; }
        .settings-nav a[aria-current="page"] { background: #eef0ff; color: #4f46e5; font-weight: 600; }
        .settings-nav a:hover { background: #e9e9f2; }
        @media (max-width: 600px) { .container { padding: 18px; } .settings-layout { grid-template-columns: 1fr; } .settings-nav { flex-direction: row; flex-wrap: wrap; } }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a class="brand" href="{{ route('accueil') }}">{{ __('Game space') }}</a>
            <nav aria-label="{{ __('Main navigation') }}">
                <a href="{{ route('accueil') }}">{{ __('Home') }}</a>
                @include('partials.user-menu')
            </nav>
        </header>
        <main>

            <div class="narrow">
                <h1>{{ __('My profile') }}</h1>
                <section class="card" aria-labelledby="profile-title">
                    @if ($user->avatar_path)
                        <img class="profile-photo" src="{{ route('account.avatar') }}" alt="{{ __('Profile photo') }}">
                    @endif
                    <h2 id="profile-title">{{ $user->name }}</h2>
                    <p>{{ $user->email }}</p>
                    <a href="{{ route('preferences.edit', ['section' => 'account']) }}">{{ __('Edit account details') }}</a>
                </section>
                <details class="card">
                    <summary>{{ __('Payment') }}</summary>
                    <p>{{ __('Offers and prices are still being defined. No purchases are available yet.') }}</p>
                    <ul>
                        <li><strong>{{ __('Access to all games') }}:</strong> {{ __('One-time purchase; included games and access duration to be defined.') }}</li>
                        <li><strong>{{ __('Access to one game') }}:</strong> {{ __('Buy access to a game of your choice; duration to be defined.') }}</li>
                        <li><strong>{{ __('Subscription') }}:</strong> {{ __('A monthly or annual plan is being considered.') }}</li>
                        <li><strong>{{ __('Credit pack') }}:</strong> {{ __('Credit amounts and uses are still to be defined.') }}</li>
                    </ul>
                    <p id="payment-element">{{ __('Payment is not available yet.') }}</p>
                </details>
            </div>
        </main>
    </div>
</body>
</html>
