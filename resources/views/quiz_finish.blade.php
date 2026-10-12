<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Quiz results') }}</title>
<style>body { font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }}; }</style>
</head>
<body>

    <h1>{{ __('Quiz completed!') }}</h1>
    <a href="{{ route('accueil') }}">{{ __('Back to home') }}</a>
    <p>{{ __('Your score: :score / :total', ['score' => $score, 'total' => $total]) }}</p>
    <form method="POST" action="{{ route('quiz.restart') }}">
        @csrf
        <button type="submit">{{ __('Start again') }}</button>
    </form>

</body>
</html>
