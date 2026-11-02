// Rebuild the integrated technician page from HTML templates and external assets.
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8');
const extract = (file, tag) => {
  const start = read(file);
  const match = start.match(new RegExp('<' + tag + '\\b[^>]*>[\\s\\S]*?<\\/' + tag + '>'));
  if (!match) throw new Error('Missing ' + tag + ': ' + file);
  return match[0];
};
const clean = (html) => html.replace(/<span class="demo-tag">[^<]*<\/span>/g, '').replace(/<span class="[^"]*">Bản thiết kế giao diện<\/span>/g, '');
const views = {
  SCHEDULE: clean(extract('pages/technician.html', 'main')),
  DETAIL: clean(extract('pages/technician-task.html', 'main')) + extract('pages/technician-task.html', 'footer') + extract('pages/technician-task.html', 'dialog'),
  RESOLVE: clean(extract('pages/ticket-resolve.html', 'main')) + extract('pages/ticket-resolve.html', 'dialog')
};
const html = read('pages/templates/technician-shell.html').replace(/{{(SCHEDULE|DETAIL|RESOLVE)_VIEW}}/g, (_, name) => views[name]);
fs.writeFileSync(path.join(root, 'technician-app.html'), html);
console.log('Built technician-app.html with external CSS and JavaScript.');
