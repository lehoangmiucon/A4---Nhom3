(() => {
  const skip = (select) => select.multiple
    || select.size > 1
    || select.classList.contains('period-select-native')
    || select.classList.contains('modal-custom-select-native')
    || select.classList.contains('staff-filter-native')
    || select.classList.contains('helpdesk-select-native')
    || select.closest('.modal-custom-select, .staff-filter');

  const labelFor = (select) => {
    const explicit = select.getAttribute('aria-label') || select.labels?.[0]?.textContent || '';
    return explicit.replace(/\s+/g, ' ').trim() || 'lựa chọn';
  };

  function enhance(select) {
    if (skip(select) || !select.options.length || select.hidden) return;

    const wrapper = document.createElement('span');
    wrapper.className = 'helpdesk-select-control';
    wrapper.dataset.selectFor = select.id || select.name || '';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.append(select);

    const originallyRequired = select.required;
    if (originallyRequired) {
      select.dataset.helpdeskRequired = 'true';
      select.required = false;
    }
    select.classList.add('helpdesk-select-native');
    select.setAttribute('aria-hidden', 'true');
    select.tabIndex = -1;

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'helpdesk-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-label', labelFor(select));
    trigger.disabled = select.disabled;
    const value = document.createElement('span');
    const arrow = document.createElement('i');
    arrow.className = 'fa-solid fa-chevron-down';
    arrow.setAttribute('aria-hidden', 'true');
    trigger.append(value, arrow);

    const menu = document.createElement('span');
    menu.className = 'helpdesk-select-menu';
    menu.hidden = true;
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('aria-label', labelFor(select));

    const error = document.createElement('small');
    error.className = 'helpdesk-select-error';
    error.hidden = true;
    error.setAttribute('role', 'alert');
    const errorIcon = document.createElement('i');
    errorIcon.className = 'fa-solid fa-circle-exclamation';
    errorIcon.setAttribute('aria-hidden', 'true');
    const errorText = document.createElement('span');
    error.append(errorIcon, errorText);

    wrapper.append(trigger, menu, error);

    let optionButtons = [];
    function close() {
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      wrapper.classList.remove('is-open');
    }
    function sync() {
      const selected = select.selectedOptions[0];
      value.textContent = selected?.textContent.trim() || 'Chọn…';
      trigger.disabled = select.disabled;
      trigger.classList.toggle('is-placeholder', !select.value);
      optionButtons.forEach((button, index) => {
        const option = select.options[index];
        const active = option === selected;
        button.classList.toggle('is-selected', active);
        button.setAttribute('aria-selected', String(active));
      });
    }
    function rebuild() {
      menu.replaceChildren();
      optionButtons = [...select.options].map((option) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'helpdesk-select-option';
        item.setAttribute('role', 'option');
        item.disabled = option.disabled;
        const text = document.createElement('span');
        text.textContent = option.textContent.trim();
        const check = document.createElement('i');
        check.className = 'fa-solid fa-check';
        check.setAttribute('aria-hidden', 'true');
        item.append(text, check);
        item.addEventListener('click', () => {
          select.value = option.value;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          error.hidden = true;
          trigger.removeAttribute('aria-invalid');
          close();
          trigger.focus();
        });
        menu.append(item);
        return item;
      });
      sync();
    }

    trigger.addEventListener('click', () => {
      if (trigger.disabled) return;
      const opening = menu.hidden;
      menu.hidden = !opening;
      trigger.setAttribute('aria-expanded', String(opening));
      wrapper.classList.toggle('is-open', opening);
      if (opening) (menu.querySelector('.is-selected:not(:disabled)') || menu.querySelector('.helpdesk-select-option:not(:disabled)'))?.focus();
    });
    trigger.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
      event.preventDefault();
      menu.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      wrapper.classList.add('is-open');
      (menu.querySelector('.is-selected:not(:disabled)') || menu.querySelector('.helpdesk-select-option:not(:disabled)'))?.focus();
    });
    menu.addEventListener('keydown', (event) => {
      const currentIndex = optionButtons.indexOf(document.activeElement);
      if (event.key === 'Escape') {
        event.preventDefault();
        close();
        trigger.focus();
      } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const direction = event.key === 'ArrowDown' ? 1 : -1;
        let nextIndex = currentIndex;
        do { nextIndex = (nextIndex + direction + optionButtons.length) % optionButtons.length; }
        while (optionButtons[nextIndex]?.disabled && nextIndex !== currentIndex);
        optionButtons[nextIndex]?.focus();
      } else if (event.key === 'Home' || event.key === 'End') {
        event.preventDefault();
        const choices = optionButtons.filter((button) => !button.disabled);
        (event.key === 'Home' ? choices[0] : choices.at(-1))?.focus();
      }
    });
    select.addEventListener('change', () => {
      sync();
      if (select.value) {
        error.hidden = true;
        trigger.removeAttribute('aria-invalid');
      }
    });
    select.addEventListener('input', sync);
    select.form?.addEventListener('reset', () => window.setTimeout(sync, 0));
    new MutationObserver(rebuild).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'label', 'value'] });
    rebuild();
  }

  document.querySelectorAll('.main-area select').forEach(enhance);

  document.addEventListener('pointerdown', (event) => {
    document.querySelectorAll('.helpdesk-select-control.is-open').forEach((wrapper) => {
      if (wrapper.contains(event.target)) return;
      wrapper.querySelector('.helpdesk-select-menu').hidden = true;
      wrapper.querySelector('.helpdesk-select-trigger').setAttribute('aria-expanded', 'false');
      wrapper.classList.remove('is-open');
    });
  });

  document.addEventListener('submit', (event) => {
    const select = [...event.target.querySelectorAll('select[data-helpdesk-required="true"]')]
      .find((candidate) => !candidate.disabled && !candidate.value);
    if (!select) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const wrapper = select.closest('.helpdesk-select-control');
    const trigger = wrapper?.querySelector('.helpdesk-select-trigger');
    const error = wrapper?.querySelector('.helpdesk-select-error');
    const text = error?.querySelector('span');
    if (text) text.textContent = `Vui lòng chọn ${labelFor(select).toLocaleLowerCase('vi-VN')}.`;
    if (error) error.hidden = false;
    trigger?.setAttribute('aria-invalid', 'true');
    trigger?.focus();
  }, true);
})();
