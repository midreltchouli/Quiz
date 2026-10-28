/* Chat autonome pour les pages Blade et Inertia ; aucune dépendance JavaScript. */
(() => {
    const root = document.getElementById('chatbox-root');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = 'true';
    const config = JSON.parse(root.dataset.config);
    const labels = config.labels;
    const el = (id) => document.getElementById(`chatbox-${id}`);
    const state = {
        conversation: null, messages: [], older: false, busy: false, loading: false,
        generation: 0, page: 1, recipientPage: 1, searchVersion: 0, drafts: new Map(),
        conversations: [], polling: false, timer: null,
    };
    const conversationUrl = (id) => `${config.conversationsUrl}/${id}`;
    const name = (conversation) => conversation.type === 'ai' ? labels.ai :
        conversation.participants?.filter((user) => user.id !== config.userId).map((user) => user.name).join(', ') || labels.conversation;
    const setError = (message = '') => {
        el('error').textContent = message;
        el('error').hidden = !message;
    };
    async function request(url, options = {}) {
        let response;
        try {
            response = await fetch(url, {
                credentials: 'same-origin', ...options,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrf, ...options.headers },
                signal: AbortSignal.timeout(60000),
            });
        } catch { throw new Error(labels.error); }
        if ([401, 419].includes(response.status)) throw new Error(labels.session);
        if (response.status === 429) throw new Error(labels.rate);
        let data;
        try { data = await response.json(); } catch { throw new Error(labels.error); }
        if (!response.ok) {
            // Les erreurs serveur 500 peuvent contenir des détails techniques : ne pas les afficher.
            throw new Error(response.status >= 500 && response.status !== 503 ? labels.error :
                Object.values(data.errors || {}).flat()[0] || data.message || labels.error);
        }
        return data;
    }
    function button(text, action) {
        const node = document.createElement('button');
        node.type = 'button';
        node.textContent = text;
        node.addEventListener('click', () => Promise.resolve(action()).catch((error) => setError(error.message)));
        return node;
    }
    function renderConversations() {
        el('conversations').replaceChildren();
        for (const conversation of state.conversations) {
            const node = button(name(conversation), () => select(conversation));
            node.setAttribute('aria-current', String(state.conversation?.id === conversation.id));
            el('conversations').append(node);
        }
        if (!state.conversations.length) el('conversations').textContent = labels.noConversations;
    }
    async function loadConversations(page = 1) {
        const data = await request(`${config.conversationsUrl}?page=${page}`);
        state.page = page;
        const merged = new Map((page === 1 ? [] : state.conversations).map((item) => [item.id, item]));
        data.data.forEach((item) => merged.set(item.id, item));
        state.conversations = [...merged.values()];
        el('more-conversations').hidden = !data.next_page_url;
        renderConversations();
    }
    const pendingQuestion = () => state.conversation?.type === 'ai' && state.messages.at(-1)?.role === 'user' ? state.messages.at(-1) : null;
    function updateControls() {
        const pending = pendingQuestion();
        el('input').disabled = state.busy || state.loading || !state.conversation || Boolean(pending);
        el('send').disabled = el('input').disabled || !el('input').value.trim();
        el('retry').hidden = !pending;
        el('retry').disabled = state.busy || state.loading;
        el('older').hidden = !state.older || !state.conversation;
        el('older').disabled = state.busy || state.loading;
        el('new').disabled = state.busy;
        el('create-ai').disabled = state.busy;
        el('more-conversations').disabled = state.busy;
        el('status').textContent = state.loading ? labels.loading : state.busy ? labels.sending : pending ? labels.pending : '';
    }
    function mergeMessages(incoming, preserveScroll = false) {
        const box = el('messages');
        const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
        const oldHeight = box.scrollHeight;
        const oldTop = box.scrollTop;
        const merged = new Map(state.messages.map((message) => [message.id, message]));
        incoming.forEach((message) => merged.set(message.id, message));
        state.messages = [...merged.values()].sort((a, b) => a.id - b.id);
        // Ajoute chaque message comme texte : aucun HTML reçu n'est exécuté.
        const existing = new Set([...box.querySelectorAll('[data-message-id]')].map((node) => Number(node.dataset.messageId)));
        box.querySelector('[data-empty]')?.remove();
        for (const message of state.messages) {
            if (existing.has(message.id)) continue;
            const bubble = document.createElement('article');
            bubble.className = 'chatbox-bubble';
            bubble.dataset.messageId = message.id;
            bubble.dataset.own = String(message.user_id === config.userId && message.role !== 'assistant');
            const meta = document.createElement('small');
            const author = message.role === 'assistant' ? labels.ai : message.user_id === config.userId ? labels.you : message.user?.name || labels.deletedUser;
            meta.textContent = `${author} · ${new Date(message.created_at).toLocaleString(config.locale, { dateStyle: 'short', timeStyle: 'short' })}`;
            const text = document.createElement('p');
            text.textContent = message.content;
            bubble.append(meta, text);
            const next = [...box.children].find((node) => Number(node.dataset.messageId) > message.id);
            box.insertBefore(bubble, next || null);
        }
        if (!state.messages.length) {
            const empty = document.createElement('p');
            empty.dataset.empty = 'true';
            empty.textContent = labels.empty;
            box.append(empty);
        }
        if (preserveScroll) box.scrollTop = oldTop + box.scrollHeight - oldHeight;
        else if (nearBottom) box.scrollTop = box.scrollHeight;
        updateControls();
    }
    async function select(conversation) {
        if (state.busy) return;
        if (state.conversation) state.drafts.set(state.conversation.id, el('input').value);
        const generation = ++state.generation;
        state.conversation = conversation;
        state.messages = [];
        state.older = false;
        state.loading = true;
        el('messages').replaceChildren();
        el('create').hidden = true;
        el('title').hidden = false;
        el('title').textContent = name(conversation);
        el('messages').hidden = false;
        el('form').hidden = false;
        el('input').value = state.drafts.get(conversation.id) || '';
        setError();
        updateControls();
        renderConversations();
        try {
            const data = await request(`${conversationUrl(conversation.id)}?latest=1`);
            if (generation !== state.generation) return;
            state.older = Boolean(data.messages.next_page_url);
            mergeMessages(data.messages.data);
            el('messages').scrollTop = el('messages').scrollHeight;
        } catch (error) {
            if (generation === state.generation) setError(error.message);
        } finally {
            if (generation === state.generation) {
                state.loading = false;
                updateControls();
            }
        }
    }
    async function loadRecipients(page = 1) {
        const version = ++state.searchVersion;
        const query = el('search').value.trim();
        const data = await request(`${config.recipientsUrl}?q=${encodeURIComponent(query)}&page=${page}`);
        if (version !== state.searchVersion || el('create').hidden) return;
        if (page === 1) el('recipients').replaceChildren();
        for (const user of data.data) el('recipients').append(button(`${user.name} (#${user.id})`, () => create('human', user.id)));
        if (!data.data.length && page === 1) el('recipients').textContent = labels.noRecipients;
        state.recipientPage = page;
        el('more-recipients').hidden = !data.next_page_url;
    }
    async function create(type, recipientId) {
        if (state.busy) return;
        state.busy = true;
        setError();
        updateControls();
        try {
            const data = await request(config.conversationsUrl, {
                method: 'POST', body: JSON.stringify({ type, recipient_id: recipientId }),
            });
            state.busy = false;
            state.conversations.unshift(data.conversation);
            renderConversations();
            await select(data.conversation);
        } catch (error) { setError(error.message); }
        finally { state.busy = false; updateControls(); }
    }
    async function reply() {
        const question = pendingQuestion();
        if (!question || state.busy) return;
        state.busy = true;
        setError();
        updateControls();
        el('status').textContent = labels.thinking;
        try {
            const data = await request(`${conversationUrl(state.conversation.id)}/reply`, {
                method: 'POST', body: JSON.stringify({ message_id: question.id }),
            });
            mergeMessages([data.message]);
        } catch (error) { setError(error.message); }
        finally { state.busy = false; updateControls(); }
    }
    async function refreshMessages() {
        if (!state.conversation || state.busy || state.loading) return;
        const generation = state.generation;
        const lastId = state.messages.at(-1)?.id || 0;
        const data = await request(`${conversationUrl(state.conversation.id)}?after_id=${lastId}`);
        if (generation === state.generation) mergeMessages(data.messages.data);
    }
    async function poll() {
        if (el('panel').hidden || document.hidden || state.polling || state.busy || state.loading) return;
        state.polling = true;
        try {
            await refreshMessages();
            // Recharge les pages déjà ouvertes pour conserver l'accès aux anciennes conversations.
            for (let page = 1, pages = state.page; page <= pages; page++) await loadConversations(page);
        } catch (error) { setError(error.message); }
        finally { state.polling = false; }
    }
    async function open() {
        el('panel').hidden = false;
        el('toggle').setAttribute('aria-expanded', 'true');
        el('close').focus();
        clearInterval(state.timer);
        state.timer = setInterval(poll, 4000);
        await poll();
    }
    function close() {
        el('panel').hidden = true;
        el('toggle').setAttribute('aria-expanded', 'false');
        clearInterval(state.timer);
        el('toggle').focus();
    }
    el('toggle').addEventListener('click', () => el('panel').hidden ? open().catch((error) => setError(error.message)) : close());
    el('close').addEventListener('click', close);
    root.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    el('new').addEventListener('click', () => {
        if (state.busy) return;
        if (state.conversation) state.drafts.set(state.conversation.id, el('input').value);
        state.generation++;
        state.searchVersion++;
        state.conversation = null;
        state.loading = false;
        state.older = false;
        state.messages = [];
        el('create').hidden = false;
        el('title').hidden = true;
        el('messages').hidden = true;
        el('form').hidden = true;
        setError();
        updateControls();
        renderConversations();
        el('search').focus();
        loadRecipients().catch((error) => setError(error.message));
    });
    let searchTimer;
    el('search').addEventListener('input', () => {
        clearTimeout(searchTimer);
        state.searchVersion++;
        searchTimer = setTimeout(() => loadRecipients().catch((error) => setError(error.message)), 300);
    });
    el('create-ai').addEventListener('click', () => create('ai'));
    el('more-recipients').addEventListener('click', () => loadRecipients(state.recipientPage + 1).catch((error) => setError(error.message)));
    el('more-conversations').addEventListener('click', () => loadConversations(state.page + 1).catch((error) => setError(error.message)));
    el('retry').addEventListener('click', reply);
    el('input').addEventListener('input', updateControls);
    el('form').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (state.busy || state.loading || !state.conversation || pendingQuestion() || !el('input').value.trim()) return;
        state.busy = true;
        setError();
        updateControls();
        try {
            const data = await request(`${conversationUrl(state.conversation.id)}/messages`, {
                method: 'POST', body: JSON.stringify({ content: el('input').value.trim() }),
            });
            el('input').value = '';
            state.drafts.delete(state.conversation.id);
            mergeMessages([data.message]);
            el('messages').scrollTop = el('messages').scrollHeight;
            state.busy = false;
            if (state.conversation.type === 'ai') await reply();
            else { updateControls(); el('input').focus(); }
        } catch (error) {
            // Si la connexion est coupée après l'écriture, le brouillon reste et l'historique est relu.
            state.busy = false;
            try { await refreshMessages(); } catch { /* Le prochain cycle réessaiera. */ }
            setError(`${error.message} ${labels.ambiguous}`);
        } finally { state.busy = false; updateControls(); }
    });
    el('older').addEventListener('click', async () => {
        if (state.loading || state.busy || !state.messages.length) return;
        const generation = state.generation;
        state.loading = true;
        updateControls();
        try {
            const data = await request(`${conversationUrl(state.conversation.id)}?before_id=${state.messages[0].id}`);
            if (generation !== state.generation) return;
            state.older = Boolean(data.messages.next_page_url);
            mergeMessages(data.messages.data, true);
        } catch (error) { if (generation === state.generation) setError(error.message); }
        finally { if (generation === state.generation) { state.loading = false; updateControls(); } }
    });
    updateControls();
})();
