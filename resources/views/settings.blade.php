<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __(request()->routeIs('purchases.index') ? 'Purchases and subscription' : 'Settings') }} — {{ __('Game space') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }}; background: #f4f4f8; color: #202039; }
        .container { max-width: 1040px; margin: auto; padding: 24px; }
        header, nav { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        header { justify-content: space-between; padding-bottom: 24px; border-bottom: 1px solid #e0e0e5; }
        a { color: #4f46e5; }
        .brand { font-weight: 800; font-size: 1.2rem; color: #202039; text-decoration: none; }
        main { margin: 36px auto; }
        .narrow { max-width: 640px; margin: auto; }
        .card { background: white; border: 1px solid #e0e0e5; border-radius: 14px; padding: 24px; margin: 20px 0; }
        h1 { font-size: 2rem; } h2 { margin-top: 0; font-size: 1.2rem; }
        p { color: #62627a; line-height: 1.6; overflow-wrap: anywhere; }
        label { display: block; font-weight: 600; margin: 16px 0 6px; }
        input, select { width: 100%; padding: 12px; border: 1px solid #dcdce3; border-radius: 8px; font: inherit; background: white; color: #202039; }
        .button { display: inline-block; margin-top: 18px; padding: 12px 20px; border: 0; border-radius: 8px; background: #4f46e5; color: white; font: inherit; text-decoration: none; cursor: pointer; }
        .button:hover { background: #4338ca; }
        input:focus-visible, select:focus-visible, button:focus-visible, summary:focus-visible, a:focus-visible { outline: 3px solid #818cf8; outline-offset: 3px; }
        summary { cursor: pointer; font-size: .95rem; font-weight: 600; }
        li { margin: 12px 0; line-height: 1.6; }
        .notice { background: #eef0ff; color: #3730a3; padding: 14px; border-radius: 8px; }
        .errors { background: #fff0f0; color: #9f2424; padding: 14px; border-radius: 8px; }
        .checkbox-row { display: flex; align-items: center; gap: 10px; font-weight: 400; }
        .checkbox-row input { width: auto; }
        .profile-photo { display: block; width: 88px; height: 88px; object-fit: cover; border-radius: 50%; margin: 12px 0; }
        .danger { background: #b91c1c; }
        .settings-layout { display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 24px; }
        .settings-nav { display: flex; flex-direction: column; align-items: stretch; gap: 6px; }
        .settings-nav a { padding: 12px 16px; border-radius: 8px; color: #62627a; text-decoration: none; }
        .settings-nav a[aria-current="page"] { background: #eef0ff; color: #4f46e5; font-weight: 600; }
        .settings-nav a:hover { background: #e9e9f2; }
        @media (max-width: 600px) { .container { padding: 18px; } .settings-layout { grid-template-columns: 1fr; } .settings-nav { flex-direction: row; flex-wrap: wrap; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/game-space.css') }}">
</head>
<body>
    <div class="container">
        <header>
            <a class="brand" href="{{ route('accueil') }}">{{ __('Game space') }}</a>
            <nav aria-label="{{ __('Main navigation') }}">
                <a class="action-button action-secondary" href="{{ route('accueil') }}">{{ __('Home') }}</a>
                @include('partials.user-menu')
            </nav>
        </header>
        <main>

            @php
                $section = request()->routeIs('purchases.index') ? 'purchases' : (in_array(request('section'), ['general', 'account', 'privacy'], true) ? request('section') : 'general');
            @endphp
            <h1>{{ __($section === 'purchases' ? 'Purchases and subscription' : 'Settings') }}</h1>
            @if (session('status'))
                <p class="notice" role="status">{{ __('Your changes have been saved.') }}</p>
            @endif
            @if ($errors->any())
                <div class="errors" role="alert">
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="settings-layout">
                <nav class="settings-nav" aria-label="{{ __('Settings sections') }}">
                    <a href="{{ route('profile.edit') }}">{{ __('Profile') }}</a>
                    <a href="{{ route('purchases.index') }}" @if ($section === 'purchases') aria-current="page" @endif>{{ __('Purchases and subscription') }}</a>
                    @foreach (['general' => 'General', 'account' => 'Account', 'privacy' => 'Privacy'] as $key => $label)
                        <a href="{{ route('preferences.edit', ['section' => $key]) }}" @if ($section === $key) aria-current="page" @endif>{{ __($label) }}</a>
                    @endforeach
                </nav>
                <div>
                    @if ($section === 'purchases')
                        @include('settings.purchases')
                    @elseif ($section === 'general')
                        <section class="card" aria-labelledby="general-title">
                            <h2 id="general-title">{{ __('General settings') }}</h2>
                            <form method="POST" action="{{ route('preferences.update') }}">
                                @csrf
                                @method('PATCH')
                                <label for="locale">{{ __('Language') }}</label>
                                <p>{{ __('Choose the language used throughout the application.') }}</p>
                                <select id="locale" name="locale">
                                    @foreach (['en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch'] as $code => $label)
                                        <option value="{{ $code }}" @selected(old('locale', $user->locale ?? 'en') === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <label for="font-family">{{ __('Font') }}</label>
                                <select id="font-family" name="font_family">
                                    @foreach (['system' => __('System default'), 'arial' => 'Arial', 'georgia' => 'Georgia', 'monospace' => __('Monospace')] as $code => $label)
                                        <option value="{{ $code }}" @selected(old('font_family', $user->font_family ?? 'system') === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <label for="text-size">{{ __('Text size') }}</label>
                                <select id="text-size" name="text_size">
                                    @foreach (['small' => 'Small', 'normal' => 'Normal', 'large' => 'Large'] as $code => $label)
                                        <option value="{{ $code }}" @selected(old('text_size', $user->text_size ?? 'normal') === $code)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                                <label for="quiz-notifications">{{ __('Notifications') }}</label>
                                <input type="hidden" name="quiz_notifications" value="0">
                                <label class="checkbox-row" for="quiz-notifications">
                                    <input id="quiz-notifications" type="checkbox" name="quiz_notifications" value="1" @checked(old('quiz_notifications', $user->quiz_notifications ?? true))>
                                    {{ __('Show quiz feedback notifications') }}
                                </label>
                                <p>{{ __('Display the result of each answer while playing. Your score is still recorded when this is disabled.') }}</p>
                                <button class="button" type="submit">{{ __('Save changes') }}</button>
                            </form>
                        </section>
                    @elseif ($section === 'account')
                        <section class="card" aria-labelledby="account-title">
                            <h2 id="account-title">{{ __('Account details') }}</h2>
                            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                                @csrf
                                @method('PATCH')
                                <label for="avatar">{{ __('Profile photo') }}</label>
                                @if ($user->avatar_path)
                                    <img class="profile-photo" src="{{ route('account.avatar') }}" alt="{{ __('Profile photo') }}">
                                @endif
                                <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp">
                                <p>{{ __('Choose a JPG, PNG or WebP image up to 2 MB.') }}</p>
                                @if ($user->avatar_path)
                                    <label class="checkbox-row" for="remove-avatar">
                                        <input id="remove-avatar" type="checkbox" name="remove_avatar" value="1">
                                        {{ __('Remove my profile photo') }}
                                    </label>
                                @endif
                                <label for="name">{{ __('Name') }}</label>
                                <input id="name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required>
                                <label for="email">{{ __('Email address') }}</label>
                                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" required>
                                <button class="button" type="submit">{{ __('Save changes') }}</button>
                            </form>
                        </section>
                        <section class="card" aria-labelledby="password-title">
                            <h2 id="password-title">{{ __('Change password') }}</h2>
                            <form method="POST" action="{{ route('user-password.update') }}">
                                @csrf
                                @method('PUT')
                                <label for="current-password">{{ __('Current password') }}</label>
                                <input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
                                <label for="new-password">{{ __('New password') }}</label>
                                <input id="new-password" name="password" type="password" autocomplete="new-password" required>
                                <label for="confirm-password">{{ __('Confirm password') }}</label>
                                <input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" required>
                                <button class="button" type="submit">{{ __('Update password') }}</button>
                            </form>
                        </section>
                    @else
                        <section class="card" aria-labelledby="privacy-title">
                            <h2 id="privacy-title">{{ __('Privacy and data protection') }}</h2>
                            <p>{{ __('Manage your personal data and your account here.') }}</p>
                            <p>{{ __('Your account includes your name, email address, profile photo and interface preferences. Your current quiz progress is stored in your session.') }}</p>
                            <a class="button" href="{{ route('preferences.export') }}">{{ __('Download my account data') }}</a>
                        </section>
                        <details class="card">
                            <summary>{{ __('Delete my account') }}</summary>
                            <p>{{ __('Deleting your account is permanent. Confirm with your password.') }}</p>
                            <form method="POST" action="{{ route('profile.destroy') }}">
                                @csrf
                                @method('DELETE')
                                <label for="delete-password">{{ __('Password') }}</label>
                                <input id="delete-password" name="password" type="password" autocomplete="current-password" required>
                                <button class="button danger" type="submit">{{ __('Delete my account') }}</button>
                            </form>
                        </details>
                    @endif
                </div>
            </div>
        </main>
    </div>
    @include('partials.chatbox')
</body>
</html>
