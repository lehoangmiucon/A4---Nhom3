(() => {
  const names = new Set(['schedule', 'reports', 'task', 'report', 'resolve']);
  const ticketViews = new Set(['task', 'report', 'resolve']);
  function parse(hash) {
    const value = (hash || '#schedule').replace(/^#/, '');
    const separator = value.indexOf('?');
    const view = (separator < 0 ? value : value.slice(0, separator)) || 'schedule';
    const params = new URLSearchParams(separator < 0 ? '' : value.slice(separator + 1));
    return { view: names.has(view) ? view : 'missing', id: ticketViews.has(view) ? params.get('id') || 'SC-2026-084' : null, completed: params.get('completed') };
  }
  function make(view, id, completed) {
    const params = new URLSearchParams();
    if (ticketViews.has(view) && id) params.set('id', id);
    if (completed) params.set('completed', completed);
    return `#${view}${params.size ? `?${params}` : ''}`;
  }
  function fromHref(href, currentId) {
    if (href.startsWith('#')) {
      const route = parse(href);
      if (route.view === 'missing') return null;
      const hasId = new URLSearchParams(href.split('?')[1] || '').has('id');
      return make(route.view, hasId ? route.id : currentId || route.id, route.completed);
    }
    const url = new URL(href, 'https://helpdesk.invalid/pages/');
    if (url.origin !== 'https://helpdesk.invalid') return null;
    const name = url.pathname.split('/').pop();
    if (name === 'technician.html') return make(url.hash === '#reports' ? 'reports' : 'schedule', null, url.searchParams.get('completed'));
    if (name === 'technician-task.html') return make(url.hash === '#report' ? 'report' : 'task', url.searchParams.get('id') || currentId);
    if (name === 'ticket-resolve.html') return make('resolve', url.searchParams.get('id') || currentId);
    return null;
  }
  window.TechnicianRoutes = { parse, make, fromHref };
})();
