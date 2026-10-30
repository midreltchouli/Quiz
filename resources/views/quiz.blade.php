<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quiz</title>
    <style>
        body {
            margin: 0;
            font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }};
            background: #f4f4f8;
            display: flex;
            justify-content: center;
            padding: 3rem 1rem;
        }

        .quiz-card {
            box-sizing: border-box;
            min-width: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 2rem;
            max-width: 480px;
            width: 100%;
            overflow-wrap: anywhere;
        }

        .progress {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 0.5rem;
        }

        h1 {
            font-size: 1.3rem;
            margin: 0 0 1.5rem;
        }

        .options label {
            display: block;
            background: #f9f9fb;
            border: 1px solid #e0e0e5;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.6rem;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }

        .options label:hover {
            background: #eef0ff;
            border-color: #7b7bf0;
        }

        .options input[type="radio"] {
            margin-right: 0.6rem;
        }

        button {
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.7rem 1.4rem;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 0.5rem;
        }

        button:hover {
            background: #4338ca;
        }

        .resultat {
            margin-top: 1.2rem;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            background: #eef7ee;
            color: #2f7a3a;
            font-size: 0.95rem;
        }

        @media (min-width: 1024px) {
            body {
                padding: 3rem clamp(2rem, 4vw, 6rem);
            }

            .quiz-card {
                max-width: 1440px;
                padding: clamp(2rem, 3vw, 4rem);
            }

            h1 {
                font-size: clamp(1.5rem, 2vw, 2.25rem);
                line-height: 1.35;
            }

            .options {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 1rem;
                margin-bottom: 1rem;
            }

            .options label {
                display: flex;
                align-items: center;
                min-width: 0;
                margin-bottom: 0;
                padding: 1.25rem;
                line-height: 1.5;
            }

            .options input[type="radio"] {
                flex-shrink: 0;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/game-space.css') }}">
</head>
<body class="game-page">
    <main class="game-shell">
    @if ($review)
        @include('partials.quiz-review')
    @else
    <div class="quiz-card game-surface quiz-layout">
        <nav class="game-navigation">
        <a class="action-button action-secondary" href="{{ route('accueil') }}">{{ __('Back to home') }}</a>
        </nav>
        <section class="quiz-play" aria-labelledby="quiz-question">
        @if(session('payment_status'))
            <p class="resultat" role="status">{{ session('payment_status') }}</p>
        @endif
        <h1 id="quiz-question">{{ $question->question }}</h1>
        @if($errors->any())
            <p role="alert">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="/quiz/verified">
            @csrf

            <div class="options">
                @foreach($question->options as $option)
                    <label>
                        <input type="radio" name="reponse" value="{{ $option }}" required>
                        {{ $option }}
                    </label>
                @endforeach
            </div>

            <input type="hidden" name="question_id" value="{{ $question->id }}">

            <button type="submit">{{ __('Confirm') }}</button>
        </form>

        @if(session('resultat') && (auth()->user()->quiz_notifications ?? true))
            <div class="resultat">{{ session('resultat') }}</div>
        @endif
        </section>
        <footer class="game-details">
            <div class="quiz-metadata">
                <p>{{ __('Question :number / :total', ['number' => $number, 'total' => $total]) }}</p>
                <p>{{ __('Category') }} : <strong>{{ \App\Services\Games\QuizCategories::label(session('quiz_category', 'general')) }}</strong></p>
                <p>{{ __('Difficulty') }} : <strong>{{ __(['easy' => 'Easy', 'medium' => 'Intermediate', 'hard' => 'Difficult'][$question->difficulty] ?? 'Intermediate') }}</strong></p>
            </div>
            <div class="quiz-players">
                <div>
                    <p role="status"><strong>{{ __(':name to play', ['name' => $players[$currentPlayer]['name']]) }}</strong></p>
                    <ul class="player-scores" aria-label="{{ __('Player scores') }}">
                        @foreach($players as $player)
                            <li>{{ $player['name'] }} : {{ $player['score'] }} / {{ $player['answered'] }}</li>
                        @endforeach
                    </ul>
                </div>
                <a class="action-button action-secondary" href="{{ route('games.players', ['game' => 'quiz']) }}">{{ __('Change category or players') }}</a>
            </div>
        </footer>
    </div>
    @endif
    </main>

    @include('partials.chatbox')
</body>
</html>
