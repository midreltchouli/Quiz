<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quiz</title>
    <style>
        body {
            font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }};
            background: #f4f4f8;
            display: flex;
            justify-content: center;
            padding: 3rem 1rem;
        }

        .quiz-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 2rem;
            max-width: 480px;
            width: 100%;
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
    </style>
</head>
<body>
    <div class="quiz-card">
        <a href="{{ route('accueil') }}">{{ __('Back to home') }}</a>
        <form method="POST" action="/deconnexion" style="margin-bottom: 1rem;">
            @csrf
            <button type="submit" style="background: #999; font-size: 0.85rem; padding: 0.4rem 0.9rem;">
                {{ __('Log out') }}
            </button>
        </form>
        <p class="progress">{{ __('Question :number / :total', ['number' => $number, 'total' => $total]) }}</p>
        <p role="status"><strong>{{ __(':name to play', ['name' => $players[$currentPlayer]['name']]) }}</strong></p>
        <ul aria-label="{{ __('Player scores') }}">
            @foreach($players as $player)
                <li>{{ $player['name'] }} : {{ $player['score'] }} / {{ $player['answered'] }}</li>
            @endforeach
        </ul>
        <p><a href="{{ route('games.players', ['game' => 'quiz']) }}">{{ __('Change players') }}</a></p>
        @if(session('payment_status'))
            <p class="resultat" role="status">{{ session('payment_status') }}</p>
        @endif
        <h1>{{ $question->question }}</h1>
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
    </div>

</body>
</html>
