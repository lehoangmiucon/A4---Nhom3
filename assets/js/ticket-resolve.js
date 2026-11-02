'use strict';
window.initTicketResolve = async (options = {}) => {
  const root = options.root || document;
  const form = root.querySelector('#resolve-form');
  if (!form) return;
  const $ = (selector) => root.querySelector(selector);
  const demo = window.TechnicianDemo;
  let tasks;
  try { tasks = await demo.all(); }
  catch { tasks = demo.samples(); }
  const task = tasks.find((item) => item.id === (options.id || demo.selectedId()));
  if (!task || task.status !== 'in_progress') {
    const main = root.querySelector('main');
    main.replaceChildren();
    const title = document.createElement('h1');
    title.className = 'text-2xl font-bold mb-5';
    title.textContent = !task ? 'Không tìm thấy nhiệm vụ' : task.status === 'resolved' ? 'Nhiệm vụ đã có báo cáo nghiệm thu' : 'Hãy bắt đầu xử lý nhiệm vụ trước khi lập báo cáo';
    const back = document.createElement('a');
    back.href = task ? (task.report ? demo.reportUrl(task.id) : demo.detailUrl(task.id)) : demo.scheduleUrl();
    back.className = 'primary-button';
    back.textContent = task ? 'Về chi tiết nhiệm vụ' : 'Về lịch của tôi';
    main.append(title, back);
    return;
  }
  $('#back-to-task').href = demo.detailUrl(task.id);
  for (const [selector, value] of [['#resolve-code', task.id], ['#resolve-title', task.title], ['#resolve-location', task.location], ['#resolve-device', task.device], ['#resolve-reporter', task.reporter]]) {
    $(selector).textContent = value;
  }
  form.elements.ticket_id.value = task.id;
  form.elements.category_id.value = task.category;
  const materials = $('#materials');
  const files = { before: [], after: [] };
  let nextMaterial = 0;
  let busy = false;
  let reportSnapshot = null;
  let dirty = false;
  form.addEventListener('input', () => { dirty = true; });
  const note = form.elements.resolution_note;
  note.addEventListener('input', () => {
    $('#note-count').textContent = `${Array.from(note.value).length} / 500`;
    note.setCustomValidity('');
  });

  $('#add-material').addEventListener('click', () => {
    dirty = true;
    const index = nextMaterial++;
    const row = document.createElement('div');
    row.className = 'material-row';
    row.innerHTML = `<label class="field">Tên vật tư<input name="materials_used[${index}][item_name]" placeholder="Ví dụ: Cầu chì" maxlength="150" required></label>
      <label class="field">Số lượng<input name="materials_used[${index}][quantity]" type="number" value="1" min="1" step="any" required></label>
      <label class="field">Đơn vị<select name="materials_used[${index}][unit]" required><option>cái</option><option>mét</option><option>sợi</option><option>bóng</option></select></label>
      <button class="remove-material" type="button" aria-label="Xóa dòng vật tư">×</button>`;
    row.querySelector('button').addEventListener('click', () => {
      row.remove();
      $('#materials-empty').hidden = materials.children.length > 0;
      $('#add-material').focus();
    });
    materials.append(row);
    $('#materials-empty').hidden = true;
    row.querySelector('input').focus();
  });

  function addImages(kind, input) {
    const candidates = Array.from(input.files || []);
    const errors = [];
    for (const file of candidates) {
      if (!['image/jpeg', 'image/png'].includes(file.type)) {
        errors.push(`${file.name}: chỉ nhận ảnh JPEG hoặc PNG.`);
        continue;
      }
      if (file.size > 5 * 1024 * 1024 || file.size === 0) {
        errors.push(`${file.name}: ảnh phải có dữ liệu và không vượt quá 5 MB.`);
        continue;
      }
      if (kind === 'before' && files.before.length >= 3) {
        errors.push('Chỉ được thêm tối đa 3 ảnh trước xử lý.');
        break;
      }
      const entry = { file, url: URL.createObjectURL(file) };
      dirty = true;
      files[kind].push(entry);
      const card = document.createElement('div');
      card.className = 'photo';
      const image = document.createElement('img');
      image.src = entry.url;
      image.alt = file.name;
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.textContent = '×';
      remove.setAttribute('aria-label', `Xóa ảnh ${file.name}`);
      remove.addEventListener('click', () => {
        files[kind].splice(files[kind].indexOf(entry), 1);
        URL.revokeObjectURL(entry.url);
        card.remove();
        $(`#${kind}-images`).focus();
      });
      card.append(image, remove);
      $(`#${kind}-preview`).append(card);
    }
    $('#upload-error').textContent = errors.join(' ');
    input.value = '';
  }
  for (const kind of ['before', 'after']) {
    $(`#${kind}-images`).addEventListener('change', (event) => addImages(kind, event.target));
  }
  $('#camera').addEventListener('change', (event) => addImages('after', event.target));

  // Fixed backing dimensions preserve the signature when the viewport rotates.
  const canvas = $('#signature');
  const context = canvas.getContext('2d');
  let activePointer = null;
  let signed = false;
  let previous = null;
  context.strokeStyle = '#16324f';
  context.fillStyle = '#16324f';
  context.lineWidth = 4;
  context.lineCap = 'round';
  context.lineJoin = 'round';
  const point = (event) => {
    const box = canvas.getBoundingClientRect();
    return { x: (event.clientX - box.left) * canvas.width / box.width, y: (event.clientY - box.top) * canvas.height / box.height };
  };
  canvas.addEventListener('pointerdown', (event) => {
    if (activePointer !== null || (event.pointerType === 'mouse' && event.button !== 0)) return;
    event.preventDefault();
    activePointer = event.pointerId;
    previous = point(event);
    canvas.setPointerCapture(activePointer);
  });
  canvas.addEventListener('pointermove', (event) => {
    if (activePointer !== event.pointerId) return;
    const current = point(event);
    if (Math.hypot(current.x - previous.x, current.y - previous.y) < 1) return;
    context.beginPath();
    context.moveTo(previous.x, previous.y);
    context.lineTo(current.x, current.y);
    context.stroke();
    previous = current;
    signed = true;
    dirty = true;
    $('#signature-hint').hidden = true;
  });
  const stopDrawing = (event) => {
    if (event.pointerId === activePointer) activePointer = null;
  };
  ['pointerup', 'pointercancel', 'lostpointercapture'].forEach((name) => canvas.addEventListener(name, stopDrawing));
  $('#clear-signature').addEventListener('click', () => {
    context.clearRect(0, 0, canvas.width, canvas.height);
    signed = false;
    activePointer = null;
    $('#signature-hint').hidden = false;
  });

  const dialog = $('#preview-dialog');
  $('#close-preview').addEventListener('click', () => dialog.close());
  const readImage = (entry) => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve({ name: entry.file.name, data: reader.result });
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(entry.file);
  });
  let confirming = false;
  dialog.addEventListener('cancel', (event) => { if (confirming) event.preventDefault(); });
  $('#confirm-demo').addEventListener('click', async () => {
    if (confirming || !reportSnapshot) return;
    confirming = true;
    const button = $('#confirm-demo');
    button.disabled = true;
    button.textContent = 'Đang lưu bản thử…';
    $('#close-preview').disabled = true;
    $('#confirm-error').textContent = '';
    try {
      const [before, after] = await Promise.all([Promise.all(files.before.map(readImage)), Promise.all(files.after.map(readImage))]);
      await demo.update(task.id, { status: 'resolved', report: { ...reportSnapshot, before, after, submittedAt: new Date().toISOString() } });
      dirty = false;
      confirming = false;
      dialog.close();
      if (options.onComplete) options.onComplete(task.id);
      else location.href = `technician.html?completed=${encodeURIComponent(task.id)}`;
    } catch {
      $('#confirm-error').textContent = 'Chưa lưu được bản thử. Bộ nhớ trình duyệt có thể đã đầy hoặc bị chặn. Dữ liệu trên form vẫn còn để bạn thử lại.';
      confirming = false;
      button.disabled = false;
      button.textContent = 'Xác nhận & hoàn tất bản thử';
      $('#close-preview').disabled = false;
    }
  });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (busy) return;
    const error = $('#form-error');
    error.textContent = '';
    note.setCustomValidity(note.value.trim().length < 10 ? 'Vui lòng mô tả phương án xử lý ít nhất 10 ký tự.' : '');
    for (const input of form.querySelectorAll('input[type="text"], input:not([type])')) {
      input.setCustomValidity(input.required && !input.value.trim() ? 'Vui lòng nhập nội dung.' : '');
      input.oninput = () => input.setCustomValidity('');
    }
    if (!form.reportValidity()) return;
    if (!files.after.length || !signed) {
      error.textContent = !files.after.length ? 'Vui lòng thêm ít nhất 1 ảnh sau xử lý.' : 'Vui lòng ký xác nhận trước khi hoàn tất.';
      error.focus();
      return;
    }
    busy = true;
    const button = $('#submit-resolution');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.textContent = 'Đang chuẩn bị bản xem trước…';
    try {
      // Preview first; only explicit demo confirmation writes to local browser storage.
      await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
      const summary = $('#summary');
      summary.replaceChildren();
      const rows = [
        ['Phiếu sự cố', form.elements.ticket_id.value],
        ['Nhóm sự cố', form.elements.category_id.selectedOptions[0].textContent],
        ['Nguyên nhân', form.elements.root_cause.value],
        ['Phương án xử lý', note.value.trim()],
        ['Vật tư', Array.from(materials.children).map((row) => {
          const fields = row.querySelectorAll('input, select');
          return `${fields[0].value.trim()} · ${fields[1].value} ${fields[2].value}`;
        }).join('\n') || 'Không sử dụng'],
        ['Ảnh minh chứng', `${files.before.length} ảnh trước · ${files.after.length} ảnh sau`],
        ['Người xác nhận', `${form.elements.verifier_name.value.trim()} · ${form.elements.verifier_role.value}`]
      ];
      for (const [label, value] of rows) {
        const group = document.createElement('div');
        const term = document.createElement('dt');
        const description = document.createElement('dd');
        term.textContent = label;
        description.textContent = value;
        group.append(term, description);
        summary.append(group);
      }
      const signatureImage = document.createElement('img');
      signatureImage.src = canvas.toDataURL('image/png');
      signatureImage.alt = 'Chữ ký người xác nhận';
      signatureImage.className = 'w-full rounded-lg border border-slate-200';
      summary.append(signatureImage);
      reportSnapshot = {
        category: form.elements.category_id.selectedOptions[0].textContent,
        rootCause: form.elements.root_cause.value,
        note: note.value.trim(),
        materials: Array.from(materials.children).map((row) => {
          const fields = row.querySelectorAll('input, select');
          return { name: fields[0].value.trim(), quantity: fields[1].value, unit: fields[2].value };
        }),
        verifier: form.elements.verifier_name.value.trim(),
        role: form.elements.verifier_role.value,
        signature: signatureImage.src,
        sample: false
      };
      $('#confirm-error').textContent = '';
      dialog.showModal();
    } catch {
      error.textContent = 'Chưa mở được bản xem trước. Vui lòng thử lại.';
      error.focus();
    } finally {
      busy = false;
      button.disabled = false;
      button.removeAttribute('aria-busy');
      button.textContent = 'Hoàn tất ticket →';
    }
  });
  form.inert = false;
  if (options.lifecycle) {
    options.lifecycle.isDirty = () => dirty;
    options.lifecycle.isSaving = () => confirming || busy;
    options.lifecycle.dispose = () => Object.values(files).flat().forEach((entry) => URL.revokeObjectURL(entry.url));
  }
};
if (!document.body.dataset.app) window.initTicketResolve();
