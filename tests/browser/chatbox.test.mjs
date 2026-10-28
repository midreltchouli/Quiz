import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import test from 'node:test';

// Teste les vrais fichiers du widget dans Chrome/Edge, avec un serveur HTTP simulé.
test('chatbox: history, sending, XSS, drafts, polling and AI retry in a browser', { timeout: 60000 }, async (t) => {
    const browserPath = process.env.CHATBOX_BROWSER || [
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        '/usr/bin/chromium', '/usr/bin/google-chrome',
    ].find(existsSync);
    if (!browserPath) return t.skip('Set CHATBOX_BROWSER to a Chromium browser executable.');

    const human = { id: 1, type: 'human', participants: [{ id: 1, name: 'Alice' }, { id: 2, name: 'Bob' }] };
    const ai = { id: 2, type: 'ai', participants: [{ id: 1, name: 'Alice' }] };
    const message = (id, content, role = 'user', userId = 2) => ({
        id, content, role, user_id: userId, user: userId ? { id: userId, name: userId === 1 ? 'Alice' : 'Bob' } : null,
        created_at: '2026-10-22T12:00:00Z',
    });
    const messages = new Map([[1, Array.from({ length: 55 }, (_, i) => message(i + 1, `Message ${i + 1}`))], [2, []]]);
    let sends = 0;
    let replies = 0;
    let nextId = 56;
    let csrfSeen = false;
    const conversations = [human, ai];
    let holdHistory = false;
    let releaseHistory;
    const historyGate = new Promise((resolve) => { releaseHistory = resolve; });
    const labels = {
        ai: 'AI assistant', conversation: 'Conversation', empty: 'No messages yet.',
        noConversations: 'No conversations.', noRecipients: 'No players.', choose: 'Choose a conversation.',
        you: 'You', deletedUser: 'Former player', loading: 'Loading', sending: 'Sending', thinking: 'Thinking',
        error: 'Network error', session: 'Session expired', rate: 'Wait a minute', pending: 'Retry AI reply', ambiguous: 'Check before resending',
    };
    const server = createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        const json = (data, status = 200) => { res.writeHead(status, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(data)); };
        if (url.pathname === '/') {
            const config = { userId: 1, csrf: 'test-csrf', locale: 'fr', conversationsUrl: '/chat/conversations', recipientsUrl: '/chat/recipients', labels };
            let html = readFileSync('resources/views/partials/chatbox.blade.php', 'utf8')
                .replace(/^@(auth|once|endonce|endauth)\s*$/gm, '')
                .replace(/data-config="[\s\S]*?\]\) \}\}"/, `data-config="${JSON.stringify(config).replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"`)
                .replace(/\{\{ asset\('([^']+)'\) \}\}/g, '/$1')
                .replace(/\{\{ __\('([^']+)'\) \}\}/g, '$1');
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            const pageStyle = readFileSync('resources/views/quiz_upgrade.blade.php', 'utf8').match(/<style>([\s\S]*?)<\/style>/)[1]
                .replace(/\{\{[\s\S]*?\}\}/g, '16px');
            return res.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${pageStyle}</style></head><body>${html}</body></html>`);
        }
        if (url.pathname === '/js/chatbox.js' || url.pathname === '/css/chatbox.css') {
            res.setHeader('Content-Type', url.pathname.endsWith('.js') ? 'text/javascript' : 'text/css');
            return res.end(readFileSync(`public${url.pathname}`));
        }
        if (url.pathname === '/chat/recipients') return json({ data: [{ id: 2, name: 'Bob' }], next_page_url: null });
        if (url.pathname === '/chat/conversations' && req.method === 'GET') return json({ data: conversations, next_page_url: null });
        if (url.pathname === '/chat/conversations' && req.method === 'POST') {
            const parts = [];
            for await (const chunk of req) parts.push(chunk);
            const body = JSON.parse(Buffer.concat(parts).toString());
            const conversation = { id: conversations.length + 1, type: body.type, participants: body.type === 'human' ? human.participants : ai.participants };
            conversations.unshift(conversation);
            messages.set(conversation.id, []);
            return json({ conversation }, 201);
        }
        const match = url.pathname.match(/^\/chat\/conversations\/(\d+)(?:\/(messages|reply))?$/);
        if (match) {
            const id = Number(match[1]);
            if (req.method === 'GET') {
                if (holdHistory && url.searchParams.has('latest')) await historyGate;
                let rows = messages.get(id);
                if (url.searchParams.has('after_id')) rows = rows.filter((row) => row.id > Number(url.searchParams.get('after_id'))).slice(0, 50);
                else if (url.searchParams.has('before_id')) rows = rows.filter((row) => row.id < Number(url.searchParams.get('before_id'))).slice(-50);
                else rows = rows.slice(-50);
                return json({ conversation: conversations.find((item) => item.id === id), messages: { data: rows, next_page_url: rows.length === 50 ? 'older' : null } });
            }
            const parts = [];
            for await (const chunk of req) parts.push(chunk);
            const body = JSON.parse(Buffer.concat(parts).toString());
            csrfSeen ||= req.headers['x-csrf-token'] === 'test-csrf';
            if (match[2] === 'messages') {
                sends++;
                const row = message(nextId++, body.content, 'user', 1);
                messages.get(id).push(row);
                return json({ message: row }, 201);
            }
            if (match[2] === 'reply') {
                replies++;
                if (replies === 1) return json({ message: 'AI temporarily unavailable' }, 503);
                const row = message(nextId++, 'Bonjour Alice !', 'assistant', null);
                messages.get(id).push(row);
                return json({ message: row }, 201);
            }
        }
        res.writeHead(404); res.end();
    });
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    t.diagnostic('Fixture server ready');
    t.after(() => server.close());
    const browser = spawn(browserPath, [
        '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
        '--remote-debugging-port=0', `--user-data-dir=${mkdtempSync(join(tmpdir(), 'quiz-chatbox-'))}`, 'about:blank',
    ], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    t.after(() => browser.kill());
    const endpoint = await new Promise((resolve, reject) => {
        let output = '';
        const timer = setTimeout(() => reject(new Error('Browser debugging endpoint unavailable: ' + output.slice(-1000))), 15000);
        browser.once('error', reject);
        browser.stderr.on('data', (chunk) => {
            output += chunk;
            const match = output.match(/DevTools listening on (ws:\/\/[^\s]+)/);
            if (match) { clearTimeout(timer); resolve(match[1]); }
        });
    });
    t.diagnostic('Browser started');
    const targets = await fetch(`http://${new URL(endpoint).host}/json/list`, { signal: AbortSignal.timeout(10000) }).then((response) => response.json());
    const ws = new WebSocket(targets.find((target) => target.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Debugger WebSocket timed out')), 10000);
        ws.addEventListener('open', () => { clearTimeout(timer); resolve(); });
        ws.addEventListener('error', reject);
    });
    t.diagnostic('Debugger connected');
    t.after(() => ws.close());
    let callId = 0;
    const pending = new Map();
    const errors = [];
    ws.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
        if (pending.has(message.id)) {
            const { resolve, reject } = pending.get(message.id);
            pending.delete(message.id);
            message.error ? reject(new Error(message.error.message)) : resolve(message.result);
        }
    });
    const cdp = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++callId;
        const timer = setTimeout(() => reject(new Error(`Debugger timed out: ${method}`)), 10000);
        pending.set(id, { resolve: (value) => { clearTimeout(timer); resolve(value); }, reject: (error) => { clearTimeout(timer); reject(error); } });
        ws.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async (expression) => {
        const result = await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    const waitFor = async (expression) => {
        for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await delay(50); }
        throw new Error(`Timed out: ${expression}`);
    };
    const click = (id) => evaluate(`document.getElementById(${JSON.stringify(`chatbox-${id}`)}).click()`);
    const input = (text) => evaluate(`document.getElementById('chatbox-input').value=${JSON.stringify(text)};document.getElementById('chatbox-input').dispatchEvent(new Event('input'))`);
    await cdp('Runtime.enable');
    await cdp('Page.navigate', { url: `http://127.0.0.1:${server.address().port}/` });
    await waitFor("document.getElementById('chatbox-root')?.dataset.ready === 'true'");
    t.diagnostic('Widget ready');
    assert.equal(await evaluate("document.getElementById('chatbox-toggle').getBoundingClientRect().width < 200"), true, 'page button styles must not stretch the chat launcher');
    await click('toggle');
    await waitFor("document.querySelectorAll('#chatbox-conversations button').length === 2");
    await evaluate("document.querySelector('#chatbox-conversations button').click()");
    await waitFor("document.querySelectorAll('[data-message-id]').length === 50");
    assert.equal(await evaluate("document.querySelector('[data-message-id]').dataset.messageId"), '6');
    await click('older');
    await waitFor("document.querySelectorAll('[data-message-id]').length === 55");
    const unsafe = '<img src=x onerror="window.chatboxXss=true">';
    await input(unsafe);
    await evaluate("document.getElementById('chatbox-form').requestSubmit()");
    await waitFor("document.querySelectorAll('[data-message-id]').length === 56");
    assert.equal(await evaluate("document.querySelector('#chatbox-messages img') === null && !window.chatboxXss"), true);
    assert.equal(sends, 1);
    assert.equal(csrfSeen, true);
    await input('Brouillon conservé');
    await evaluate("document.querySelectorAll('#chatbox-conversations button')[1].click()");
    await waitFor("document.getElementById('chatbox-title').textContent === 'AI assistant' && !document.getElementById('chatbox-input').disabled");
    await input('Bonjour');
    await evaluate("document.getElementById('chatbox-form').requestSubmit()");
    await waitFor("!document.getElementById('chatbox-retry').hidden && !document.getElementById('chatbox-retry').disabled");
    assert.equal(messages.get(2).length, 1);
    assert.equal(await evaluate("document.getElementById('chatbox-input').disabled"), true);
    await click('retry');
    await waitFor("document.querySelectorAll('[data-message-id]').length === 2 && document.getElementById('chatbox-retry').hidden");
    assert.equal(sends, 2, 'retry must not resend the question');
    assert.equal(replies, 2);
    await evaluate("document.querySelector('#chatbox-conversations button').click()");
    await waitFor("document.getElementById('chatbox-input').value === 'Brouillon conservé' && !document.getElementById('chatbox-input').disabled");
    const incoming = message(nextId++, 'Message reçu automatiquement');
    messages.get(1).push(incoming);
    await waitFor(`Boolean(document.querySelector('[data-message-id="${incoming.id}"]'))`);
    await cdp('Emulation.setDeviceMetricsOverride', { width: 375, height: 667, deviceScaleFactor: 1, mobile: true });
    assert.equal(await evaluate("document.getElementById('chatbox-panel').getBoundingClientRect().width <= innerWidth"), true);
    if (process.env.CHATBOX_SCREENSHOT) {
        const screenshot = await cdp('Page.captureScreenshot', { format: 'png' });
        writeFileSync(process.env.CHATBOX_SCREENSHOT, Buffer.from(screenshot.data, 'base64'));
    }
    // Une réponse retardée d'une ancienne sélection ne doit pas écraser l'écran de création.
    holdHistory = true;
    await evaluate("document.querySelectorAll('#chatbox-conversations button')[1].click()");
    await click('new');
    releaseHistory();
    await delay(150);
    assert.equal(await evaluate("!document.getElementById('chatbox-create').hidden && document.getElementById('chatbox-form').hidden"), true);
    holdHistory = false;
    await waitFor("document.querySelector('#chatbox-recipients button') !== null");
    await evaluate("document.querySelector('#chatbox-recipients button').click()");
    await waitFor("document.getElementById('chatbox-title').textContent === 'Bob' && !document.getElementById('chatbox-input').disabled");
    assert.equal(conversations[0].type, 'human');
    await click('new');
    await click('create-ai');
    await waitFor("document.getElementById('chatbox-title').textContent === 'AI assistant' && !document.getElementById('chatbox-input').disabled");
    assert.equal(conversations[0].type, 'ai');
    await click('close');
    assert.equal(await evaluate("document.getElementById('chatbox-panel').hidden && document.activeElement.id === 'chatbox-toggle'"), true);
    assert.deepEqual(errors, []);
});
