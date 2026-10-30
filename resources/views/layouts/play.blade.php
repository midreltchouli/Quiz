<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('A little play. A little discovery. Every day.') }}">
    <title>@yield('title', __('Game space'))</title>
    <link rel="stylesheet" href="{{ asset('css/game-space.css') }}">
    <link rel="stylesheet" href="{{ asset('css/discover.css') }}">
    <script src="{{ asset('js/language-picker.js') }}" defer></script>
    @auth
        <style>body { font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }}; }</style>
    @endauth
</head>
<body class="discover-page">
    <div class="discover-shell">
        <header class="discover-header">
            <a class="brand" href="{{ route('home') }}">@include('partials.app-mark') {{ __('Game space') }}</a>
            <nav aria-label="{{ __('Main navigation') }}">
                @guest
                    <form action="{{ route('public.language') }}" method="POST" class="locale-form">
                        @csrf
                        <label class="visually-hidden" for="public-locale">{{ __('Language') }}</label>
                        <select id="public-locale" name="locale" data-language-picker>
                            @foreach(['fr' => 'Français', 'en' => 'English', 'de' => 'Deutsch'] as $code => $label)
                                <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit">{{ __('Change language') }}</button></noscript>
                    </form>
                    <a class="nav-login" href="{{ route('login') }}">{{ __('Log in') }}</a>
                    <a class="action-button" href="{{ route('register') }}">{{ __('Sign up') }}</a>
                @else
                    <form action="{{ route('preferences.update') }}" method="POST" class="locale-form">
                        @csrf @method('PATCH')
                        <input type="hidden" name="return_to" value="home">
                        <label class="visually-hidden" for="account-locale">{{ __('Language') }}</label>
                        <select id="account-locale" name="locale" data-language-picker>
                            @foreach(['fr' => 'Français', 'en' => 'English', 'de' => 'Deutsch'] as $code => $label)
                                <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit">{{ __('Change language') }}</button></noscript>
                    </form>
                    <a href="{{ route('progress.index') }}">{{ __('My progress') }}</a>
                    @include('partials.user-menu')
                @endguest
            </nav>
        </header>
        <main id="main-content">@yield('content')</main>
        <footer class="discover-footer"><span>{{ __('A little play. A little discovery. Every day.') }}</span><span>FR · EN · DE</span></footer>
    </div>
    @auth @include('partials.chatbox') @endauth
</body>
</html>
