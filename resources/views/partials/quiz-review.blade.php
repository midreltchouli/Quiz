<section class="card game-surface quiz-review" aria-labelledby="review-question">
    <a class="action-button action-secondary" href="{{ route('accueil') }}">{{ __('Back to home') }}</a>
    <div class="quiz-review-content">
        <p class="quiz-review-context">{{ __('Question :number / :total', ['number' => $review['number'], 'total' => $review['total']]) }} · {{ $review['player_name'] }}</p>
        <h1 id="review-question">{{ $review['question']->question }}</h1>
        <p class="quiz-review-verdict {{ $review['correct'] ? 'is-correct' : 'is-incorrect' }}" role="status">
            {{ __($review['correct'] ? 'The answer is correct.' : 'The answer is incorrect.') }}
        </p>
        <p class="quiz-review-answer"><strong>{{ __('Correct answer') }} :</strong> {{ $review['question']->answer }}</p>
        @if ($review['explanation'])
            <div class="quiz-explanation">
                <h2>{{ __('Why this answer?') }}</h2>
                <p>{{ $review['explanation'] }}</p>
            </div>
        @endif
        @if ($review['source_url'])
            <p class="quiz-review-source">
                <a class="action-button action-secondary" href="{{ $review['source_url'] }}" target="_blank" rel="noopener noreferrer">{{ __('Read the source') }} ↗</a>
                <small>{{ $review['source_name'] }} · {{ __('Opens in a new tab') }}</small>
            </p>
        @endif
    </div>
    <form method="POST" action="{{ route('quiz.continue') }}">
        @csrf
        <button class="action-button" type="submit">{{ __('Continue') }} →</button>
    </form>
</section>
