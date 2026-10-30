<article class="answer-review {{ $answer['correct'] ? '' : 'is-wrong' }}" lang="{{ $answer['locale'] }}">
    <span class="pill">{{ $answer['player'] }} · {{ __($answer['correct'] ? 'Correct answer' : 'Incorrect answer') }}</span>
    <h2>{{ $answer['question'] }}</h2>
    <p>{{ __('Your answer') }} : {{ $answer['chosen'] }}</p>
    @if(!$answer['correct'])<p>{{ __('Correct answer') }} : <strong>{{ $answer['answer'] }}</strong></p>@endif
    @if($answer['explanation'])<p class="explanation">{{ $answer['explanation'] }}</p>@endif
    @php($source = $answer['source_url'])
    @if(is_string($source) && filter_var($source, FILTER_VALIDATE_URL) && parse_url($source, PHP_URL_SCHEME) === 'https')
        <a href="{{ $source }}" target="_blank" rel="noopener noreferrer">{{ __('Read source') }} ↗</a>
    @endif
</article>
