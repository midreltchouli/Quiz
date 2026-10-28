<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Quiz results') }}</title>
<style>body { font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }}; }</style>
    <link rel="stylesheet" href="{{ asset('css/game-space.css') }}">
</head>
<body class="game-page">
    <main class="game-shell">
    <section class="card game-surface game-summary">

    <h1>{{ __('Quiz completed!') }}</h1>
    <p>{{ __('Category') }} : <strong>{{ \App\Services\Games\QuizCategories::label(session('quiz_category', 'general')) }}</strong></p>
    <a class="action-button action-secondary" href="{{ route('accueil') }}">{{ __('Back to home') }}</a>
    <p>{{ __(count($players) > 1 ? 'Combined score: :score / :total' : 'Your score: :score / :total', ['score' => $score, 'total' => $total]) }}</p>
    <h2>{{ __('Player scores') }}</h2>
    @php
        $ranking = collect($players)->sortByDesc('score');
        $bestScore = $ranking->first()['score'];
        $winners = $ranking->where('score', $bestScore)->pluck('name')->implode(', ');
    @endphp
    @if(count($players) > 1)
        <p>{{ __('Winner(s): :names', ['names' => $winners]) }}</p>
    @endif
    <ol>
        @foreach($ranking as $player)
            <li>{{ $player['name'] }} : {{ $player['score'] }} / {{ $player['answered'] }}</li>
        @endforeach
    </ol>
    <p><a class="action-button action-secondary" href="{{ route('games.players', ['game' => 'quiz']) }}">{{ __('Change category or players') }}</a></p>
    <form method="POST" action="{{ route('quiz.restart') }}">
        @csrf
        <button class="action-button" type="submit">{{ __('Start again') }}</button>
    </form>

    </section>
    </main>
    @include('partials.chatbox')
</body>
</html>
