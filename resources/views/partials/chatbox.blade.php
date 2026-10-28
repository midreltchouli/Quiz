@auth
@once
<link rel="stylesheet" href="{{ asset('css/chatbox.css') }}">
<div id="chatbox-root" data-config="{{ json_encode([
    'userId' => auth()->id(),
    'csrf' => csrf_token(),
    'conversationsUrl' => route('chat.index'),
    'recipientsUrl' => route('chat.recipients'),
    'locale' => app()->getLocale(),
    'labels' => [
        'ai' => __('AI assistant'), 'conversation' => __('Conversation'),
        'empty' => __('No messages yet. Say hello!'),
        'noConversations' => __('No conversations yet.'), 'noRecipients' => __('No players found.'),
        'choose' => __('Choose a conversation or start a new one.'),
        'you' => __('You'), 'deletedUser' => __('Former player'),
        'loading' => __('Loading…'), 'sending' => __('Sending…'),
        'thinking' => __('AI is replying…'), 'saved' => __('Message saved.'),
        'error' => __('Unable to connect. Please try again.'),
        'session' => __('Your session expired. Reload the page to sign in again.'),
        'rate' => __('Too many requests. Please wait a minute.'),
        'pending' => __('Your message is saved. Retry to get the AI reply.'),
        'ambiguous' => __('Delivery could not be confirmed. Your draft is kept; check the conversation before sending again.'),
    ],
]) }}">
    <button id="chatbox-toggle" type="button" aria-expanded="false" aria-controls="chatbox-panel">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-3 3V11.5a10 10 0 0 1 20 0Z"/></svg>
        {{ __('Messages') }}
    </button>
    <section id="chatbox-panel" role="region" aria-labelledby="chatbox-heading" hidden>
        <header class="chatbox-header">
            <div><strong id="chatbox-heading">{{ __('Messages') }}</strong><small>{{ __('Players & AI assistant') }}</small></div>
            <button id="chatbox-close" type="button" aria-label="{{ __('Close chat') }}">×</button>
        </header>
        <div class="chatbox-columns">
            <aside class="chatbox-sidebar" aria-label="{{ __('Conversations') }}">
                <button id="chatbox-new" type="button">+ {{ __('New conversation') }}</button>
                <div id="chatbox-conversations"></div>
                <button id="chatbox-more-conversations" type="button" hidden>{{ __('More conversations') }}</button>
            </aside>
            <div class="chatbox-main">
                <div id="chatbox-create" hidden>
                    <button id="chatbox-create-ai" type="button">{{ __('Chat with AI') }}</button>
                    <p class="chatbox-notice">{{ __('AI messages are sent to OpenAI to generate a reply.') }}</p>
                    <label for="chatbox-search">{{ __('Find a player') }}</label>
                    <input id="chatbox-search" type="search" maxlength="100" autocomplete="off" placeholder="{{ __('Search by name') }}">
                    <div id="chatbox-recipients"></div>
                    <button id="chatbox-more-recipients" type="button" hidden>{{ __('More players') }}</button>
                </div>
                <strong id="chatbox-title">{{ __('Choose a conversation or start a new one.') }}</strong>
                <button id="chatbox-older" type="button" hidden>{{ __('Earlier messages') }}</button>
                <div id="chatbox-messages" role="log" aria-label="{{ __('Messages') }}" aria-live="polite" aria-relevant="additions text" tabindex="0"></div>
                <p id="chatbox-status" role="status" aria-live="polite"></p>
                <p id="chatbox-error" role="alert" hidden></p>
                <button id="chatbox-retry" type="button" hidden>{{ __('Retry AI reply') }}</button>
                <form id="chatbox-form" hidden>
                    <label class="chatbox-sr-only" for="chatbox-input">{{ __('Your message') }}</label>
                    <textarea id="chatbox-input" maxlength="5000" rows="2" required placeholder="{{ __('Write a message…') }}"></textarea>
                    <button id="chatbox-send" type="submit">{{ __('Send') }}</button>
                </form>
            </div>
        </div>
    </section>
</div>
<script src="{{ asset('js/chatbox.js') }}" defer></script>
@endonce
@endauth
