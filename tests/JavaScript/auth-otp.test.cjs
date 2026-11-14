const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../public/auth/assets/js/auth.js'), 'utf8');

function page(clock, storage, endpoint, payload) {
    const listeners = {};
    const timers = new Map();
    let nextTimer = 0;
    let requests = 0;
    const element = () => ({ dataset: {}, setAttribute() {}, removeAttribute() {}, replaceChildren() {} });
    const classes = new Set();
    const expiry = { hidden: true, classList: { toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); } } };
    const email = { ...element(), name: 'email', type: 'email', validity: { valid: true }, value: 'otp@ut.edu.vn', addEventListener() {}, hasAttribute: () => false, getAttribute: () => '', closest: () => null };
    const feedback = element();
    const form = {
        dataset: { apiEndpoint: endpoint, successRedirect: endpoint === '/register' ? 'verify-otp' : endpoint === '/password/forgot' ? 'forgot-password?step=reset' : '' },
        elements: { email }, querySelectorAll: () => [],
        querySelector: (selector) => selector === '[data-otp-expiry]' ? expiry : selector === '[name="email"]' ? email : selector === '[data-api-feedback]' ? feedback : null,
    };
    const button = { dataset: { apiAction: endpoint.includes('password') ? '/password/forgot' : '/email/resend-otp' }, form, disabled: false, textContent: 'Gửi lại mã OTP', closest: () => form };
    const document = {
        cookie: '',
        baseURI: 'http://localhost/auth/',
        addEventListener: (event, callback) => { listeners[event] = callback; },
        querySelectorAll: () => [],
        querySelector: (selector) => selector === 'input[name="email"]' ? email : selector.includes('data-api-action=') ? button : null,
        createElement: element,
    };
    const window = {
        HELPDESK_CONFIG: { apiEnabled: true },
        location: { origin: 'http://localhost', search: '', assign() {} },
        setInterval: (callback) => { timers.set(++nextTimer, callback); return nextTimer; },
        clearInterval: (id) => timers.delete(id),
    };
    class ClockDate extends Date { static now() { return clock.now; } }
    vm.runInNewContext(source, {
        window, document, URL, URLSearchParams, Date: ClockDate, FormData: class {},
        sessionStorage: { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: (key) => storage.delete(key) },
        fetch: async () => { requests++; return { ok: true, headers: { get: () => 'application/json' }, json: async () => payload }; },
    });
    return {
        expiry, button, classes, requests: () => requests,
        ready: () => listeners.DOMContentLoaded(),
        submit: () => listeners.submit({ target: { closest: () => form }, preventDefault() {} }),
        resend: () => listeners.click({ target: { closest: () => button }, preventDefault() {} }),
        tick: () => [...timers.values()].forEach((callback) => callback()),
    };
}

const timing = (seconds = 0) => ({
    server_time: new Date(Date.UTC(2026, 10, 14, 10, 0, seconds)).toISOString(),
    otp_expires_at: new Date(Date.UTC(2026, 10, 14, 10, 3, seconds)).toISOString(),
    otp_resend_available_at: new Date(Date.UTC(2026, 10, 14, 10, 3, seconds)).toISOString(),
});

for (const [send, verify] of [['/register', '/email/verify-otp'], ['/password/forgot', '/password/reset']]) {
    test(`${send}: countdown survives reload, expires at 180 seconds and restarts only after resend`, async () => {
        const clock = { now: Date.UTC(2030, 0, 1) }; // Deliberately different from the server clock.
        const storage = new Map();
        const sender = page(clock, storage, send, timing());
        await sender.submit();
        assert.equal(sender.requests(), 1);
        assert.equal(storage.size, 1);
        let view = page(clock, storage, verify, timing(180));
        view.ready();
        assert.match(view.expiry.textContent, /03:00/);
        assert.equal(view.button.disabled, true);
        await view.resend();
        assert.equal(view.requests(), 0);
        clock.now += 179000;
        view = page(clock, storage, verify, timing(180));
        view.ready();
        assert.match(view.expiry.textContent, /00:01/);
        assert.equal(view.button.disabled, true);
        clock.now += 1000;
        view.tick();
        assert.match(view.expiry.textContent, /đã hết hạn/);
        assert.equal(view.classes.has('otp-expired'), true);
        assert.equal(view.button.disabled, false);
        await view.resend();
        assert.equal(view.requests(), 1);
        assert.equal(view.button.disabled, true);
        assert.match(view.expiry.textContent, /03:00/);
        assert.equal(view.classes.has('otp-expired'), false);
    });
}

test('registration and password reset have separate timers for the same email', async () => {
    const clock = { now: Date.UTC(2030, 0, 1) };
    const storage = new Map();
    await page(clock, storage, '/register', timing()).submit();
    clock.now += 60000;
    await page(clock, storage, '/password/forgot', timing(60)).submit();
    const registration = page(clock, storage, '/email/verify-otp');
    const reset = page(clock, storage, '/password/reset');
    registration.ready(); reset.ready();
    assert.match(registration.expiry.textContent, /02:00/);
    assert.match(reset.expiry.textContent, /03:00/);
});
