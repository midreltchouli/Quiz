@extends('layouts.play')
@section('content')
<section class="page-heading">
    <span class="eyebrow">{{ __('Your turn to play') }}</span>
    <h1>{{ __('Welcome, :name!', ['name' => auth()->user()->name]) }}</h1>
    <p>{{ __('A short round, a new discovery, a score to improve. What will you play today?') }}</p>
</section>
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<section class="signup-banner" aria-labelledby="daily-heading">
    <div><span class="eyebrow">{{ now()->format('d/m/Y') }}</span><h2 id="daily-heading">{{ __('Daily challenge') }}</h2>
        <p>{{ $daily?->completed_at ? __('Today is complete. A new challenge arrives tomorrow.') : __('Five questions every day. The daily challenge stays free with your account.') }}</p>
    </div>
    <form method="POST" action="{{ route('quiz.daily') }}">@csrf<button class="action-button" type="submit">{{ __($daily?->completed_at ? 'See today’s result' : ($daily ? 'Continue today’s challenge' : 'Play today’s challenge')) }}</button></form>
</section>
@if($resume)
    <section class="history-item">
        <div><h2>{{ __('Pick up where you left off') }}</h2><p>{{ \App\Services\Games\QuizCategories::label($resume->category) }} · {{ count($resume->answers) }} / {{ count($resume->question_ids) }}</p></div>
        <form method="POST" action="{{ route('progress.resume', $resume) }}">@csrf<button class="action-button action-secondary">{{ __('Resume game') }}</button></form>
    </section>
@endif
<section class="discover-section" aria-labelledby="games-title">
    <h2 id="games-title">{{ __('Choose your next challenge') }}</h2>
    <div class="discovery-grid">
        <article class="discovery-card">
            <img class="game-image" src="{{ asset('images/games/quiz.svg') }}" alt="" width="640" height="360">
            <span class="pill">{{ __('1 to 6 players') }}</span>
            <h3>{{ __('General knowledge quiz') }}</h3>
            <p>{{ __('History, science, mathematics and more. Pick a category and play 5, 10 or 20 questions per player.') }}</p>
            <a class="action-button" href="{{ route('quiz') }}">{{ __('Play quiz') }}</a>
            <a class="text-link" href="{{ route('games.players', ['game' => 'quiz']) }}">{{ __('New game') }}</a>
        </article>
        <article class="discovery-card green-card">
            <img class="game-image" src="{{ asset('images/games/connect-four.svg') }}" alt="" width="640" height="360">
            <span class="pill">{{ __('Two players, one screen') }}</span>
            <h3>{{ __('Connect Four') }}</h3>
            <p>{{ __('A quick duel with a friend. Plan your moves and align four discs before your opponent.') }}</p>
            <a class="action-button" href="{{ route('connect-four.show') }}">{{ __('Play Connect Four') }}</a>
        </article>
    </div>
</section>
<section class="history-item"><div><h2>{{ __('Your progress matters') }}</h2><p>{{ __('Completed games') }} : {{ $completedGames }} · {{ __('Keep your scores and learn from your mistakes.') }}</p></div><a class="action-button action-secondary" href="{{ route('progress.index') }}">{{ __('My progress') }}</a></section>
@endsection
