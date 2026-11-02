/* Technician pages: local, clearly labelled UI prototype. */
window.initTechnicianPage = async (options = {}) => {
  const demo = window.TechnicianDemo;
  const root = options.root || document;
  const $ = (selector) => root.querySelector(selector);
  const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  const status = (task) => `<span class="task-status ${escape(task.status)}">${escape(demo.labels[task.status])}</span>`;
  const dayDate = (key) => new Date(`${key}T12:00:00`);
  const dateKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  const longDate = (key) => dayDate(key).toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
  const reportDate = (value) => new Date(value).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh', hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
  let tasks;
  try { tasks = await demo.all(); }
  catch {
    if (options.signal?.aborted) return;
    tasks = demo.samples();
    $('#storage-message').hidden = false;
    $('#storage-message').textContent = 'Trình duyệt chưa mở được bộ nhớ bản thử. Bạn vẫn xem được dữ liệu mẫu, nhưng có thể không lưu được thao tác thử.';
  }
  if (options.signal?.aborted) return;
  if ((options.page || document.body.dataset.page) === 'schedule') {
    const state = options.state || {};
    let selected = state.selected || '2026-11-02';
    let weekStart = dayDate(state.weekStart || '2026-11-02');
    $('#task-search').value = state.search || '';
    $('#task-status').value = state.status || 'all';
    const card = (task) => `<a class="task-card ${task.status === 'in_progress' ? 'current' : ''}" href="${demo.detailUrl(task.id)}"><div class="task-time"><strong>${escape(task.time)}</strong><small>${escape(task.end)}</small></div><div><div class="task-card-meta"><span class="ticket-code">${escape(task.id)}</span>${status(task)}</div><h3>${escape(task.title)}</h3><p>${escape(task.location)}</p>${task.priority === 'high' && task.status !== 'resolved' ? '<span class="priority-label">● Ưu tiên cao</span>' : ''}</div><span class="task-arrow" aria-hidden="true">›</span></a>`;
    function renderList() {
      const daily = tasks.filter((task) => task.date === selected);
      const search = $('#task-search').value.trim().toLocaleLowerCase('vi');
      const filter = $('#task-status').value;
      Object.assign(state, { selected, weekStart: dateKey(weekStart), search, status: filter });
      const visible = daily.filter((task) => (filter === 'all' || task.status === filter) && `${task.id} ${task.title} ${task.location}`.toLocaleLowerCase('vi').includes(search)).sort((a, b) => a.time.localeCompare(b.time));
      $('#day-label').textContent = longDate(selected);
      $('#day-count').textContent = `${visible.length} / ${daily.length} nhiệm vụ`;
      $('#task-list').innerHTML = visible.length ? visible.map(card).join('') : '<div class="empty-state"><h2>Không có nhiệm vụ phù hợp</h2><p>Thử chọn ngày khác hoặc thay đổi bộ lọc.</p></div>';
    }
    function renderWeek() {
      const end = new Date(weekStart);
      end.setDate(end.getDate() + 6);
      const short = (date) => date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
      $('#week-label').textContent = `${short(weekStart)} – ${short(end)}/${end.getFullYear()}`;
      $('#week-days').replaceChildren();
      for (let index = 0; index < 7; index++) {
        const date = new Date(weekStart);
        date.setDate(date.getDate() + index);
        const key = dateKey(date);
        const count = tasks.filter((task) => task.date === key).length;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `day-button ${count ? 'has-tasks' : ''}`;
        button.setAttribute('aria-pressed', String(selected === key));
        button.setAttribute('aria-label', `${longDate(key)}, ${count} nhiệm vụ`);
        button.innerHTML = `<span>${['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'][index]}</span><strong>${date.getDate()}</strong><i aria-hidden="true"></i>`;
        button.addEventListener('click', () => {
          selected = key;
          renderWeek();
          $('#week-days').children[index].focus();
        });
        $('#week-days').append(button);
      }
      const weekly = tasks.filter((task) => task.date >= dateKey(weekStart) && task.date <= dateKey(end));
      for (const [name, selector] of [['assigned', '#stat-assigned'], ['in_progress', '#stat-progress'], ['resolved', '#stat-resolved']]) {
        $(selector).textContent = String(weekly.filter((task) => task.status === name).length).padStart(2, '0');
      }
      renderList();
    }
    for (const [selector, delta] of [['#previous-week', -7], ['#next-week', 7]]) {
      $(selector).addEventListener('click', () => {
        weekStart.setDate(weekStart.getDate() + delta);
        selected = dateKey(weekStart);
        renderWeek();
      });
    }
    $('#demo-today').addEventListener('click', () => {
      selected = '2026-11-02'; weekStart = dayDate(selected); renderWeek();
    });
    $('#task-search').addEventListener('input', renderList);
    $('#task-status').addEventListener('change', renderList);
    const active = tasks.find((task) => task.status === 'in_progress');
    $('#current-task').innerHTML = active ? `<h2>${escape(active.title)}</h2><p>${escape(active.location)}</p><span class="focus-time">${escape(active.date.split('-').reverse().join('/'))} · ${escape(active.time)} – ${escape(active.end)}</span><a href="${demo.detailUrl(active.id)}" class="primary-button">Tiếp tục nhiệm vụ →</a>` : '<h2>Sẵn sàng cho nhiệm vụ tiếp theo</h2><p>Chọn một công việc trong lịch để xem thông tin và bắt đầu xử lý.</p>';
    const reports = tasks.filter((task) => task.report).sort((a, b) => new Date(b.report.submittedAt) - new Date(a.report.submittedAt));
    $('#report-list').innerHTML = reports.length ? reports.map((task) => `<a class="report-card" href="${demo.reportUrl(task.id)}"><div class="task-card-meta"><span class="ticket-code">${escape(task.id)}</span>${status(task)}</div><h2>${escape(task.title)}</h2><p>${escape(task.location)}</p><p>Người xác nhận: ${escape(task.report.verifier)}</p><div class="report-card-foot"><span>${escape(reportDate(task.report.submittedAt))}</span><span>Xem báo cáo →</span></div></a>`).join('') : '<div class="empty-state"><h2>Chưa có báo cáo</h2><p>Báo cáo xuất hiện tại đây sau khi hoàn tất nghiệm thu.</p></div>';
    function showView() {
      const view = (options.view || location.hash.slice(1)) === 'reports' ? 'reports' : 'schedule';
      $('#schedule-view').hidden = view !== 'schedule';
      $('#reports-view').hidden = view !== 'reports';
      root.querySelectorAll('[data-view]').forEach((link) => {
        if (link.dataset.view === view) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
      });
    }
    if (!options.spa) window.addEventListener('hashchange', showView);
    const completed = tasks.find((task) => task.id === (options.completed || new URLSearchParams(location.search).get('completed')) && task.report);
    if (completed) {
      const message = $('#storage-message');
      message.hidden = false;
      message.innerHTML = `Đã lưu báo cáo ${escape(completed.id)} trong bản thử trên trình duyệt. <a class="underline font-semibold" href="${demo.reportUrl(completed.id)}">Xem báo cáo nghiệm thu →</a>`;
      selected = completed.date;
      weekStart = dayDate(selected);
      weekStart.setDate(weekStart.getDate() - (weekStart.getDay() + 6) % 7);
      $('#task-search').value = '';
      $('#task-status').value = 'all';
    }
    renderWeek(); showView();
    return;
  }

  let task = tasks.find((item) => item.id === (options.id || demo.selectedId()));
  if (!task) {
    $('#missing-task').hidden = false;
    $('.task-bottom').hidden = true;
    return;
  }
  $('#task-content').hidden = false;
  document.title = `${task.id} · ${task.title} · Helpdesk`;
  const setText = (selector, value) => { $(selector).textContent = value; };
  for (const [selector, value] of [
    ['#detail-code', task.id], ['#detail-title', task.title], ['#detail-location', task.location],
    ['#detail-description', task.description], ['#detail-device', task.device], ['#detail-priority', task.priority === 'high' ? 'Ưu tiên cao' : 'Thông thường'],
    ['#detail-reporter', task.reporter], ['#detail-role', task.role], ['#detail-instruction', task.instruction],
    ['#detail-time', `${task.time} – ${task.end}`], ['#detail-date', longDate(task.date)]
  ]) setText(selector, value);
  function renderReport() {
    const report = task.report;
    if (!report) {
      $('#report-content').innerHTML = '<div class="empty-state"><h2>Chưa có báo cáo nghiệm thu</h2><p>Sau khi khắc phục xong, lập báo cáo, đính kèm ảnh và lấy chữ ký người tiếp nhận.</p></div>';
      return;
    }
    $('#report-content').innerHTML = `<div class="report-banner"><span class="guide-icon" aria-hidden="true">✓</span><div><h2>Đã xác nhận bàn giao</h2><p>${escape(report.verifier)} · ${escape(report.role)}<br>${escape(reportDate(report.submittedAt))} · ${report.sample ? 'Báo cáo mẫu minh họa' : 'Báo cáo thử, chưa gửi lên hệ thống'}</p></div></div>
      <div class="report-layout"><div class="space-y-5"><section class="tech-panel"><p class="eyebrow">KẾT QUẢ XỬ LÝ</p><dl class="facts-grid" style="border:0;padding:0;margin:20px 0"><div><dt>Nhóm sự cố</dt><dd>${escape(report.category)}</dd></div><div><dt>Nguyên nhân</dt><dd>${escape(report.rootCause)}</dd></div></dl><h2>Phương án đã thực hiện</h2><p class="detail-description">${escape(report.note)}</p></section><section class="tech-panel"><h2>Vật tư đã sử dụng</h2>${report.materials.length ? `<table class="report-table"><thead><tr><th>Tên vật tư</th><th>Số lượng</th><th>Đơn vị</th></tr></thead><tbody>${report.materials.map((item) => `<tr><td>${escape(item.name)}</td><td>${escape(item.quantity)}</td><td>${escape(item.unit)}</td></tr>`).join('')}</tbody></table>` : '<p class="muted mt-4">Không phát sinh vật tư thay thế.</p>'}</section><section class="tech-panel"><h2>Ảnh minh chứng</h2><div id="report-before"></div><div id="report-after"></div></section></div>
      <aside><section class="tech-panel"><p class="eyebrow">NGƯỜI TIẾP NHẬN XÁC NHẬN</p><h2 class="verifier-name">${escape(report.verifier)}</h2><p class="muted mt-2">${escape(report.role)}</p><div id="report-signature"></div><p class="muted mt-4">Đã kiểm tra và tiếp nhận thiết bị sau xử lý.</p></section><p class="aside-note mt-4">Đây là xác nhận của người tiếp nhận tại hiện trường, không phải bước duyệt của quản lý.</p></aside></div>`;
    for (const kind of ['before', 'after']) {
      const section = $(`#report-${kind}`);
      const title = document.createElement('h3');
      title.className = 'text-sm font-semibold mt-5';
      title.textContent = kind === 'before' ? 'Trước xử lý' : 'Sau xử lý';
      section.append(title);
      if (!report[kind].length) {
        const text = document.createElement('p');
        text.className = report.sample && kind === 'after' ? 'sample-evidence' : 'muted mt-2';
        text.textContent = report.sample && kind === 'after' ? 'Vùng ảnh nghiệm thu · Báo cáo mẫu không chứa ảnh thực tế' : 'Không đính kèm ảnh.';
        section.append(text);
      } else {
        const gallery = document.createElement('div');
        gallery.className = 'report-photos';
        report[kind].forEach((photo, index) => {
          if (!/^data:image\/(jpeg|png);base64,/.test(photo.data)) return;
          const button = document.createElement('button');
          button.type = 'button';
          button.setAttribute('aria-label', `Xem ảnh ${kind === 'before' ? 'trước' : 'sau'} xử lý ${index + 1}`);
          const img = document.createElement('img');
          img.src = photo.data;
          img.alt = photo.name;
          button.append(img);
          button.addEventListener('click', () => {
            $('#large-image').src = photo.data;
            $('#large-image').alt = photo.name;
            $('#image-dialog').showModal();
          });
          gallery.append(button);
        });
        section.append(gallery);
      }
    }
    if (report.signature && report.signature.startsWith('data:image/png;base64,')) {
      const img = document.createElement('img'); img.src = report.signature; img.alt = `Chữ ký ${report.verifier}`; img.className = 'report-signature';
      $('#report-signature').append(img);
    } else $('#report-signature').innerHTML = '<div class="sample-evidence">Vùng chữ ký · Dữ liệu minh họa</div>';
  }
  function renderState() {
    setText('#detail-status', demo.labels[task.status]);
    $('#detail-status').className = `task-status ${task.status}`;
    $('#task-timeline').innerHTML = `<li class="done">Được phân công<small>Tuấn Lê phụ trách xử lý</small></li><li class="${task.status !== 'assigned' ? 'done' : ''}">Xử lý tại hiện trường<small>${task.status === 'assigned' ? 'Chưa bắt đầu' : 'Đã bắt đầu công việc'}</small></li><li class="${task.report ? 'done' : ''}">Nghiệm thu & bàn giao<small>${task.report ? 'Đã có báo cáo xác nhận' : 'Chờ báo cáo và chữ ký'}</small></li>`;
    renderReport(); showPane();
  }
  function showPane() {
    const isReport = (options.view || location.hash.slice(1)) === 'report';
    $('#task-pane').hidden = isReport;
    $('#report-pane').hidden = !isReport;
    for (const [selector, active] of [['#task-tab', !isReport], ['#report-tab', isReport]]) {
      if (active) $(selector).setAttribute('aria-current', 'page');
      else $(selector).removeAttribute('aria-current');
    }
    const actions = $('#task-actions');
    actions.replaceChildren();
    const back = document.createElement('a'); back.href = demo.scheduleUrl(); back.className = 'secondary-button'; back.textContent = 'Về lịch của tôi'; actions.append(back);
    if (task.status === 'assigned') {
      setText('#action-hint', 'Sẵn sàng đến hiện trường? Bắt đầu xử lý nhiệm vụ.');
      const button = document.createElement('button'); button.type = 'button'; button.className = 'primary-button'; button.textContent = 'Bắt đầu xử lý →';
      button.addEventListener('click', async () => {
        button.disabled = true; button.textContent = 'Đang cập nhật bản thử…';
        try {
          await demo.update(task.id, { status: 'in_progress' });
          if (options.signal?.aborted) return;
          task = { ...task, status: 'in_progress' };
          $('#action-error').textContent = '';
          renderState();
          $('#task-actions .primary-button').focus();
        } catch {
          if (options.signal?.aborted) return;
          $('#action-error').textContent = 'Chưa lưu được thao tác thử trên trình duyệt. Vui lòng thử lại.';
          button.disabled = false; button.textContent = 'Bắt đầu xử lý →';
        }
      }); actions.append(button);
    } else if (task.status === 'in_progress') {
      setText('#action-hint', 'Đã khắc phục xong? Lập báo cáo và lấy chữ ký nghiệm thu.');
      const link = document.createElement('a'); link.href = demo.resolveUrl(task.id); link.className = 'primary-button'; link.textContent = 'Lập báo cáo nghiệm thu →'; actions.append(link);
    } else {
      setText('#action-hint', 'Đã có xác nhận của người tiếp nhận · Bản thử trên trình duyệt');
      if (!isReport) { const link = document.createElement('a'); link.href = demo.reportUrl(task.id); link.className = 'primary-button'; link.textContent = 'Xem báo cáo nghiệm thu →'; actions.append(link); }
    }
  }
  $('#close-image').addEventListener('click', () => $('#image-dialog').close());
  if (!options.spa) window.addEventListener('hashchange', showPane);
  renderState();
};
if (!document.body.dataset.app) window.initTechnicianPage();
