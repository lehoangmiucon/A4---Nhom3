/* Single-page shell: templates are embedded, so navigation also works from a local HTML file. */
(() => {
  const routes = window.TechnicianRoutes;
  const outlet = document.querySelector('#app-view');
  const drafts = new Map();
  const calendarState = {};
  let active = null;
  let lastHash = location.hash || '#schedule';
  let renderVersion = 0;

  function announce(message) {
    document.querySelector('#app-announcement').textContent = message;
  }
  function canNavigate() {
    if (active?.lifecycle?.isSaving?.()) {
      announce('Đang lưu báo cáo thử. Vui lòng chờ hoàn tất.');
      return false;
    }
    return true;
  }
  function navigate(hash) {
    if (!canNavigate()) return;
    if (location.hash === hash) return;
    location.hash = hash;
  }
  function updateDraftLink() {
    const draft = [...drafts.entries()].find(([, item]) => item.lifecycle.isDirty?.());
    const link = document.querySelector('#draft-shortcut');
    link.hidden = !draft;
    if (draft) {
      link.href = routes.make('resolve', draft[0]);
      link.textContent = `Tiếp tục bản nháp ${draft[0]} →`;
    }
  }
  function normalizeLinks(node, id) {
    node.querySelectorAll('a[href]').forEach((link) => {
      const target = routes.fromHref(link.getAttribute('href'), id);
      if (target) link.setAttribute('href', target);
    });
  }
  function updateShell(route) {
    const reportView = ['reports', 'report'].includes(route.view);
    document.querySelectorAll('[data-app-nav]').forEach((link) => {
      const selected = link.dataset.appNav === (reportView ? 'reports' : 'schedule');
      if (selected) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
    document.querySelector('#app-mobile-nav').hidden = !['schedule', 'reports'].includes(route.view);
    const breadcrumb = document.querySelector('#app-breadcrumb');
    breadcrumb.hidden = !['task', 'resolve', 'report'].includes(route.view);
    breadcrumb.querySelector('[data-route-task]').href = routes.make('task', route.id);
    document.querySelector('#breadcrumb-current').textContent = route.view === 'resolve' ? 'Lập báo cáo nghiệm thu' : route.view === 'report' ? 'Báo cáo đã xác nhận' : 'Chi tiết nhiệm vụ';
    document.body.dataset.currentView = route.view;
    document.title = route.view === 'reports' ? 'Báo cáo nghiệm thu · Helpdesk' : route.view === 'resolve' ? 'Lập báo cáo nghiệm thu · Helpdesk' : 'Không gian kỹ thuật viên · Helpdesk';
  }
  async function render() {
    if (!canNavigate()) {
      history.replaceState(null, '', lastHash);
      return;
    }
    const version = ++renderVersion;
    const route = routes.parse(location.hash);
    if (active) {
      active.node.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
      if (!active.draft) active.abort.abort();
    }
    lastHash = location.hash || '#schedule';
    updateShell(route);
    announce('');
    if (route.view === 'missing') {
      outlet.innerHTML = '<main class="tech-main"><section class="empty-state"><h1>Không tìm thấy nội dung</h1><p>Chọn Lịch của tôi để tiếp tục.</p><a class="primary-button" href="#schedule">Về lịch của tôi</a></section></main>';
      active = null;
      return;
    }
    const isDraft = route.view === 'resolve';
    let page = isDraft ? drafts.get(route.id) : null;
    const reused = Boolean(page);
    if (!page) {
      const templateName = isDraft ? 'resolve' : ['task', 'report'].includes(route.view) ? 'detail' : 'schedule';
      const node = document.createElement('div');
      node.append(document.querySelector(`#template-${templateName}`).content.cloneNode(true));
      page = { node, lifecycle: {}, abort: new AbortController(), draft: isDraft };
      if (isDraft) drafts.set(route.id, page);
    }
    active = page;
    normalizeLinks(page.node, route.id);
    outlet.replaceChildren(page.node);
    outlet.setAttribute('aria-busy', 'true');
    try {
      if (!reused) {
        if (isDraft) {
          page.ready = window.initTicketResolve({
            root: page.node, id: route.id, lifecycle: page.lifecycle,
            onComplete(id) {
              page.lifecycle.dispose?.();
              drafts.delete(id);
              updateDraftLink();
              navigate(routes.make('schedule', null, id));
            }
          });
        } else {
          page.ready = window.initTechnicianPage({ root: page.node, spa: true, signal: page.abort.signal, page: ['task', 'report'].includes(route.view) ? 'detail' : 'schedule', view: route.view, id: route.id, completed: route.completed, state: calendarState });
        }
      }
      await page.ready;
      if (version !== renderVersion) return;
      normalizeLinks(page.node, route.id);
      updateDraftLink();
      window.scrollTo({ top: 0, behavior: 'instant' });
      const heading = page.node.querySelector('h1');
      if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus({ preventScroll: true }); }
    } catch {
      if (version !== renderVersion) return;
      announce('Chưa tải được nội dung. Vui lòng quay về Lịch của tôi và mở lại.');
      if (isDraft) { page.lifecycle.dispose?.(); drafts.delete(route.id); }
    } finally {
      if (version === renderVersion) outlet.removeAttribute('aria-busy');
    }
  }
  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const href = link.getAttribute('href');
    const route = routes.parse(location.hash);
    const next = routes.fromHref(href, route.id);
    if (next) { event.preventDefault(); navigate(next); }
    else if (['#work', '#evidence', '#handover'].includes(href)) {
      event.preventDefault();
      active?.node.querySelector(href)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
  ['input', 'click', 'pointerup'].forEach((name) => document.addEventListener(name, updateDraftLink));
  window.addEventListener('hashchange', render);
  window.addEventListener('beforeunload', (event) => {
    if ([...drafts.values()].some((page) => page.lifecycle.isDirty?.())) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
  document.querySelector('#app-help-open').addEventListener('click', () => document.querySelector('#app-help').showModal());
  document.querySelector('#app-help-close').addEventListener('click', () => document.querySelector('#app-help').close());
  document.querySelector('#app-help-start').addEventListener('click', () => document.querySelector('#app-help').close());
  render();
})();
