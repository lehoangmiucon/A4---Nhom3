const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
    constructor() { this.children = []; this.events = {}; this.attributes = {}; this.dataset = {}; this.value = ''; this.classList = { add() {}, remove() {}, toggle() {} }; }
    set textContent(value) { this.text = value; this.children = []; }
    get textContent() { return this.text || ''; }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; this.text = ''; }
    contains(child) { return this.children.includes(child); }
    querySelector(selector) { return selector === 'img' ? this.children.find(child => child.tagName === 'img') : null; }
    setAttribute(name, value) { this.attributes[name] = value; }
    getAttribute(name) { return name === 'src' ? this.src : this.attributes[name]; }
    removeAttribute(name) { delete this.attributes[name]; }
    addEventListener(event, callback) { this.events[event] = callback; }
}

async function loadChat() {
    const elements = new Map();
    const get = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
    const person = { name: 'Test', role: 'student', avatar_url: 'http://localhost/storage/first.png' };
    const conversation = { id: 1, type: 'direct', title: 'Test', participants: [person] };
    const timers = [];
    vm.runInNewContext(fs.readFileSync('public/shared/js/chat.js', 'utf8'), {
        document: {
            getElementById: get,
            querySelector: () => new Element(),
            querySelectorAll: () => [],
            addEventListener() {},
            createElement: tagName => Object.assign(new Element(), { tagName }),
            createTextNode: text => ({ textContent: text }),
        },
        window: {
            HELPDESK_CHAT_CONFIG: { canSearchContacts: true },
            location: { search: '' },
            setInterval: callback => { timers.push(callback); return timers.length; },
            clearInterval() {}, clearTimeout() {}, setTimeout: callback => { timers.push(callback); },
        },
        fetch: async url => ({ ok: true, json: async () => ({ data: url.includes('/messages') ? [] : url.includes('/contacts') ? [person] : [conversation] }) }),
        URLSearchParams,
    });
    await new Promise(setImmediate);
    return { get, person, timers };
}

test('chat shows profile photos and Vietnamese roles, then refreshes a changed avatar', async () => {
    const { get, person } = await loadChat();
    const button = get('chat-conversation-list').children[0];
    assert.equal(button.children[0].querySelector('img').src, person.avatar_url);
    await button.events.click();
    assert.equal(get('chat-subtitle').textContent, 'Sinh viên');
    const oldImage = get('chat-avatar').querySelector('img');
    person.avatar_url = 'http://localhost/storage/new.png';
    await get('chat-refresh').events.click();
    assert.equal(get('chat-avatar').querySelector('img').src, person.avatar_url);
    oldImage.events.error();
    assert.equal(get('chat-avatar').querySelector('img').src, person.avatar_url);
    get('chat-avatar').querySelector('img').events.error();
    assert.equal(get('chat-avatar').textContent, 'T');
});

test('contact search shows avatar and translates all supported roles', async () => {
    const { get, person, timers } = await loadChat();
    for (const [role, label] of Object.entries({ student: 'Sinh viên', teacher: 'Giảng viên', staff: 'Kỹ thuật viên', admin: 'Quản trị viên' })) {
        person.role = role;
        get('chat-search').value = 'test';
        get('chat-search').events.input();
        await timers.at(-1)();
        const contact = get('chat-contact-results').children[0];
        assert.equal(contact.children[0].querySelector('img').src, person.avatar_url);
        assert.equal(contact.children[2].textContent, label);
    }
});
