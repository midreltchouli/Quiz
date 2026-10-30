@extends('layouts.play')
@section('title', __('Try the quiz'))
@section('content')
<section class="demo-panel">
    <span class="eyebrow">{{ __('Free discovery round') }}</span>
    @if(!$demo)
        <h1>{{ __('Try 5 questions') }}</h1>
        <p>{{ __('No account needed for the demo. No card required.') }}</p>
        <form method="POST" action="{{ route('quiz.demo.start') }}">@csrf<button class="action-button">{{ __('Start game') }}</button></form>
    @elseif($demo['review'])
        <h1>{{ $demo['review']['question'] }}</h1>
        <p role="status">{{ __($demo['review']['correct'] ? 'The answer is correct.' : 'The answer is incorrect.') }}</p>
        <p>{{ __('Correct answer') }} : <strong>{{ $demo['review']['answer'] }}</strong></p>
        @if($demo['review']['explanation'])<p>{{ $demo['review']['explanation'] }}</p>@endif
        <form method="POST" action="{{ route('quiz.demo.continue') }}">@csrf<button class="action-button">{{ __('Continue') }}</button></form>
    @elseif($demo['ids'] === [])
        <h1>{{ __('The quiz is being prepared.') }}</h1>
        <a href="{{ route('home') }}">{{ __('Back to home') }}</a>
    @elseif($demo['index'] >= count($demo['ids']))
        <h1>{{ __('Your discovery round is complete!') }}</h1>
        <span class="score-display">{{ $demo['score'] }} / {{ count($demo['ids']) }}</span>
        <p>{{ __('Create an account to save your next scores, explore more categories and play the daily challenge.') }}</p>
        <p class="quiet">{{ __('This demo score is not saved to an account.') }}</p>
        <div class="button-row"><a class="action-button" href="{{ route('register') }}">{{ __('Sign up') }}</a><a class="action-button action-secondary" href="{{ route('home') }}">{{ __('Back to home') }}</a></div>
    @elseif($question)
        <p>{{ __('Question :number / :total', ['number' => $demo['index'] + 1, 'total' => count($demo['ids'])]) }}</p>
        <h1>{{ $question->question }}</h1>
        @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
        <form method="POST" action="{{ route('quiz.demo.answer') }}">
            @csrf
            <input type="hidden" name="question_id" value="{{ $question->id }}">
            <div class="demo-options">@foreach($question->options as $option)<label><input type="radio" name="answer" value="{{ $option }}" required>{{ $option }}</label>@endforeach</div>
            <button class="action-button" type="submit">{{ __('Confirm') }}</button>
        </form>
    @else
        <h1>{{ __('This question is no longer available.') }}</h1>
        <form method="POST" action="{{ route('quiz.demo.start') }}">@csrf<button class="action-button">{{ __('Start again') }}</button></form>
    @endif
</section>
@endsection
