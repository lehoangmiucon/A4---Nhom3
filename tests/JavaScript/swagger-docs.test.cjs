const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function loadPage() {
    const api = 'http://localhost:8888/Laravelpro/helpdesk/public/api';
    const authorized = [];
    const loggedOut = [];
    const status = { textContent: '' };
    let options;
    let onLoad;
    const bundle = (config) => {
        options = config;
        return {
            preauthorizeApiKey: (scheme, token) => authorized.push({ scheme, token }),
            authActions: { logout: (schemes) => loggedOut.push(Array.from(schemes)) },
        };
    };
    bundle.presets = { apis: {} };
    const window = {
        location: { href: api },
        addEventListener: (event, callback) => { onLoad = callback; },
    };
    const document = {
        querySelectorAll: () => [],
        getElementById: (id) => {
            if (id === 'swagger-ui') return { dataset: { apiUrl: api } };
            if (id === 'swagger-spec') return { textContent: JSON.stringify({ openapi: '3.0.3', servers: [{ url: api }], paths: {} }) };
            return status;
        },
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/shared/js/swagger-docs.js'), 'utf8'), {
        window, document, SwaggerUIBundle: bundle, URL,
    });
    onLoad();
    return { api, options, authorized, loggedOut, status };
}

test('Swagger reads its embedded document without requesting a separate JSON page', () => {
    const page = loadPage();
    assert.equal(page.options.url, undefined);
    assert.equal(page.options.spec.openapi, '3.0.3');
    assert.equal(page.options.spec.servers[0].url, page.api);
});

test('login requires explicit Authorize and never attaches the returned token automatically', () => {
    const page = loadPage();
    for (const role of ['admin', 'staff', 'customer']) {
        const response = { status: 200, url: page.api + '/auth/login', obj: { access_token: role + '-secret', user: { name: '<b>Test</b>', role } } };
        assert.equal(page.options.responseInterceptor(response), response);
        assert.ok(page.status.textContent.includes('Authorize'));
        assert.ok(!page.status.textContent.includes('-secret'));
    }
    assert.equal(page.authorized.length, 0);
    const request = page.options.requestInterceptor({});
    assert.equal(request.headers.Authorization, undefined);
});

test('failed logins and other endpoints cannot change authorization', () => {
    const page = loadPage();
    for (const response of [
        { status: 422, url: page.api + '/auth/login', obj: { access_token: 'invalid' } },
        { status: 200, url: page.api + '/rooms', obj: { access_token: 'invalid' } },
        { status: 200, url: 'https://example.com/api/auth/login', obj: { access_token: 'invalid' } },
        { status: 200, url: page.api + '/auth/login', text: 'invalid JSON' },
        { status: 200, url: page.api + '/auth/login', obj: {} },
    ]) page.options.responseInterceptor(response);
    assert.equal(page.authorized.length, 0);
    assert.equal(page.loggedOut.length, 0);
});

test('raw JSON login does not authorize and only successful logout clears authorization', () => {
    const page = loadPage();
    page.options.responseInterceptor({ status: 200, url: page.api + '/auth/login', text: JSON.stringify({ access_token: 'secret', user: { role: 'staff' } }) });
    assert.equal(page.authorized.length, 0);
    page.options.responseInterceptor({ status: 401, url: page.api + '/auth/logout' });
    assert.equal(page.loggedOut.length, 0);
    page.options.responseInterceptor({ status: 200, url: page.api + '/auth/logout' });
    assert.deepEqual(page.loggedOut, [['bearerAuth']]);
    assert.ok(page.status.textContent.includes('Đã đăng xuất'));
});

test('Swagger requests omit browser cookies and identify the token testing client', () => {
    const page = loadPage();
    const request = { headers: { Authorization: 'Bearer staff-token' } };
    assert.equal(page.options.requestInterceptor(request), request);
    assert.equal(request.credentials, 'omit');
    assert.equal(request.headers.Authorization, 'Bearer staff-token');
    assert.equal(request.headers.Accept, 'application/json');
    assert.equal(request.headers['X-Helpdesk-Client'], 'swagger-ui');
});
