<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Quiz unavailable') }}</title>
<style>body { font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }}; }</style>
</head>
<body>
    <h1>{{ __('No questions available') }}</h1>
    <p>{{ __('This quiz has no questions yet. Please try again later.') }}</p>
    <a href="/">{{ __('Back to home') }}</a>
</body>
</html>
