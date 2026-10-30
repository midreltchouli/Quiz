import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import test from 'node:test';

// Run against a local Laravel server with a populated question bank.
test('discovery: responsive landing, languages, account links and guest quiz', { timeout: 60000 }, async (t) => {
    const executable = process.env.CHATBOX_BROWSER || [
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        '/usr/bin/chromium', '/usr/bin/google-chrome',
    ].find(existsSync);
    if (!executable) return t.skip('No Chromium browser installed.');
    const base = process.env.QUIZ_TEST_URL || 'http://127.0.0.1:8000';
    const profile = mkdtempSync(join(tmpdir(), 'quiz-discovery-'));
    const browser = spawn(executable, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    t.after(() => browser.kill());
    const endpoint = await new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Browser did not start.')), 10000);
        let output = '';
        browser.stderr.on('data', (chunk) => {
            output += chunk;
            const match = output.match(/DevTools listening on (ws:\/\/[^\s]+)/);
            if (match) { clearTimeout(timer); resolve(match[1]); }
        });
        browser.on('error', reject);
    });
    const targets = await fetch(`http://${new URL(endpoint).host}/json/list`).then((r) => r.json());
    const socket = new WebSocket(targets.find((target) => target.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve); socket.addEventListener('error', reject); });
    t.after(() => socket.close());
    let sequence = 0;
    const pending = new Map();
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (!pending.has(message.id)) return;
        const callback = pending.get(message.id);
        pending.delete(message.id);
        message.error ? callback.reject(new Error(message.error.message)) : callback.resolve(message.result);
    });
    const cdp = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++sequence;
        const timer = setTimeout(() => reject(new Error(`Timeout: ${method}`)), 10000);
        pending.set(id, { resolve: (value) => { clearTimeout(timer); resolve(value); }, reject: (error) => { clearTimeout(timer); reject(error); } });
        socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async (expression) => {
        const response = await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (response.exceptionDetails) throw new Error(response.exceptionDetails.text);
        return response.result.value;
    };
    const waitFor = async (expression) => {
        for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await delay(50); }
        throw new Error(`Not ready: ${expression}`);
    };
    await cdp('Page.enable');
    await cdp('Page.navigate', { url: base });
    await waitFor('document.querySelector(".discover-hero") !== null');
    for (const width of [320, 390, 768, 1366, 1920]) {
        await cdp('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 500 });
        await delay(50);
        assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), true, `No horizontal overflow at ${width}px`);
    }
    assert.equal(await evaluate('document.querySelector(".nav-login").pathname'), '/login');
    assert.equal(await evaluate('document.querySelector(".discover-header a.action-button").pathname'), '/register');
    await evaluate('document.querySelector("#public-locale").value="fr";document.querySelector("#public-locale").dispatchEvent(new Event("change", { bubbles: true }))');
    await waitFor('document.readyState === "complete" && document.documentElement.lang === "fr" && document.body.textContent.includes("Cinq minutes pour jouer")');
    await evaluate('window.beforeLanguageReload = true');
    await cdp('Page.reload');
    await waitFor('!window.beforeLanguageReload && document.readyState === "complete" && document.documentElement.lang === "fr" && document.querySelector("#public-locale")?.value === "fr"');
    for (const locale of ['de', 'en', 'fr']) {
        await evaluate(`document.querySelector('#public-locale').value='${locale}';document.querySelector('#public-locale').dispatchEvent(new Event('change', { bubbles: true }))`);
        await waitFor(`document.readyState === 'complete' && document.documentElement.lang === '${locale}' && document.querySelector('#public-locale')?.value === '${locale}'`);
    }
    const capture = await cdp('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    const screenshot = join(profile, 'landing-desktop.png');
    writeFileSync(screenshot, Buffer.from(capture.data, 'base64'));
    t.diagnostic(`Screenshot: ${screenshot}`);
    await evaluate('document.querySelector(".discover-hero form").requestSubmit()');
    await waitFor('document.querySelector(".demo-options input") !== null');
    assert.equal(await evaluate('location.pathname'), '/demo');
    for (let i = 0; i < 5; i++) {
        await evaluate('document.querySelector(".demo-options input").checked=true;document.querySelector(".demo-options").closest("form").requestSubmit()');
        await waitFor('document.querySelector("[role=status]") !== null');
        assert.equal(await evaluate('document.body.textContent.includes("Bonne réponse")'), true);
        await evaluate("Array.from(document.forms).find(form => form.action.endsWith('/demo/continue')).requestSubmit()");
        await waitFor(i === 4 ? 'document.querySelector(".score-display") !== null' : 'document.querySelector(".demo-options input") !== null');
    }
    assert.equal(await evaluate('document.body.textContent.includes("Ta partie découverte est terminée")'), true);
    await cdp('Page.navigate', { url: base });
    await waitFor('document.querySelector(".nav-login") !== null');
    await evaluate('document.querySelector(".nav-login").click()');
    await waitFor('document.querySelector(".auth-home-icon") !== null');
    assert.equal(await evaluate('location.pathname'), '/login');
    assert.equal(await evaluate('document.querySelector(".login-card form").action.endsWith("/login")'), true);
    assert.equal(await evaluate('document.querySelector(".auth-navigation nav a").pathname'), '/register');
    await evaluate('document.querySelector(".auth-navigation nav a").click()');
    await waitFor('document.querySelector(".register-card") !== null');
    assert.equal(await evaluate('location.pathname'), '/register');
    assert.equal(await evaluate('document.querySelector(".register-card form").action.endsWith("/register")'), true);
    assert.equal(await evaluate('document.querySelector(".auth-navigation nav a").pathname'), '/login');
    assert.equal(await evaluate('document.querySelector(".auth-home-icon").pathname'), '/');
});
