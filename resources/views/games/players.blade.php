@php
    $isQuiz = $gameType === 'quiz';
    $min = $isQuiz ? 1 : 2;
    $max = $isQuiz ? 6 : 2;
    $saved = session($isQuiz ? 'quiz_players' : 'connect_four_players', []);
    $requestedCount = (int) old('player_count', request('player_count', count($saved) ?: $min));
    $count = max($min, min($max, $requestedCount));
    $title = $isQuiz ? __('General knowledge quiz') : __('Connect Four');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Choose players') }} — {{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f4f8; color: #202039; font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }}; }
        main { max-width: 620px; margin: 32px auto; padding: 24px; }
        .card { background: white; border: 1px solid #e0e0e5; border-radius: 16px; padding: clamp(18px, 4vw, 32px); margin-top: 24px; }
        h1 { margin-top: 0; } p { line-height: 1.6; color: #62627a; }
        a { color: #4f46e5; } label { display: block; font-weight: 600; margin: 18px 0 8px; }
        input, select { font: inherit; width: 100%; border: 1px solid #b8b8cb; border-radius: 8px; padding: 12px; background: white; color: #202039; }
        button { font: inherit; background: #4f46e5; color: white; border: 0; border-radius: 8px; padding: 12px 18px; cursor: pointer; margin-top: 16px; }
        .notice { background: #eef0ff; padding: 12px; border-radius: 8px; }
        .errors { background: #fef2f2; color: #991b1b; padding: 12px 24px; border-radius: 8px; }
        .quiz-setup { max-width: 960px; }
        .category-fieldset { border: 0; padding: 0; margin: 0 0 28px; min-width: 0; }
        .category-fieldset legend { font-size: 1.3rem; font-weight: 700; padding: 0; }
        .category-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .category-choice { display: flex; align-items: flex-start; gap: 10px; margin: 0; padding: 14px; border: 2px solid #dce9ee; border-radius: 12px; background: #fff; cursor: pointer; }
        .category-choice:hover { background: #f3fbff; border-color: #a9d7f4; }
        .category-choice:has(input:checked) { background: #ecf6ff; border-color: #4f46e5; }
        .category-choice:has(input:disabled) { opacity: .55; cursor: not-allowed; }
        .category-choice input { width: 18px; height: 18px; padding: 0; margin: 4px 0 0; flex-shrink: 0; accent-color: #4f46e5; }
        .category-choice strong, .category-choice small { display: block; }
        .category-choice small { font-weight: 400; color: #51516b; line-height: 1.4; margin-top: 6px; }
        .category-choice .category-count { color: #3730a3; font-weight: 600; }
        @media (max-width: 760px) { .category-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 480px) { main { padding: 12px; } .category-grid { grid-template-columns: 1fr; } }
        :focus-visible { outline: 3px solid #818cf8; outline-offset: 3px; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/game-space.css') }}">
</head>
<body class="game-page">
<main class="game-shell {{ $isQuiz ? 'quiz-setup' : '' }}">
    <a class="action-button action-secondary" href="{{ route('accueil') }}">&larr; {{ __('Back to home') }}</a>
    <section class="card game-surface setup-surface">
        <h1>{{ $title }}</h1>
        @if(!$isQuiz)<h2>{{ __('Choose players') }}</h2>@endif
        <p>{{ __('Play together on this screen using one account. Each participant chooses a name.') }}</p>
        @if($isQuiz)
            <p class="notice">{{ __('Quiz: 1 to 6 players. Each player answers a different question in turn, with the same number of questions per player. Scores are separate; the account question limit is shared.') }}</p>
        @else
            <p class="notice">{{ __('Connect Four requires exactly 2 players. Player 1 is red; player 2 is yellow.') }}</p>
        @endif
        @if($errors->any())
            <ul class="errors" role="alert">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('games.players.store', ['game' => $gameType]) }}">
            @csrf
            @if($isQuiz)
                <fieldset class="category-fieldset" aria-describedby="category-help">
                    <legend>{{ __('Choose a category') }}</legend>
                    <p id="category-help">{{ __('Standard categories contain intermediate and difficult questions. Kids discovery keeps a simpler selection.') }}</p>
                    <div class="category-grid">
                        @foreach($quizCategories as $key => $category)
                            <label class="category-choice">
                                <input type="radio" name="category" value="{{ $key }}" required
                                    @checked(old('category', session('quiz_category', 'general')) === $key)
                                    @disabled($category['count'] === 0)>
                                <span>
                                    <strong><span aria-hidden="true">{{ $category['icon'] }}</span> {{ __($category['label']) }}</strong>
                                    <small>{{ __($category['description']) }}</small>
                                    <small class="category-count">{{ trans_choice(':count question|:count questions', $category['count']) }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <h2>{{ __('Choose players') }}</h2>
                <label for="quiz-length">{{ __('Questions per player') }}</label>
                <select id="quiz-length" name="length">
                    @foreach([5, 10, 20] as $length)
                        <option value="{{ $length }}" @selected((int) old('length', session('quiz_length', 10)) === $length)>{{ $length }}</option>
                    @endforeach
                </select>
                <p>{{ __('Everyone gets the same number of turns. Smaller categories may give a shorter game.') }}</p>
                <label for="player-count">{{ __('Number of players') }}</label>
                <select id="player-count" name="player_count">
                    @for($n = $min; $n <= $max; $n++)
                        <option value="{{ $n }}" @selected($count === $n)>{{ $n }}</option>
                    @endfor
                </select>
            @else
                <input type="hidden" name="player_count" value="2">
            @endif
            @for($i = 0; $i < $max; $i++)
                <div data-player-field="{{ $i }}" @if($i >= $count) hidden @endif>
                    <label for="player-{{ $i }}">{{ __('Player :number', ['number' => $i + 1]) }}</label>
                    <input id="player-{{ $i }}" name="players[]" type="text" maxlength="40" autocomplete="off"
                        value="{{ old('players.'.$i, ($isQuiz ? ($saved[$i]['name'] ?? null) : ($saved[$i] ?? null)) ?? ($i === 0 ? auth()->user()->name : '')) }}"
                        @if($i < $count) required @else disabled @endif>
                </div>
            @endfor
            <p>{{ __('Use a different name for each player. Starting replaces only this game’s current session.') }}</p>
            <button type="submit">{{ __('Start game') }}</button>
        </form>
        @if(count($saved))
            <p><a class="action-button action-secondary" href="{{ route($isQuiz ? 'quiz' : 'connect-four.show') }}">{{ __('Return to current game') }}</a></p>
        @endif
    </section>
</main>
@if($isQuiz)
<script>
    document.getElementById('player-count').addEventListener('change', function () {
        const count = Number(this.value);
        document.querySelectorAll('[data-player-field]').forEach(function (field) {
            const active = Number(field.dataset.playerField) < count;
            field.hidden = !active;
            const input = field.querySelector('input');
            input.disabled = !active;
            input.required = active;
        });
    });
</script>
@endif
    @include('partials.chatbox')
</body>
</html>
