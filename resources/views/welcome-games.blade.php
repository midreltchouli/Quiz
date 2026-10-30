@extends('layouts.play')
@section('content')
<section class="discover-hero">
    <div>
        <span class="eyebrow">{{ __('Curiosity starts here') }}</span>
        <h1>{{ __('Five minutes to play. Something new to learn.') }}</h1>
        <p class="lead">{{ __('Challenge your knowledge, discover the story behind each answer, or play together on one screen.') }}</p>
        <div class="button-row">
            <form method="POST" action="{{ route('quiz.demo.start') }}">@csrf<button class="action-button" type="submit">{{ __('Try 5 questions') }} <span aria-hidden="true">→</span></button></form>
            <a class="action-button action-secondary" href="#discover-games">{{ __('Explore the games') }}</a>
        </div>
        <p class="quiet">{{ __('No account needed for the demo. No card required.') }}</p>
        <div class="feature-tags"><span>{{ __('Solo or together') }}</span><span>{{ __('Short games') }}</span><span>{{ __('Three languages') }}</span></div>
    </div>
    <aside class="hero-preview" aria-label="{{ __('A glimpse of the quiz') }}">
        <div class="preview-top"><span class="pill">{{ __('A glimpse of the quiz') }}</span><span aria-hidden="true">✦</span></div>
        <img src="{{ asset('images/games/quiz.svg') }}" alt="" width="640" height="360">
        <h2>{{ __('Every answer is a chance to discover.') }}</h2>
        <p>{{ __('Play a short round, read the explanations and improve at your own pace.') }}</p>
    </aside>
</section>
<section id="discover-games" class="discover-section" aria-labelledby="games-heading">
    <span class="eyebrow">{{ __('Make time for a little fun') }}</span>
    <h2 id="games-heading">{{ __('Choose your next challenge') }}</h2>
    <div class="game-marquee" role="region" aria-label="{{ __('Choose your next challenge') }}">
        @foreach(['left', 'right'] as $direction)
            <div class="game-marquee-row {{ $direction === 'right' ? 'marquee-reverse' : '' }}">
                <div class="game-marquee-track">
                    @for($copy = 0; $copy < 2; $copy++)
                        <div class="game-marquee-group" @if($copy === 1) aria-hidden="true" inert @endif>
                            @for($pair = 0; $pair < 2; $pair++)
                                <div class="game-marquee-pair" @if($copy === 1 || $pair === 1) aria-hidden="true" inert @endif>
                                    <article class="marquee-card">
                                        <img src="{{ asset('images/games/connect-four.svg') }}" alt="" width="640" height="360">
                                        <div><span class="pill">{{ __('Two players, one screen') }}</span><h3>{{ __('Connect Four') }}</h3><a class="text-link" href="{{ route('register') }}">{{ __('Create an account to play') }} →</a></div>
                                    </article>
                                    <article class="marquee-card">
                                        <img src="{{ asset('images/games/quiz.svg') }}" alt="" width="640" height="360">
                                        <div><span class="pill">{{ __('1 to 6 players') }}</span><h3>{{ __('General knowledge quiz') }}</h3><form method="POST" action="{{ route('quiz.demo.start') }}">@csrf<button type="submit" class="text-link">{{ __('Try the quiz') }} →</button></form></div>
                                    </article>
                                </div>
                            @endfor
                        </div>
                    @endfor
                </div>
            </div>
        @endforeach
    </div>
</section>
<section class="discovery-grid benefits discover-section" aria-label="{{ __('More with your account') }}">
    <article><span class="feature-number">01</span><h3>{{ __('Learn from your answers') }}</h3><p>{{ __('Read explanations and available sources, then revisit your mistakes in your game history.') }}</p></article>
    <article><span class="feature-number">02</span><h3>{{ __('See your progress') }}</h3><p>{{ __('Keep your scores, find your best results and choose your next goal.') }}</p></article>
    <article><span class="feature-number">03</span><h3>{{ __('A fresh daily challenge') }}</h3><p>{{ __('Five questions every day. The daily challenge stays free with your account.') }}</p></article>
</section>
<section class="signup-banner"><div><h2>{{ __('Ready for your first full game?') }}</h2><p>{{ __('Your first classic quiz game is free, from start to finish.') }}</p></div><a class="action-button" href="{{ route('register') }}">{{ __('Sign up') }}</a></section>
@endsection
