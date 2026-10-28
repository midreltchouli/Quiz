<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Game space') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }}; background: #f4f4f8; color: #202039; }
        a { color: #4f46e5; }
        .container { max-width: 1040px; margin: auto; padding: 24px; }
        header, nav { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        header { justify-content: flex-end; padding-bottom: 24px; border-bottom: 1px solid #e0e0e5; }
        .language-picker { position: relative; }
        .language-picker summary { display: flex; align-items: center; gap: 6px; min-height: 44px; padding: 0 12px; border: 1px solid #cce6f6; border-radius: 22px; background: #f0faff; color: #176398; cursor: pointer; list-style: none; font-weight: 600; font-size: .85rem; }
        .language-picker summary::-webkit-details-marker { display: none; }
        .language-picker summary:hover { background: #def3ff; }
        .language-picker summary:focus-visible, .language-options button:focus-visible { outline: 3px solid #818cf8; outline-offset: 3px; }
        .language-options { position: absolute; right: 0; top: calc(100% + 10px); z-index: 20; min-width: 170px; padding: 6px; background: white; border: 1px solid #dce9ee; border-radius: 12px; box-shadow: 0 12px 32px rgb(58 100 126 / 14%); }
        .language-options button { display: flex; justify-content: space-between; gap: 16px; width: 100%; padding: 11px 12px; border: 0; border-radius: 8px; background: white; color: #202039; font: inherit; text-align: left; cursor: pointer; }
        .language-options button:hover, .language-options button[aria-pressed="true"] { background: #eaf7ff; color: #176398; }
        .button { display: inline-block; background: #4f46e5; color: white; border: 0; border-radius: 8px; padding: 12px 20px; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .button:hover { background: #4338ca; }
        a:focus-visible, button:focus-visible { outline: 3px solid #818cf8; outline-offset: 4px; }
        .hero { padding: 48px 0 32px; }
        .eyebrow { color: #4f46e5; font-size: .85rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        h1 { font-size: clamp(1.8rem, 5vw, 2.8rem); margin: 12px 0; }
        h2 { font-size: 1.4rem; }
        p { color: #62627a; line-height: 1.6; }
        .games { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 20px; }
        .card { padding: 28px; background: white; border: 1px solid #e0e0e5; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,.03); }
        .card h3 { margin: 16px 0 8px; font-size: 1.2rem; }
        .badge { display: inline-block; padding: 5px 10px; background: #eef0ff; color: #4f46e5; border-radius: 20px; font-size: .8rem; }
        footer { margin-top: 32px; color: #77778b; font-size: .85rem; }
        @media (max-width: 600px) { .container { padding: 18px; } .hero { padding-top: 32px; } nav { gap: 10px; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/game-space.css') }}">
</head>
<body>
    <div class="container">
        <header>
            <nav aria-label="{{ __('Main navigation') }}">
                <details class="language-picker">
                    <summary aria-label="{{ __('Language') }}" title="{{ __('Language') }}">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <ellipse cx="12" cy="12" rx="4" ry="9" />
                            <path d="M3 12h18" />
                        </svg>
                        <span aria-hidden="true">{{ strtoupper(app()->getLocale()) }}</span>
                    </summary>
                    <form class="language-options" method="POST" action="{{ route('preferences.update') }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="return_to" value="home">
                        @foreach (['fr' => 'Français', 'en' => 'English', 'de' => 'Deutsch'] as $code => $label)
                            <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" aria-pressed="{{ app()->getLocale() === $code ? 'true' : 'false' }}">
                                {{ $label }}
                                @if (app()->getLocale() === $code)<span aria-hidden="true">✓</span>@endif
                            </button>
                        @endforeach
                    </form>
                </details>
                @include('partials.user-menu')
            </nav>
        </header>
        <main>
            <section class="hero">
                <span class="eyebrow">{{ __('Your turn to play') }}</span>
                <h1>{{ __('Welcome, :name!', ['name' => auth()->user()->name]) }}</h1>
                <p>{{ __('Choose a game, test your knowledge and play at your own pace.') }}</p>
            </section>
            <section id="jeux" aria-labelledby="games-title">
                <h2 id="games-title">{{ __('Games') }}</h2>
                <div class="games">
                    <article class="card">
                        <img class="game-image" src="{{ asset('images/games/quiz.svg') }}" alt="" width="640" height="360">
                        <span class="badge">{{ __('Available') }}</span>
                        <h3>{{ __('General knowledge quiz') }}</h3>
                        <p>{{ __('Answer the questions and discover your score at the end.') }}</p>
                        <a class="button" href="{{ route('quiz') }}">{{ __('Play quiz') }}</a>
                    </article>
                    <article class="card">
                        <img class="game-image" src="{{ asset('images/games/connect-four.svg') }}" alt="" width="640" height="360">
                        <span class="badge">{{ __('Available') }}</span>
                        <h3>{{ __('Connect Four') }}</h3>
                        <p>{{ __('Two players, one screen. Align four discs to win.') }}</p>
                        <a class="button" href="{{ route('connect-four.show') }}">{{ __('Play Connect Four') }}</a>
                    </article>
                </div>
            </section>
        </main>
        <footer>{{ __('Your next challenge starts here.') }}</footer>
    </div>
    @include('partials.chatbox')
</body>
</html>
