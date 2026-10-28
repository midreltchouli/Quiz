<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Quiz unavailable') }}</title>
<style>body { font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }}; }</style>
    <link rel="stylesheet" href="{{ asset('css/game-space.css') }}">
</head>
<body class="game-page">
    <main class="game-shell">
    <section class="card game-surface game-summary">
    <h1>{{ __('No questions available') }}</h1>
    <p>{{ __('This quiz has no questions yet. Please try again later.') }}</p>
    <a class="action-button action-secondary" href="/">{{ __('Back to home') }}</a>
    </section>
    </main>
    @include('partials.chatbox')
</body>
</html>
