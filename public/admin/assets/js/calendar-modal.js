(() => {
  const config = window.HELPDESK_CONFIG || {};
  const appBase = new URL(config.appUrl || '/', window.location.origin).pathname.replace(/\/+$/, '');
  const calendar = document.querySelector('.month-days');
  const modal = document.querySelector('#calendar-modal');
  if (!calendar || !modal) return;

  const dateHeading = modal.querySelector('[data-modal-date]');
  const eventList = modal.querySelector('[data-modal-events]');
  const emptyState = modal.querySelector('[data-modal-empty]');
  const assignmentPanel = modal.querySelector('[data-modal-assignment]');
  const detailsActions = modal.querySelector('[data-modal-details-actions]');
  const assignmentActions = modal.querySelector('[data-modal-assignment-actions]');
  const cancelScheduleButton = modal.querySelector('[data-modal-cancel-schedule]');
  const saveAssignmentButton = modal.querySelector('[data-modal-save]');
  const assignmentTime = modal.querySelector('#modal-assignment-time');
  const assignmentNote = modal.querySelector('#modal-assignment-note');
  const assignmentFeedback = modal.querySelector('[data-modal-feedback]');
  const addAssignmentButton = modal.querySelector('[data-modal-add]');
  const openAssignmentButton = document.querySelector('[data-open-assignment]');
  const ticketSelect = modal.querySelector('#modal-ticket');
  const technicianSelect = modal.querySelector('#modal-technician');
  const staffFilter = document.querySelector('.staff-filter select');
  const categoryFilter = document.querySelector('.category-filter select');
  const statusFilter = document.querySelector('.status-filter select');
  const monthHead = document.querySelector('.month-head');
  const viewInputs = [...document.querySelectorAll('input[name="calendar-view"]')];
  const allEvents = (() => {
    try { return JSON.parse(document.querySelector('#schedule-event-data')?.textContent || '[]'); }
    catch (_) { return []; }
  })();
  let selectedDate = '';
  let selectedScheduleId = '';
  let selectedScheduleStatus = '';
  let assignmentLocked = false;
  let assignmentSaving = false;
  let assignmentValidationShown = false;
  let dayHasEvents = false;
  let remoteScheduleIds = null;
  let filterRequestSequence = 0;
  let refreshTicketDropdown = () => {};
  let refreshTechnicianDropdown = () => {};
  function assignmentErrors() {
    const errors = [];
    if (!ticketSelect.value) errors.push({ field: ticketSelect, message: 'Vui lòng chọn phiếu sự cố.' });
    if (!technicianSelect.value || technicianSelect.disabled) {
      errors.push({ field: technicianSelect, message: ticketSelect.value && technicianSelect.disabled
        ? 'Chưa có nhân viên phù hợp với danh mục của phiếu này.'
        : 'Vui lòng chọn nhân viên kỹ thuật.' });
    }
    if (!assignmentTime.value) errors.push({ field: assignmentTime, message: 'Vui lòng chọn giờ bắt đầu.' });
    else if (!assignmentTime.checkValidity()) errors.push({ field: assignmentTime, message: 'Giờ bắt đầu không hợp lệ.' });
    if (!assignmentNote.value.trim()) errors.push({ field: assignmentNote, message: 'Vui lòng nhập ghi chú phân công.' });
    else if (!assignmentNote.checkValidity() || assignmentNote.value.length > 2000)
      errors.push({ field: assignmentNote, message: 'Ghi chú không được vượt quá 2000 ký tự.' });
    return errors;
  }

  function markAssignmentField(field, invalid) {
    const trigger = field.closest('.modal-custom-select')?.querySelector('.modal-select-trigger');
    [field, trigger].filter(Boolean).forEach((control) => {
      if (invalid) control.setAttribute('aria-invalid', 'true');
      else control.removeAttribute('aria-invalid');
    });
  }

  function renderAssignmentErrors() {
    const errors = assignmentErrors();
    [ticketSelect, technicianSelect, assignmentTime, assignmentNote].forEach((field) =>
      markAssignmentField(field, errors.some((error) => error.field === field))
    );
    assignmentFeedback.textContent = errors.map((error) => error.message).join(' ');
    assignmentFeedback.hidden = !errors.length;
    return errors;
  }

  function clearAssignmentFeedback() {
    assignmentValidationShown = false;
    assignmentFeedback.hidden = true;
    assignmentFeedback.textContent = '';
    [ticketSelect, technicianSelect, assignmentTime, assignmentNote].forEach((field) => markAssignmentField(field, false));
  }

  function showAssignmentFeedback(message) {
    assignmentFeedback.textContent = message;
    assignmentFeedback.hidden = false;
    assignmentFeedback.scrollIntoView({ block: 'nearest' });
  }

  function updateSaveButton() {
    saveAssignmentButton.disabled = assignmentSaving || assignmentLocked || assignmentPanel.hidden
      || !selectedDate || isPastDate(selectedDate);
  }

  function updateAssignmentForm() {
    updateSaveButton();
    if (assignmentValidationShown) renderAssignmentErrors();
  }

  function filterTechnicians() {
    const hasSelectedTicket = Boolean(ticketSelect.value);
    const categoryId = ticketSelect.selectedOptions[0]?.dataset.categoryId || '';
    let eligibleCount = 0;
    [...technicianSelect.options].slice(1).forEach((option) => {
      let categoryIds = [];
      try { categoryIds = JSON.parse(option.dataset.categoryIds || '[]').map(String); } catch (_) { /* Hide invalid category data. */ }
      const eligible = hasSelectedTicket && Boolean(categoryId) && categoryIds.includes(String(categoryId));
      option.hidden = !eligible;
      option.disabled = !eligible;
      if (eligible) eligibleCount += 1;
    });
    technicianSelect.options[0].textContent = eligibleCount
      ? 'Chọn nhân viên phù hợp với danh mục'
      : 'Chưa có nhân viên phù hợp với danh mục này';
    if (technicianSelect.selectedOptions[0]?.disabled) technicianSelect.value = '';
    technicianSelect.disabled = !hasSelectedTicket || eligibleCount === 0 || assignmentLocked;
    const technicianTrigger = modal.querySelector('.modal-custom-select-technician .modal-select-trigger');
    if (technicianTrigger) technicianTrigger.disabled = technicianSelect.disabled;
    refreshTechnicianDropdown();
    updateAssignmentForm();
  }

  function categoryIcon(name) {
    const label = String(name).toLocaleLowerCase('vi-VN');
    if (label.includes('bàn') || label.includes('ghế')) return 'fa-chair';
    if (label.includes('wi-fi') || label.includes('mạng')) return 'fa-wifi';
    if (label.includes('lạnh')) return 'fa-snowflake';
    if (label.includes('chiếu') && !label.includes('sáng')) return 'fa-video';
    if (label.includes('điện') || label.includes('sáng') || label.includes('đèn')) return 'fa-bolt';
    if (label.includes('tính')) return 'fa-computer';
    return 'fa-tag';
  }

  function enhanceModalSelect(select, type) {
    if (!select) return () => {};
    const wrapper = document.createElement('div');
    wrapper.className = `modal-custom-select modal-custom-select-${type}`;
    select.parentNode.insertBefore(wrapper, select);
    wrapper.append(select);
    select.classList.add('modal-custom-select-native');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'modal-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    const triggerText = document.createElement('span');
    const chevron = document.createElement('i');
    chevron.className = 'fa-solid fa-chevron-down';
    chevron.setAttribute('aria-hidden', 'true');
    trigger.append(triggerText, chevron);

    const menu = document.createElement('div');
    menu.className = 'modal-select-menu';
    menu.hidden = true;
    menu.setAttribute('role', 'listbox');
    wrapper.append(trigger, menu);

    const close = () => {
      menu.hidden = true;
      wrapper.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
    };

    const render = () => {
      const selected = select.selectedOptions[0];
      triggerText.textContent = type === 'ticket' && selected?.dataset.ticketNumber
        ? [selected.dataset.ticketNumber, selected.dataset.roomName, selected.dataset.ticketTitle].filter(Boolean).join(' · ')
        : selected?.textContent.trim() || 'Chọn';
      trigger.title = triggerText.textContent;
      menu.replaceChildren();

      [...select.options].forEach((option) => {
        if (option.hidden || option.disabled) return;
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'modal-select-option';
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', String(option.value === select.value));

        const icon = document.createElement('i');
        icon.className = `fa-solid ${type === 'ticket' ? categoryIcon(option.dataset.categoryName || '') : 'fa-user-gear'}`;
        icon.setAttribute('aria-hidden', 'true');
        const color = /^#[0-9a-fA-F]{6}$/.test(option.dataset.categoryColor || '')
          ? option.dataset.categoryColor
          : '#1686df';
        icon.style.setProperty('--option-color', color);

        const text = document.createElement('span');
        text.className = 'modal-select-option-text';
        const title = document.createElement('strong');
        const subtitle = document.createElement('small');
        if (type === 'ticket' && option.dataset.ticketNumber) {
          title.textContent = `${option.dataset.ticketNumber} · ${option.dataset.ticketTitle || ''}`;
          subtitle.textContent = `${option.dataset.roomName || 'Chưa xác định phòng'} · ${option.dataset.categoryName || 'Chưa phân loại'}`;
        } else {
          title.textContent = option.textContent.trim();
          subtitle.textContent = type === 'ticket' ? 'Chọn phiếu sự cố' : 'Nhân viên kỹ thuật';
        }
        text.append(title, subtitle);
        const check = document.createElement('i');
        check.className = 'fa-solid fa-check modal-select-option-check';
        check.setAttribute('aria-hidden', 'true');
        item.append(icon, text, check);
        item.addEventListener('click', () => {
          select.value = option.value;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          close();
          trigger.focus();
        });
        menu.append(item);
      });
    };

    trigger.addEventListener('click', () => {
      const open = menu.hidden;
      menu.hidden = !open;
      wrapper.classList.toggle('is-open', open);
      trigger.setAttribute('aria-expanded', String(open));
    });
    trigger.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowDown') return;
      event.preventDefault();
      menu.hidden = false;
      wrapper.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      menu.querySelector('.modal-select-option')?.focus();
    });
    menu.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        close();
        trigger.focus();
      }
    });
    document.addEventListener('pointerdown', (event) => {
      if (!wrapper.contains(event.target)) close();
    });
    select.addEventListener('change', render);
    render();
    return render;
  }

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

  function currentDayKey() {
    const parts = new Intl.DateTimeFormat('en', {
      timeZone: calendar.dataset.timeZone || Intl.DateTimeFormat().resolvedOptions().timeZone,
      year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(new Date());
    const part = (type) => parts.find((item) => item.type === type).value;
    return `${part('year')}-${part('month')}-${part('day')}`;
  }

  function isPastDate(key) {
    return Boolean(key) && key < currentDayKey();
  }

  function currentDayDate() {
    const [year, month, day] = currentDayKey().split('-').map(Number);
    return new Date(year, month - 1, day);
  }

  let todayDate = currentDayDate();
  let activeDate = new Date(todayDate);
  let viewMode = 'month';

  function dateKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  function eventsForDate(date) {
    const key = dateKey(date);
    return allEvents.filter((item) => item.date === key && (!remoteScheduleIds || remoteScheduleIds.has(String(item.id)))).map((item) => {
      const event = document.createElement('div');
      event.className = 'cal-event category-event';
      event.style.setProperty('--category-color', item.category_color || '#64748B');
      Object.assign(event.dataset, {
        categoryColor: item.category_color || '#64748B', categoryId: item.category_id || '',
        ticketStatus: item.ticket_status || '', ticketId: item.ticket_id, scheduleId: item.id,
        ticketNumber: item.ticket_number || '', ticketTitle: item.title || '',
        scheduleStatus: item.schedule_status || '', staffId: item.staff_id || '',
        note: item.note || '',
        roomName: item.room_name || item.room || 'Chưa xác định phòng',
        staffName: item.staff_name || '', categoryName: item.category_name || 'Chưa phân loại',
      });
      const time = document.createElement('time');
      time.className = 'event-time'; time.textContent = item.time;
      const title = document.createElement('strong');
      title.className = 'event-title'; title.textContent = `${item.ticket_number} · ${item.room || ''}`;
      const meta = document.createElement('small');
      meta.className = 'event-meta'; meta.textContent = `${item.category_name || 'Chưa phân loại'} · ${item.staff_name || ''}`;
      event.append(time, title, meta);
      return event;
    });
  }

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

  function renderCalendar() {
    todayDate = currentDayDate();
    if (openAssignmentButton) openAssignmentButton.hidden = isPastDate(dateKey(activeDate));
    const year = activeDate.getFullYear();
    const month = activeDate.getMonth();
    const heading = document.querySelector('.calendar-toolbar .section-heading');
    const periodLabel = viewMode === 'month' ? 'tháng' : viewMode === 'week' ? 'tuần' : 'ngày';
    document.querySelector('[data-calendar-prev]')?.setAttribute('aria-label', `${periodLabel.charAt(0).toLocaleUpperCase('vi-VN')}${periodLabel.slice(1)} trước`);
    document.querySelector('[data-calendar-next]')?.setAttribute('aria-label', `${periodLabel.charAt(0).toLocaleUpperCase('vi-VN')}${periodLabel.slice(1)} sau`);
    let dates = [];
    if (viewMode === 'month') {
      if (heading) heading.textContent = `Th\u00e1ng ${month + 1} n\u0103m ${year}`;
      const firstDay = new Date(year, month, 1);
      const mondayOffset = (firstDay.getDay() + 6) % 7;
      const cellCount = Math.ceil((mondayOffset + new Date(year, month + 1, 0).getDate()) / 7) * 7;
      dates = Array.from({ length: cellCount }, (_, index) => new Date(year, month, index - mondayOffset + 1));
      calendar.className = 'month-grid month-days';
      monthHead.hidden = false;
      monthHead.innerHTML = '<span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span><span>CN</span>';
    } else if (viewMode === 'week') {
      const monday = new Date(activeDate);
      monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
      dates = Array.from({ length: 7 }, (_, index) => new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + index));
      if (heading) heading.textContent = `${dates[0].toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' })} – ${dates[6].toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })}`;
      calendar.className = 'month-grid month-days week-days';
      monthHead.hidden = false;
      monthHead.innerHTML = dates.map((date) => `<span>${['CN','T2','T3','T4','T5','T6','T7'][date.getDay()]}<small>${date.getDate()}/${date.getMonth() + 1}</small></span>`).join('');
    } else {
      dates = [new Date(activeDate)];
      if (heading) heading.textContent = labelDate(activeDate);
      calendar.className = 'month-grid month-days day-days';
      monthHead.hidden = true;
    }
    const fragment = document.createDocumentFragment();
    dates.forEach((date) => {
      const cell = document.createElement('div');
      cell.className = 'day-cell';
      cell.dataset.date = dateKey(date);
      if (viewMode === 'month' && date.getMonth() !== month) cell.classList.add('muted-day');
      if (dateKey(date) === dateKey(todayDate)) cell.classList.add('today-cell');

      const number = document.createElement('span');
      number.className = 'day-number';
      number.textContent = date.getDate();
      if (dateKey(date) === dateKey(todayDate)) number.classList.add('today-number');

      cell.append(number);
      if (!isPastDate(cell.dataset.date) && viewMode !== 'day') {
        const addButton = document.createElement('button');
        addButton.className = 'day-schedule-add';
        addButton.type = 'button';
        addButton.setAttribute('data-day-add', '');
        addButton.textContent = '+ X\u1ebfp l\u1ecbch';
        cell.append(addButton);
      }

      const dayEvents = eventsForDate(date);
      dayEvents.forEach((event) => cell.append(event));
      if (dayEvents.length > 0) {
        const expandButton = document.createElement('button');
        expandButton.className = 'day-more-events';
        expandButton.type = 'button';
        expandButton.dataset.dayExpand = '';
        cell.append(expandButton);
      }
      prepareCell(cell);
      fragment.append(cell);
    });
    calendar.replaceChildren(fragment);
    applyCalendarFilters();
  }

  function applyCalendarFilters() {
    const selectedStaff = staffFilter?.value || '';
    const selectedCategory = categoryFilter?.value || '';
    const selectedStatus = statusFilter?.value || '';
    calendar.querySelectorAll('.cal-event').forEach((event) => {
      const assignedStaff = event.dataset.staffName || '';
      const eventCategory = event.dataset.categoryId || '';
      const ticketStatus = event.dataset.ticketStatus || '';
      event.hidden = (Boolean(selectedStaff) && assignedStaff !== selectedStaff)
        || (Boolean(selectedCategory) && eventCategory !== selectedCategory)
        || (Boolean(selectedStatus) && ticketStatus !== selectedStatus);
    });

    calendar.querySelectorAll('.day-cell').forEach((cell) => {
      const visibleEvents = [...cell.querySelectorAll('.cal-event')].filter((event) => !event.hidden);
      const visibleLimit = viewMode === 'day' ? Number.POSITIVE_INFINITY : viewMode === 'week' ? 4 : 2;
      visibleEvents.forEach((event, index) => event.classList.toggle('is-overflowed', index >= visibleLimit));
      const expandButton = cell.querySelector('[data-day-expand]');
      if (expandButton) {
        const hiddenCount = Math.max(0, visibleEvents.length - visibleLimit);
        expandButton.hidden = visibleEvents.length <= visibleLimit;
        expandButton.textContent = hiddenCount > 0
          ? `+ ${hiddenCount} báo cáo`
          : `Xem ${visibleEvents.length} báo cáo`;
        expandButton.setAttribute('aria-label', `Xem tất cả ${visibleEvents.length} lịch trong ngày`);
      }
    });
  }

  async function fetchCalendarFilters() {
    const sequence = ++filterRequestSequence;
    const params = new URLSearchParams();
    if (staffFilter?.value) params.set('staff', staffFilter.value);
    if (categoryFilter?.value) params.set('category', categoryFilter.value);
    if (statusFilter?.value) params.set('status', statusFilter.value);
    try {
      const response = await fetch(`${appBase}/api/admin/schedules/filter?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Không thể lọc lịch phân công.');
      if (sequence !== filterRequestSequence) return;
      remoteScheduleIds = new Set((result.data?.ids || []).map(String));
      renderCalendar();
    } catch (error) {
      if (sequence === filterRequestSequence) console.error(error);
    }
  }

  function enhanceFilter(select, type, defaultLabel, ariaLabel) {
    if (!select) return;
    const wrapper = select.closest(`[data-${type}-filter]`);
    if (!wrapper) return;

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'staff-filter-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', 'staff-filter-options');

    const value = document.createElement('span');
    value.className = 'staff-filter-value';
    const chevron = document.createElement('i');
    chevron.className = 'fa-solid fa-chevron-down';
    chevron.setAttribute('aria-hidden', 'true');
    trigger.append(value, chevron);

    const menu = document.createElement('div');
    menu.className = 'staff-filter-menu';
    menu.hidden = true;
    menu.id = `${type}-filter-options`;
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('aria-label', ariaLabel);

    function categoryIcon(name) {
      const label = name.toLocaleLowerCase('vi-VN');
      if (label.includes('bàn') || label.includes('ghế')) return 'fa-chair';
      if (label.includes('wi-fi') || label.includes('mạng')) return 'fa-wifi';
      if (label.includes('lạnh')) return 'fa-snowflake';
      if (label.includes('chiếu') && !label.includes('sáng')) return 'fa-video';
      if (label.includes('điện') || label.includes('sáng') || label.includes('đèn')) return 'fa-bolt';
      if (label.includes('tính')) return 'fa-computer';
      return 'fa-tag';
    }

    [...select.options].forEach((option) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = `staff-filter-option${type === 'category' ? ' has-category-icon' : ''}`;
      item.dataset.value = option.value;
      item.setAttribute('role', 'option');
      item.setAttribute('aria-selected', 'false');
      const name = document.createElement('span');
      name.textContent = option.textContent.trim();
      if (type === 'category') {
        const icon = document.createElement('i');
        icon.className = `staff-filter-option-icon fa-solid ${option.value ? categoryIcon(option.textContent) : 'fa-layer-group'}`;
        icon.setAttribute('aria-hidden', 'true');
        const categoryColor = /^#[0-9a-fA-F]{6}$/.test(option.dataset.color || '') ? option.dataset.color : '#64748b';
        icon.style.setProperty('--filter-category-color', categoryColor);
        item.append(icon);
      }
      const check = document.createElement('i');
      check.className = 'fa-solid fa-check';
      check.setAttribute('aria-hidden', 'true');
      item.append(name, check);
      item.addEventListener('click', () => {
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        trigger.focus();
      });
      menu.append(item);
    });

    function syncSelection() {
      const selected = select.selectedOptions[0];
      value.textContent = selected?.textContent.trim() || defaultLabel;
      menu.querySelectorAll('.staff-filter-option').forEach((item) => {
        const isSelected = item.dataset.value === select.value;
        item.classList.toggle('is-selected', isSelected);
        item.setAttribute('aria-selected', String(isSelected));
      });
    }

    select.classList.add('staff-filter-native');
    wrapper.append(trigger, menu);
    syncSelection();
    trigger.addEventListener('click', () => {
      menu.hidden = !menu.hidden;
      trigger.setAttribute('aria-expanded', String(!menu.hidden));
    });
    trigger.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        menu.querySelector('.staff-filter-option')?.focus();
      }
    });
    menu.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        trigger.focus();
      }
    });
    document.addEventListener('pointerdown', (event) => {
      if (!wrapper.contains(event.target)) {
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
      }
    });
    select.addEventListener('change', () => {
      syncSelection();
      applyCalendarFilters();
      fetchCalendarFilters();
    });
  }

  enhanceFilter(staffFilter, 'staff', 'Tất cả nhân viên', 'Chọn nhân viên');
  enhanceFilter(categoryFilter, 'category', 'Tất cả danh mục lỗi', 'Chọn danh mục lỗi');
  enhanceFilter(statusFilter, 'status', 'Tất cả trạng thái', 'Chọn trạng thái phiếu');
  refreshTicketDropdown = enhanceModalSelect(ticketSelect, 'ticket');
  refreshTechnicianDropdown = enhanceModalSelect(technicianSelect, 'technician');
  filterTechnicians();

  function resetModalView() {
    clearAssignmentFeedback();
    assignmentPanel.hidden = true;
    detailsActions.hidden = false;
    assignmentActions.hidden = true;
    eventList.hidden = false;
    emptyState.hidden = dayHasEvents;
    if (addAssignmentButton) addAssignmentButton.hidden = isPastDate(selectedDate);
    if (isPastDate(selectedDate) && !dayHasEvents) emptyState.textContent = 'Ngày này không có lịch xử lý.';
    updateSaveButton();
  }

  function openAssignment(eventInfo = {}) {
    if (!selectedDate || isPastDate(selectedDate)) {
      resetModalView();
      return;
    }
    clearAssignmentFeedback();
    [...ticketSelect.options]
      .filter((option) => option.dataset.existingScheduleOption === 'true' && option.value !== String(eventInfo.ticketId || ''))
      .forEach((option) => option.remove());
    selectedScheduleId = eventInfo.scheduleId || '';
    selectedScheduleStatus = eventInfo.scheduleStatus || '';
    cancelScheduleButton.hidden = !selectedScheduleId;
    const scheduleLocked = selectedScheduleStatus === 'acknowledged';
    assignmentLocked = scheduleLocked;
    ticketSelect.disabled = scheduleLocked;
    technicianSelect.disabled = scheduleLocked;
    assignmentTime.disabled = scheduleLocked;
    modal.querySelector('#modal-assignment-note').disabled = scheduleLocked;
    modal.querySelectorAll('.modal-select-trigger').forEach((trigger) => { trigger.disabled = scheduleLocked; });
    eventList.hidden = false;
    eventList.querySelectorAll('.modal-event.is-selected').forEach((row) => row.classList.remove('is-selected'));
    eventInfo.sourceRow?.classList.add('is-selected');
    emptyState.hidden = dayHasEvents;
    assignmentPanel.hidden = false;
    detailsActions.hidden = true;
    assignmentActions.hidden = false;
    const eventTime = typeof eventInfo === 'string'
      ? eventInfo.split('\u00b7')[0]?.trim()
      : eventInfo.time;
    const eventTitle = typeof eventInfo === 'string' ? eventInfo : (eventInfo.title || '');
    assignmentTime.value = eventTime || '';
    assignmentNote.value = eventInfo.note || '';

    if (eventInfo.ticketId && ![...ticketSelect.options].some((option) => option.value === String(eventInfo.ticketId))) {
      const currentTicket = document.createElement('option');
      currentTicket.value = String(eventInfo.ticketId);
      currentTicket.textContent = `${eventInfo.ticketNumber || ''} · ${eventInfo.ticketTitle || ''}`.trim();
      currentTicket.dataset.ticketNumber = eventInfo.ticketNumber || '';
      currentTicket.dataset.ticketTitle = eventInfo.ticketTitle || '';
      currentTicket.dataset.roomName = eventInfo.roomName || 'Chưa xác định phòng';
      currentTicket.dataset.categoryId = eventInfo.categoryId || '';
      currentTicket.dataset.categoryName = eventInfo.categoryName || '';
      currentTicket.dataset.categoryColor = eventInfo.categoryColor || '';
      currentTicket.dataset.existingScheduleOption = 'true';
      ticketSelect.add(currentTicket, 1);
    }
    ticketSelect.value = eventInfo.ticketId || '';
    refreshTicketDropdown();
    filterTechnicians();
    const matchingStaff = [...technicianSelect.options].find((option) =>
      option.value === String(eventInfo.staffId || '') && !option.disabled
    );
    technicianSelect.value = matchingStaff?.value || '';
    refreshTechnicianDropdown();
    updateSaveButton();
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
      : (isPastDate(selectedDate)
        ? 'Ngày này không có lịch xử lý.'
        : 'Ng\u00e0y n\u00e0y ch\u01b0a c\u00f3 l\u1ecbch x\u1eed l\u00fd. B\u1ea1n c\u00f3 th\u1ec3 x\u1ebfp l\u1ecbch m\u1edbi.');
    events.forEach((event) => {
      const rawParts = event.textContent.split('\u00b7').map((part) => part.trim());
      const time = event.querySelector('.event-time')?.textContent.trim() || rawParts[0] || '';
      const eventTitle = event.querySelector('.event-title')?.textContent.trim() || rawParts.slice(1).join(' \u00b7 ');
      const technician = event.dataset.staffName || event.querySelector('.event-meta')?.textContent.trim() || '';
      const row = document.createElement('article');
      row.setAttribute('role', 'group');
      const categoryColor = event.dataset.categoryColor || getComputedStyle(event).getPropertyValue('--category-color').trim();
      row.className = categoryColor ? 'modal-event category-modal-event' : `modal-event ${[...event.classList].find((name) => name.startsWith('event-')) || 'event-blue'}`;
      if (categoryColor) row.style.setProperty('--category-color', categoryColor);
      row.setAttribute('aria-label', `${isPastDate(selectedDate) ? 'Xem chi tiết phiếu' : 'Xem hoặc sửa lịch'} ${time} · ${eventTitle}`);
      const timeLabel = document.createElement('time');
      timeLabel.textContent = time;
      const details = document.createElement('div');
      details.className = 'modal-event-details';
      const titleLabel = document.createElement('strong');
      titleLabel.textContent = eventTitle || 'L\u1ecbch x\u1eed l\u00fd s\u1ef1 c\u1ed1';
      const meta = document.createElement('small');
      const category = event.dataset.categoryName || '';
      const acknowledged = event.dataset.scheduleStatus === 'acknowledged';
      const scheduleStatus = acknowledged
        ? 'Nhân viên đã xác nhận · đang trên đường'
        : 'Chờ nhân viên xác nhận';
      meta.textContent = [category, technician ? `K\u1ef9 thu\u1eadt vi\u00ean: ${technician}` : ''].filter(Boolean).join(' · ');
      const statusBadge = document.createElement('span');
      statusBadge.className = `modal-schedule-status ${acknowledged ? 'is-acknowledged' : 'is-pending'}`;
      const statusIcon = document.createElement('span');
      statusIcon.className = 'modal-schedule-status-icon';
      statusIcon.setAttribute('aria-hidden', 'true');
      statusIcon.textContent = acknowledged ? '✓' : '◷';
      const statusText = document.createElement('span');
      statusText.textContent = scheduleStatus;
      statusBadge.append(statusIcon, statusText);
      const ticketId = event.dataset.ticketId || '';
      const detailLink = document.createElement('a');
      detailLink.className = 'button button-secondary modal-edit';
      detailLink.textContent = 'Xem chi ti\u1ebft';
      detailLink.href = ticketId
        ? `${appBase}/admin/pages/ticket-detail?ticket=${encodeURIComponent(ticketId)}`
        : `${appBase}/admin/pages/incidents`;
      detailLink.addEventListener('click', (clickEvent) => clickEvent.stopPropagation());
      row.addEventListener('click', () => {
        if (isPastDate(selectedDate)) {
          window.location.assign(detailLink.href);
          return;
        }
        openAssignment({
        time,
        title: eventTitle,
        ticketId,
        ticketNumber: event.dataset.ticketNumber || '',
        ticketTitle: event.dataset.ticketTitle || '',
        roomName: event.dataset.roomName || 'Chưa xác định phòng',
        categoryId: event.dataset.categoryId || '',
        categoryName: event.dataset.categoryName || '',
        categoryColor: event.dataset.categoryColor || '',
        staffId: event.dataset.staffId || '',
        scheduleId: event.dataset.scheduleId || '',
        scheduleStatus: event.dataset.scheduleStatus || 'scheduled',
        note: event.dataset.note || '',
        sourceRow: row,
        });
      });
      details.append(titleLabel, meta, statusBadge);
      row.append(timeLabel, details, detailLink);
      eventList.append(row);
    });
    openAssignment();
    modal.showModal();
  }

  renderCalendar();
  window.setInterval(() => {
    if (dateKey(todayDate) === currentDayKey()) return;
    renderCalendar();
    if (modal.open && isPastDate(selectedDate)) resetModalView();
  }, 60000);

  calendar.addEventListener('click', (event) => {
    const cell = event.target.closest('.day-cell');
    if (!cell) return;
    if (event.target.closest('[data-day-add]')) {
      event.preventDefault();
      openDay(cell);
      return;
    }
    if (event.target.closest('[data-day-expand]')) {
      event.preventDefault();
      openDay(cell);
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

  document.querySelector('[data-calendar-prev]')?.addEventListener('click', () => {
    if (viewMode === 'month') activeDate = new Date(activeDate.getFullYear(), activeDate.getMonth() - 1, 1);
    else activeDate.setDate(activeDate.getDate() - (viewMode === 'week' ? 7 : 1));
    renderCalendar();
  });
  document.querySelector('[data-calendar-next]')?.addEventListener('click', () => {
    if (viewMode === 'month') activeDate = new Date(activeDate.getFullYear(), activeDate.getMonth() + 1, 1);
    else activeDate.setDate(activeDate.getDate() + (viewMode === 'week' ? 7 : 1));
    renderCalendar();
  });

  document.querySelector('.today-button')?.addEventListener('click', () => {
    activeDate = currentDayDate();
    renderCalendar();
    calendar.querySelector('.today-number')?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
  });
  viewInputs.forEach((input) => input.addEventListener('change', () => {
    if (!input.checked) return;
    viewMode = input.id === 'view-week' ? 'week' : input.id === 'view-day' ? 'day' : 'month';
    renderCalendar();
  }));

  modal.querySelector('[data-modal-add]')?.addEventListener('click', () => openAssignment());
  modal.querySelector('[data-modal-cancel]')?.addEventListener('click', resetModalView);
  cancelScheduleButton?.addEventListener('click', async () => {
    if (!selectedScheduleId || isPastDate(selectedDate)) return;
    const warning = selectedScheduleStatus === 'acknowledged'
      ? 'Nhân viên đã xác nhận và đang trên đường. Hủy lịch này? Nhân viên và người báo sự cố sẽ được thông báo.'
      : 'Hủy lịch xử lý này? Nhân viên và người báo sự cố sẽ được thông báo.';
    if (!window.confirm(warning)) return;

    cancelScheduleButton.disabled = true;
    try {
      const response = await fetch(`${appBase}/api/admin/schedules/${encodeURIComponent(selectedScheduleId)}/cancel`, {
        method: 'POST', credentials: 'same-origin',
        headers: {
          Accept: 'application/json', 'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({}),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat().join(' '));
      window.location.reload();
    } catch (error) {
      showAssignmentFeedback(error.message || 'Không thể hủy lịch.');
    } finally {
      cancelScheduleButton.disabled = false;
    }
  });
  ticketSelect.addEventListener('change', () => {
    technicianSelect.value = '';
    filterTechnicians();
  });
  [technicianSelect, assignmentTime, assignmentNote].forEach((field) => {
    field.addEventListener('input', updateAssignmentForm);
    field.addEventListener('change', updateAssignmentForm);
  });
  assignmentTime.addEventListener('click', () => {
    if (assignmentTime.disabled || assignmentTime.readOnly) return;
    try {
      assignmentTime.showPicker();
    } catch (_) {
      assignmentTime.focus();
    }
  });
  modal.querySelector('[data-modal-save]')?.addEventListener('click', async () => {
    let saved = false;
    if (assignmentSaving) return;
    if (assignmentLocked || !selectedDate || isPastDate(selectedDate)) {
      resetModalView();
      return;
    }
    assignmentValidationShown = true;
    const errors = renderAssignmentErrors();
    if (errors.length) {
      const field = errors[0].field;
      (field.closest('.modal-custom-select')?.querySelector('.modal-select-trigger') || field).focus();
      assignmentFeedback.scrollIntoView({ block: 'nearest' });
      return;
    }
    assignmentSaving = true;
    updateSaveButton();
    try {
      const response = await fetch(`${appBase}/api/admin/tickets/${encodeURIComponent(ticketSelect.value)}/assign`, {
        method: 'POST', credentials: 'same-origin',
        headers: {
          Accept: 'application/json', 'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({
          staff_id: technicianSelect.value,
          schedule_id: selectedScheduleId || null,
          scheduled_at: `${selectedDate}T${assignmentTime.value}`,
          note: assignmentNote.value.trim(),
        }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat().join(' '));
      saved = true;
      if (window.HelpdeskFeedback) window.HelpdeskFeedback.reload('Đã lưu phân công thành công.');
      else window.location.reload();
    } catch (error) {
      showAssignmentFeedback(error.message || 'Không thể lưu lịch phân công.');
    } finally {
      if (!saved) {
        assignmentSaving = false;
        updateSaveButton();
      }
    }
  });
  openAssignmentButton?.addEventListener('click', () => {
    if (isPastDate(dateKey(activeDate))) return;
    const dateCell = [...calendar.querySelectorAll('.day-cell')].find((cell) => cell.dataset.date === dateKey(activeDate));
    if (dateCell) openDay(dateCell);
    else {
      selectedDate = dateKey(activeDate);
      dateHeading.textContent = labelDate(activeDate);
      dayHasEvents = eventsForDate(activeDate).length > 0;
      resetModalView();
      eventList.replaceChildren();
      modal.showModal();
    }
    openAssignment();
  });
  modal.addEventListener('click', (event) => {
    if (event.target === modal) modal.close();
  });
})();
