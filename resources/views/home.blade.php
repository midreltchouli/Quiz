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
        header { justify-content: space-between; padding-bottom: 24px; border-bottom: 1px solid #e0e0e5; }
        .brand { font-weight: 800; font-size: 1.2rem; color: #202039; text-decoration: none; }
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
</head>
<body>
    <div class="container">
        <header>
            <a class="brand" href="{{ route('accueil') }}">{{ __('Game space') }}</a>
            <nav aria-label="{{ __('Main navigation') }}">
                <a href="#jeux">{{ __('Games') }}</a>
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
                        <span class="badge">{{ __('Available') }}</span>
                        <h3>{{ __('General knowledge quiz') }}</h3>
                        <p>{{ __('Answer the questions and discover your score at the end.') }}</p>
                        <a class="button" href="{{ route('quiz') }}">{{ __('Play quiz') }}</a>
                    </article>
                    <article class="card">
                        <span class="badge">{{ __('Available') }}</span>
                        <h3>{{ __('Connect Four') }}</h3>
                        <p>{{ __('Two players, one screen. Align four discs to win.') }}</p>
                        <a class="button" href="{{ route('connect-four.show') }}">{{ __('Play Connect Four') }}</a>
                    </article>
                </div>
            </section>
        </main>
        <footer>{{ __('Game space') }} &middot; {{ __('Your next challenge starts here.') }}</footer>
    </div>
</body>
</html>
