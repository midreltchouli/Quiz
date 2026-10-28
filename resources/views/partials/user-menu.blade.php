<style>
    .user-menu { position: relative; }
    .avatar-button { width: 44px; height: 44px; display: grid; place-items: center; border: 1px solid #dedcf4; border-radius: 50%; background: #eef0ff; color: #4f46e5; cursor: pointer; }
    .avatar-button:hover { background: #e0e3ff; }
    .avatar-button:focus-visible, .account-panel a:focus-visible, .account-panel button:focus-visible { outline: 3px solid #818cf8; outline-offset: 3px; }
    .account-panel { position: absolute; right: 0; top: 54px; width: min(270px, calc(100vw - 36px)); max-height: min(400px, 70vh); overflow-y: auto; background: white; border: 1px solid #e0e0e5; border-radius: 12px; padding: 8px; box-shadow: 0 12px 32px rgba(32,32,57,.14); z-index: 10; }
    .account-panel[hidden] { display: none; }
    .account-info { padding: 12px; border-bottom: 1px solid #eee; margin-bottom: 6px; }
    .account-info strong, .account-info small { display: block; overflow-wrap: anywhere; }
    .account-info small { margin-top: 4px; color: #62627a; }
    .account-panel a, .account-panel button { display: block; width: 100%; padding: 11px 12px; border: 0; border-radius: 6px; background: transparent; color: #202039; font: inherit; font-size: .95rem; text-align: left; text-decoration: none; cursor: pointer; }
    .account-panel a:hover, .account-panel button:hover { background: #f4f4f8; }
</style>
<div class="user-menu">
    <button class="avatar-button" id="account-toggle" type="button" aria-label="{{ __('Open account menu') }}" aria-expanded="false" aria-controls="account-panel">
        @if (auth()->user()->avatar_path)
            <img src="{{ route('account.avatar') }}" alt="" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
        @else
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21v-2a8 8 0 0 1 16 0v2" />
        </svg>
        @endif
    </button>
    <div class="account-panel" id="account-panel" hidden>
        <div class="account-info">
            <strong>{{ auth()->user()->name }}</strong>
            <small>{{ auth()->user()->email }}</small>
        </div>
        <a href="{{ route('profile.edit') }}">{{ __('Profile') }}</a>
        <a href="{{ route('purchases.index') }}">{{ __('Purchases and subscription') }}</a>
        <a href="{{ route('preferences.edit') }}">{{ __('Settings') }}</a>
        <form method="POST" action="{{ route('quiz.logout') }}">
            @csrf
            <button type="submit">{{ __('Log out') }}</button>
        </form>
    </div>
</div>
<script>
    (() => {
        const toggle = document.getElementById('account-toggle');
        const panel = document.getElementById('account-panel');
        const setOpen = (open) => {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        };
        toggle.addEventListener('click', () => setOpen(panel.hidden));
        document.addEventListener('click', (event) => {
            if (!toggle.parentElement.contains(event.target)) setOpen(false);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !panel.hidden) {
                setOpen(false);
                toggle.focus();
            }
        });
        toggle.parentElement.addEventListener('focusout', (event) => {
            if (!toggle.parentElement.contains(event.relatedTarget)) setOpen(false);
        });
    })();
</script>
