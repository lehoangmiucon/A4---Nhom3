const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8');
const context = vm.createContext({ window: {}, URL, URLSearchParams });
vm.runInContext(read('assets/js/technician-routes.js'), context);
const routes = context.window.TechnicianRoutes;
assert.equal(routes.parse('').view, 'schedule');
assert.equal(routes.parse('#reports').view, 'reports');
assert.equal(routes.parse('#report?id=SC-2026-089').id, 'SC-2026-089');
assert.equal(routes.parse('#unknown').view, 'missing');
assert.equal(routes.fromHref('#report', 'SC-2026-089'), '#report?id=SC-2026-089');
assert.equal(routes.fromHref('#reports', 'SC-2026-089'), '#reports');
assert.equal(routes.fromHref('technician-task.html?id=SC-2026-092#report'), '#report?id=SC-2026-092');
assert.equal(routes.fromHref('ticket-resolve.html?id=SC-2026-092'), '#resolve?id=SC-2026-092');
assert.equal(routes.fromHref('technician.html?completed=SC-2026-092'), '#schedule?completed=SC-2026-092');
assert.equal(routes.fromHref('#work', 'SC-2026-089'), null);
assert.equal(routes.fromHref('https://example.com/technician.html'), null);
const unusualId = 'Phiếu #1 & phòng A';
assert.equal(routes.parse(routes.make('task', unusualId)).id, unusualId);

// Check the project JavaScript and integrated HTML.
for (const name of ['technician-data', 'technician-routes', 'technician', 'ticket-resolve', 'technician-app']) {
  new vm.Script(read(`assets/js/${name}.js`), { filename: name });
}
const html = read('technician-app.html');
const templates = [...html.matchAll(/<template id="template-([^"]+)">([\s\S]*?)<\/template>/g)];
assert.equal(templates.length, 3);
const shell = html.replace(/<template\b[\s\S]*?<\/template>/g, '');
for (const [name, markup] of [['shell', shell], ...templates.map((match) => [match[1], match[2]])]) {
  const ids = [...markup.matchAll(/\bid="([^"]+)"/g)].map((match) => match[1]);
  assert.equal(ids.length, new Set(ids).size, `Duplicate DOM ID in ${name}`);
}
assert.ok(templates.find((match) => match[1] === 'resolve')[2].includes('id="signature"'));
assert.ok(templates.find((match) => match[1] === 'resolve')[2].includes('id="confirm-demo"'));
assert.ok(templates.find((match) => match[1] === 'detail')[2].includes('id="report-content"'));
assert.ok(templates.find((match) => match[1] === 'schedule')[2].includes('id="task-list"'));
const localScripts = [...shell.matchAll(/<script src="(assets\/js\/[^"]+)" defer><\/script>/g)];
assert.equal(localScripts.length, 5);
assert.equal([...shell.matchAll(/<link rel="stylesheet" href="assets\/css\//g)].length, 3);
assert.ok(!/<script(?:\s[^>]*)?>\s*[^<\s]/.test(shell), 'Application JS must remain in external files');
for (const match of shell.matchAll(/(?:href|src)="(assets\/[^\"]+)"/g)) {
  assert.ok(fs.existsSync(path.join(root, match[1])), `Missing asset: ${match[1]}`);
}
console.log('PASS: route navigation, ticket IDs, legacy links, draft section links, templates, external CSS/JS and local assets.');
