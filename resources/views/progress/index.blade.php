@extends('layouts.play')
@section('title', __('My progress'))
@section('content')
<div class="page-heading"><span class="eyebrow">{{ __('One game at a time') }}</span><h1>{{ __('My progress') }}</h1><p>{{ __('Your completed quiz games and the answers you can learn from.') }}</p></div>
<div class="stat-grid">
    <div class="stat"><strong>{{ $games }}</strong>{{ __('Completed games') }}</div>
    <div class="stat"><strong>{{ $total ? round(100 * $correct / $total) : 0 }} %</strong>{{ __('Correct answers') }}</div>
    <div class="stat"><strong>{{ $best }} %</strong>{{ __('Best game') }}</div>
</div>
<p class="quiet">{{ __('For shared games, these statistics include all players on your account.') }}</p>
<div class="history-list">
    @forelse($attempts as $attempt)
        <article class="history-item">
            <div><h2>{{ $attempt->daily_date ? __('Daily challenge') : \App\Services\Games\QuizCategories::label($attempt->category) }}</h2><p><time datetime="{{ $attempt->completed_at->toIso8601String() }}">{{ $attempt->completed_at->format('d/m/Y H:i') }}</time> · {{ implode(', ', array_column($attempt->players, 'name')) }}</p></div>
            <strong>{{ $attempt->score }} / {{ count($attempt->question_ids) }}</strong>
            <a class="action-button action-secondary" href="{{ route('progress.show', $attempt) }}">{{ __('Review answers') }}</a>
        </article>
    @empty
        <section class="demo-panel"><h2>{{ __('Your story starts with one game.') }}</h2><p>{{ __('Completed games will appear here. Earlier games were not recorded.') }}</p><a class="action-button" href="{{ route('games.players', ['game' => 'quiz']) }}">{{ __('Play quiz') }}</a></section>
    @endforelse
</div>
@if($attempts->hasPages())
    <nav class="button-row" aria-label="{{ __('Game history pages') }}">
        @if($attempts->previousPageUrl())<a class="action-button action-secondary" href="{{ $attempts->previousPageUrl() }}">← {{ __('Previous') }}</a>@endif
        <span>{{ $attempts->currentPage() }} / {{ $attempts->lastPage() }}</span>
        @if($attempts->nextPageUrl())<a class="action-button action-secondary" href="{{ $attempts->nextPageUrl() }}">{{ __('Next') }} →</a>@endif
    </nav>
@endif
@endsection
