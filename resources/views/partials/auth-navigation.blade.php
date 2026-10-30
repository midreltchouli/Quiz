<header class="auth-navigation">
    <a class="auth-home-icon" href="{{ route('home') }}" aria-label="{{ __('Back to home') }}" title="{{ __('Back to home') }}">
        @include('partials.app-mark')
    </a>
    <nav aria-label="{{ __('Main navigation') }}">
        @if(request()->routeIs('login'))
            <a class="action-button action-secondary" href="{{ route('register') }}">{{ __('Sign up') }}</a>
        @else
            <a class="action-button action-secondary" href="{{ route('login') }}">{{ __('Log in') }}</a>
        @endif
    </nav>
</header>
