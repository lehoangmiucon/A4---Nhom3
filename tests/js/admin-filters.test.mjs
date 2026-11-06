import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

function element(properties = {}) {
  return Object.assign({
    value: '', hidden: false, dataset: {}, listeners: {}, queries: {},
    addEventListener(type, listener) { this.listeners[type] = listener; },
    querySelector(selector) { return this.queries[selector] ?? null; },
    querySelectorAll() { return []; },
    append(child) { this.queries['[data-team-filter-empty]'] = child; },
    removeAttribute() {}, setAttribute() {},
  }, properties);
}

function page(kind, initialValue = '') {
  const search = element();
  const selects = [element({ value: initialValue }), element(), element()];
  const rows = ['1', '2'].map((id) => element({
    dataset: { userId: id, teamRole: id === '1' ? 'admin' : 'staff' },
    queries: { '.ticket-code': { href: `http://localhost/admin/pages/ticket-detail?ticket=${id}` } },
  }));
  const table = element({ querySelectorAll: () => rows });
  const sections = ['admin', 'staff'].map((role) => element({
    classList: { contains: (name) => name === (role === 'admin' ? 'team-management' : 'team-technicians') },
    queries: { tbody: element(), [`[data-team-count="${role}"]`]: element() },
  }));
  const form = element({
    elements: { password: element(), email: element(), phone: element() },
    queries: { '#team-account-role': element({ value: 'admin' }), '[data-staff-category-picker]': element() },
  });
  const reset = element();
  const nodes = {
    '#team-account-modal': element(), '#team-account-form': form,
    '#team-search': search, '#team-role-filter': selects[0], '#team-status-filter': selects[1],
    '#team-category-filter': selects[2], '#team-filter-reset': reset,
    '.filter-bar': element({ queries: { '.search-field': search }, querySelectorAll: () => selects }),
    '.table-panel tbody': table, '.pagination span': element(), '#team-feedback': element(),
  };
  const calls = [];
  const context = vm.createContext({
    URL, URLSearchParams, AbortController,
    window: { HELPDESK_CONFIG: {}, location: { origin: 'http://localhost' }, clearTimeout, setTimeout },
    document: {
      querySelector: (selector) => nodes[selector] ?? null,
      querySelectorAll: (selector) => selector === '[data-team-row]' ? rows
        : selector === '.team-management,.team-technicians' ? sections : [],
      createElement: () => element(),
    },
    fetch(url, options) {
      return new Promise((resolve) => calls.push({ url, options, resolve }));
    },
  });
  const file = kind === 'team' ? 'team.js' : 'incidents.js';
  vm.runInContext(readFileSync(new URL(`../../public/admin/assets/js/${file}`, import.meta.url), 'utf8'), context);
  return { calls, rows, selects, reset };
}

const settle = () => new Promise((resolve) => setImmediate(resolve));
const respond = (call, ids) => call.resolve({ ok: true, json: async () => ({ data: { ids } }) });

for (const kind of ['team', 'incidents']) {
  test(`${kind}: initial server-rendered list needs no filter request`, () => {
    const fixture = page(kind);
    assert.equal(fixture.calls.length, 0);
    assert.deepEqual(fixture.rows.map((row) => row.hidden), [false, false]);
  });

  test(`${kind}: restored filter still requests matching records`, async () => {
    const fixture = page(kind, kind === 'team' ? 'staff' : 'new');
    assert.equal(fixture.calls.length, 1);
    respond(fixture.calls[0], ['2']);
    await settle();
    assert.deepEqual(fixture.rows.map((row) => row.hidden), [true, false]);
  });

  test(`${kind}: changing filters aborts old requests and ignores stale results`, async () => {
    const fixture = page(kind);
    const start = fixture.calls.length;
    fixture.selects[0].value = kind === 'team' ? 'staff' : 'new';
    fixture.selects[0].listeners.change();
    fixture.selects[0].value = kind === 'team' ? 'admin' : 'closed';
    fixture.selects[0].listeners.change();
    assert.equal(fixture.calls[start].options.signal?.aborted, true);
    respond(fixture.calls[start + 1], ['2']);
    await settle();
    respond(fixture.calls[start], ['1']);
    await settle();
    assert.deepEqual(fixture.rows.map((row) => row.hidden), [true, false]);
  });

  test(`${kind}: clearing filters restores rows locally and cancels pending results`, async () => {
    const fixture = page(kind, kind === 'team' ? 'staff' : 'new');
    const pending = fixture.calls[0];
    fixture.selects[0].value = '';
    if (kind === 'team') fixture.reset.listeners.click();
    else fixture.selects[0].listeners.change();
    assert.equal(fixture.calls.length, 1);
    assert.equal(pending.options.signal?.aborted, true);
    respond(pending, ['2']);
    await settle();
    assert.deepEqual(fixture.rows.map((row) => row.hidden), [false, false]);
  });
}
