(() => {
  const calendar = document.querySelector('.month-days');
  const modal = document.querySelector('#calendar-modal');
  if (!calendar || !modal) return;

  const dateHeading = modal.querySelector('[data-modal-date]');
  const eventList = modal.querySelector('[data-modal-events]');
  const emptyState = modal.querySelector('[data-modal-empty]');
  const assignmentPanel = modal.querySelector('[data-modal-assignment]');
  const detailsActions = modal.querySelector('[data-modal-details-actions]');
  const assignmentActions = modal.querySelector('[data-modal-assignment-actions]');
  const assignmentDate = modal.querySelector('#modal-assignment-date');
  const assignmentTime = modal.querySelector('#modal-assignment-time');
  const ticketSelect = modal.querySelector('#modal-ticket');
  const technicianSelect = modal.querySelector('#modal-technician');
  const staffFilter = document.querySelector('.staff-filter select');
  let selectedDate = '';
  let dayHasEvents = false;

  function getCellDate(cell) {
    if (!cell) return null;
    if (cell.dataset.date) {
      const [year, month, day] = cell.dataset.date.split('-').map(Number);
      return new Date(year, month - 1, day);
    }
    const heading = document.querySelector('.calendar-toolbar .section-heading')?.textContent || '';
    const match = heading.match(/Th\u00e1ng\s+(\d{1,2})(?:,|\s+n\u0103m)\s*(\d{4})/);
    if (!match) return null;

    let month = Number(match[1]);
    let year = Number(match[2]);
    const day = Number(cell.querySelector('.day-number')?.textContent);
    const index = [...calendar.querySelectorAll('.day-cell')].indexOf(cell);
    if (cell.classList.contains('muted-day') && day > 20 && index < 7) month -= 1;
    else if (cell.classList.contains('muted-day') && day < 10 && index > 27) month += 1;
    if (month < 1) { month = 12; year -= 1; }
    if (month > 12) { month = 1; year += 1; }
    return new Date(year, month - 1, day);
  }

  const initialDate = getCellDate(calendar.querySelector('.today-number')?.closest('.day-cell')) || new Date();
  const todayDate = new Date(initialDate.getFullYear(), initialDate.getMonth(), initialDate.getDate());
  let visibleMonth = new Date(initialDate.getFullYear(), initialDate.getMonth(), 1);
  const eventsByDate = new Map();

  function dateKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  [...calendar.querySelectorAll('.day-cell')].forEach((cell) => {
    const date = getCellDate(cell);
    if (date) eventsByDate.set(dateKey(date), [...cell.querySelectorAll('.cal-event')].map((event) => event.cloneNode(true)));
  });

  function labelDate(date) {
    const label = date.toLocaleDateString('vi-VN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    return label.charAt(0).toLocaleUpperCase('vi-VN') + label.slice(1);
  }

  function prepareCell(cell) {
    cell.setAttribute('role', 'group');
    cell.setAttribute('tabindex', '0');
    cell.classList.add('day-cell-clickable');
    const date = getCellDate(cell);
    if (date) cell.setAttribute('aria-label', `${labelDate(date)}, m\u1edf l\u1ecbch trong ng\u00e0y`);
  }

  function renderMonth() {
    const year = visibleMonth.getFullYear();
    const month = visibleMonth.getMonth();
    const heading = document.querySelector('.calendar-toolbar .section-heading');
    if (heading) heading.textContent = `Th\u00e1ng ${month + 1} n\u0103m ${year}`;

    const firstDay = new Date(year, month, 1);
    const mondayOffset = (firstDay.getDay() + 6) % 7;
    const cellCount = Math.ceil((mondayOffset + new Date(year, month + 1, 0).getDate()) / 7) * 7;
    const fragment = document.createDocumentFragment();
    for (let index = 0; index < cellCount; index += 1) {
      const date = new Date(year, month, index - mondayOffset + 1);
      const cell = document.createElement('div');
      cell.className = 'day-cell';
      cell.dataset.date = dateKey(date);
      if (date.getMonth() !== month) cell.classList.add('muted-day');
      if (dateKey(date) === dateKey(todayDate)) cell.classList.add('today-cell');

      const number = document.createElement('span');
      number.className = 'day-number';
      number.textContent = date.getDate();
      if (dateKey(date) === dateKey(todayDate)) number.classList.add('today-number');

      const addButton = document.createElement('button');
      addButton.className = 'day-schedule-add';
      addButton.type = 'button';
      addButton.setAttribute('data-day-add', '');
      addButton.textContent = '+ X\u1ebfp l\u1ecbch';
      cell.append(number, addButton);

      (eventsByDate.get(dateKey(date)) || []).forEach((event) => cell.append(event.cloneNode(true)));
      prepareCell(cell);
      fragment.append(cell);
    }
    calendar.replaceChildren(fragment);
    const filter = staffFilter?.value;
    if (filter) applyStaffFilter(filter);
  }

  function applyStaffFilter(selectedStaff) {
    calendar.querySelectorAll('.cal-event').forEach((event) => {
      const assignedStaff = event.querySelector('.event-meta')?.textContent.trim() || '';
      event.hidden = selectedStaff !== 'T\u1ea5t c\u1ea3 nh\u00e2n vi\u00ean' && assignedStaff !== selectedStaff;
    });
  }

  function resetModalView() {
    assignmentPanel.hidden = true;
    detailsActions.hidden = false;
    assignmentActions.hidden = true;
    eventList.hidden = false;
    emptyState.hidden = dayHasEvents;
  }

  function openAssignment(eventInfo = {}) {
    eventList.hidden = true;
    emptyState.hidden = true;
    assignmentPanel.hidden = false;
    detailsActions.hidden = true;
    assignmentActions.hidden = false;
    assignmentDate.value = selectedDate;
    const eventTime = typeof eventInfo === 'string'
      ? eventInfo.split('\u00b7')[0]?.trim()
      : eventInfo.time;
    const eventTitle = typeof eventInfo === 'string' ? eventInfo : (eventInfo.title || '');
    assignmentTime.value = eventTime || '09:00';

    const normalized = eventTitle.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    if (normalized.includes('may chieu')) {
      ticketSelect.selectedIndex = 1;
      technicianSelect.selectedIndex = 1;
    } else if (normalized.includes('may tinh') || normalized.includes('wi-fi')) {
      ticketSelect.selectedIndex = 2;
      technicianSelect.selectedIndex = 2;
    } else {
      ticketSelect.selectedIndex = 0;
      technicianSelect.selectedIndex = 0;
    }
  }

  function openDay(cell) {
    const date = getCellDate(cell);
    if (!date) return;
    selectedDate = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const title = date.toLocaleDateString('vi-VN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    dateHeading.textContent = title.charAt(0).toLocaleUpperCase('vi-VN') + title.slice(1);
    resetModalView();
    eventList.replaceChildren();

    const cellEvents = [...cell.querySelectorAll('.cal-event')];
    const events = cellEvents.filter((event) => !event.hidden);
    dayHasEvents = events.length > 0;
    emptyState.hidden = dayHasEvents;
    emptyState.textContent = cellEvents.length > 0
      ? 'Kh\u00f4ng c\u00f3 l\u1ecbch c\u1ee7a nh\u00e2n vi\u00ean \u0111ang ch\u1ecdn trong ng\u00e0y n\u00e0y.'
      : 'Ng\u00e0y n\u00e0y ch\u01b0a c\u00f3 l\u1ecbch x\u1eed l\u00fd. B\u1ea1n c\u00f3 th\u1ec3 x\u1ebfp l\u1ecbch m\u1edbi.';
    events.forEach((event) => {
      const rawParts = event.textContent.split('\u00b7').map((part) => part.trim());
      const time = event.querySelector('.event-time')?.textContent.trim() || rawParts[0] || '';
      const eventTitle = event.querySelector('.event-title')?.textContent.trim() || rawParts.slice(1).join(' \u00b7 ');
      const technician = event.querySelector('.event-meta')?.textContent.trim() || '';
      const row = document.createElement('article');
      row.className = `modal-event ${[...event.classList].find((name) => name.startsWith('event-')) || 'event-blue'}`;
      const timeLabel = document.createElement('time');
      timeLabel.textContent = time;
      const details = document.createElement('div');
      details.className = 'modal-event-details';
      const titleLabel = document.createElement('strong');
      titleLabel.textContent = eventTitle || 'L\u1ecbch x\u1eed l\u00fd s\u1ef1 c\u1ed1';
      const meta = document.createElement('small');
      meta.textContent = technician ? `K\u1ef9 thu\u1eadt vi\u00ean: ${technician}` : '\u0110\u00e3 c\u00f3 l\u1ecbch ph\u00e2n c\u00f4ng';
      const editButton = document.createElement('button');
      editButton.className = 'button button-secondary modal-edit';
      editButton.type = 'button';
      editButton.textContent = 'Xem / s\u1eeda';
      editButton.addEventListener('click', () => openAssignment({ time, title: eventTitle }));
      details.append(titleLabel, meta);
      row.append(timeLabel, details, editButton);
      eventList.append(row);
    });
    modal.showModal();
  }

  renderMonth();

  calendar.addEventListener('click', (event) => {
    const cell = event.target.closest('.day-cell');
    if (!cell) return;
    if (event.target.closest('[data-day-add]')) {
      event.preventDefault();
      openDay(cell);
      openAssignment();
      return;
    }
    openDay(cell);
  });
  calendar.addEventListener('keydown', (event) => {
    const cell = event.target.closest('.day-cell');
    if (cell && event.target === cell && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      openDay(cell);
    }
  });

  staffFilter?.addEventListener('change', () => {
    applyStaffFilter(staffFilter.value);
  });

  document.querySelector('[data-calendar-prev]')?.addEventListener('click', () => {
    visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
    renderMonth();
  });
  document.querySelector('[data-calendar-next]')?.addEventListener('click', () => {
    visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
    renderMonth();
  });

  document.querySelector('.today-button')?.addEventListener('click', () => {
    visibleMonth = new Date(todayDate.getFullYear(), todayDate.getMonth(), 1);
    renderMonth();
    calendar.querySelector('.today-number')?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
  });

  modal.querySelector('[data-modal-add]')?.addEventListener('click', () => openAssignment());
  modal.querySelector('[data-modal-cancel]')?.addEventListener('click', resetModalView);
  modal.querySelector('[data-modal-save]')?.addEventListener('click', () => modal.close());
  document.querySelector('[data-open-assignment]')?.addEventListener('click', () => {
    const todayCell = calendar.querySelector('.today-number')?.closest('.day-cell');
    if (todayCell) {
      openDay(todayCell);
      openAssignment();
    }
  });
  modal.addEventListener('click', (event) => {
    if (event.target === modal) modal.close();
  });
})();
