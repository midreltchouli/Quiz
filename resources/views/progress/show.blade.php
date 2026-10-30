@extends('layouts.play')
@section('title', __('Review answers'))
@section('content')
<div class="page-heading"><a href="{{ route('progress.index') }}">← {{ __('My progress') }}</a><h1>{{ __('Review answers') }}</h1><p>{{ \App\Services\Games\QuizCategories::label($attempt->category) }} · {{ $attempt->completed_at->format('d/m/Y H:i') }}</p><span class="score-display">{{ $attempt->score }} / {{ count($attempt->question_ids) }}</span></div>
<p>{{ __('Answers are kept in the language used during the game.') }}</p>
<details><summary class="text-link">{{ __('Show all answers') }}</summary>
    <div class="review-list">@foreach($attempt->answers as $answer)@include('progress.answer')@endforeach</div>
</details>
<h2>{{ __('Mistakes to revisit') }}</h2>
<div class="review-list">
    @forelse(collect($attempt->answers)->where('correct', false) as $answer)
        @include('progress.answer')
    @empty
        <p>{{ __('Perfect round! Every answer was correct.') }}</p>
    @endforelse
</div>
<div class="button-row"><a class="action-button" href="{{ route('games.players', ['game' => 'quiz']) }}">{{ __('Play another round') }}</a></div>
@endsection
