(() => {
  document.querySelectorAll('.period-select').forEach((select) => {
    const wrapper = select.closest('.period-filter');
    if (!wrapper || !select.options.length) return;

    const label = document.querySelector(`label[for="${select.id}"]`)?.textContent.trim() || 'Chọn khoảng thời gian';
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'period-select-trigger';
    trigger.setAttribute('aria-label', label);
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const value = document.createElement('span');
    const chevron = document.createElement('i');
    chevron.className = 'fa-solid fa-chevron-down';
    chevron.setAttribute('aria-hidden', 'true');
    trigger.append(value, chevron);

    const menu = document.createElement('div');
    menu.className = 'period-select-menu';
    menu.hidden = true;
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('aria-label', label);

    const items = [...select.options].map((option) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'period-select-option';
      item.setAttribute('role', 'option');
      item.dataset.value = option.value;
      const text = document.createElement('span');
      text.textContent = option.textContent.trim();
      const check = document.createElement('i');
      check.className = 'fa-solid fa-check';
      check.setAttribute('aria-hidden', 'true');
      item.append(text, check);
      item.addEventListener('click', () => {
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        sync();
        close();
        trigger.focus();
      });
      menu.append(item);
      return item;
    });

    function sync() {
      const selected = select.selectedOptions[0];
      value.textContent = selected?.textContent.trim() || 'Chọn thời gian';
      items.forEach((item) => {
        const active = item.dataset.value === select.value;
        item.classList.toggle('is-selected', active);
        item.setAttribute('aria-selected', String(active));
      });
    }

    function close() {
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    }

    select.classList.add('period-select-native');
    wrapper.append(trigger, menu);
    sync();

    trigger.addEventListener('click', () => {
      const open = menu.hidden;
      menu.hidden = !open;
      trigger.setAttribute('aria-expanded', String(open));
      if (open) menu.querySelector('.is-selected')?.focus();
    });
    trigger.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        menu.querySelector('.is-selected')?.focus();
      }
    });
    menu.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        close();
        trigger.focus();
      }
    });
    select.addEventListener('change', sync);
    document.addEventListener('pointerdown', (event) => {
      if (!wrapper.contains(event.target)) close();
    });
  });
})();
