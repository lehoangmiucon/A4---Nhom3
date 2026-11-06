(() => {
    'use strict';

    const input = document.querySelector('.report-form #attachment');
    const preview = document.getElementById('attachment-preview');
    const feedback = document.getElementById('attachment-feedback');

    if (!input || !preview || !feedback) return;

    const maxFiles = 5;
    const maxBytes = 5 * 1024 * 1024;
    const selected = [];
    const sizeFormat = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 });

    let transfer;
    try {
        transfer = new DataTransfer();
    } catch (_) {
        input.disabled = true;
        showFeedback('Trình duyệt này chưa hỗ trợ chọn ảnh. Bạn vẫn có thể gửi báo cáo không kèm ảnh.');
        return;
    }

    const dialog = document.createElement('dialog');
    dialog.className = 'attachment-lightbox';
    dialog.setAttribute('aria-label', 'Xem ảnh minh họa');

    const panel = document.createElement('div');
    panel.className = 'attachment-lightbox-panel';

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'attachment-lightbox-close';
    closeButton.setAttribute('aria-label', 'Đóng ảnh');
    closeButton.textContent = '×';

    const fullImage = document.createElement('img');
    fullImage.className = 'attachment-lightbox-image';
    fullImage.alt = '';

    const caption = document.createElement('p');
    caption.className = 'attachment-lightbox-caption';

    panel.append(closeButton, fullImage, caption);
    dialog.append(panel);
    document.body.append(dialog);

    let previousTrigger = null;
    let openedEntry = null;

    function showFeedback(message) {
        feedback.textContent = message;
        feedback.hidden = !message;
    }

    function fileKey(file) {
        return `${file.name}\u0000${file.size}\u0000${file.lastModified}\u0000${file.type}`;
    }

    function syncFiles() {
        transfer = new DataTransfer();
        selected.forEach(({ file }) => transfer.items.add(file));
        input.files = transfer.files;
    }

    function closeLightbox() {
        if (dialog.open) dialog.close();
    }

    function openLightbox(entry, trigger) {
        if (dialog.open) dialog.close();
        previousTrigger = trigger;
        openedEntry = entry;
        fullImage.src = entry.url;
        fullImage.alt = entry.file.name;
        caption.textContent = entry.file.name;
        dialog.showModal();
        closeButton.focus();
    }

    function render() {
        preview.replaceChildren();
        preview.hidden = selected.length === 0;

        selected.forEach((entry) => {
            const card = document.createElement('div');
            card.className = 'attachment-preview-item';

            const openButton = document.createElement('button');
            openButton.type = 'button';
            openButton.className = 'attachment-preview-open';
            openButton.setAttribute('aria-label', `Xem ảnh ${entry.file.name}`);

            const thumbnail = document.createElement('img');
            thumbnail.className = 'attachment-preview-image';
            thumbnail.src = entry.url;
            thumbnail.alt = '';
            thumbnail.loading = 'lazy';
            openButton.append(thumbnail);

            const name = document.createElement('span');
            name.className = 'attachment-preview-name';
            name.title = entry.file.name;
            name.textContent = entry.file.name;

            const size = document.createElement('span');
            size.className = 'attachment-preview-size';
            size.textContent = `${sizeFormat.format(entry.file.size / (1024 * 1024))} MB`;

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'attachment-preview-remove';
            removeButton.setAttribute('aria-label', `Bỏ ảnh ${entry.file.name}`);
            removeButton.textContent = '×';

            openButton.addEventListener('click', () => openLightbox(entry, openButton));
            removeButton.addEventListener('click', () => {
                if (openedEntry === entry) closeLightbox();
                const index = selected.indexOf(entry);
                if (index === -1) return;
                selected.splice(index, 1);
                URL.revokeObjectURL(entry.url);
                syncFiles();
                input.setCustomValidity('');
                render();
                showFeedback('');
            });

            card.append(openButton, name, size, removeButton);
            preview.append(card);
        });
    }

    input.addEventListener('click', () => {
        // The rejected files are already removed from the input. Allow retry or cancel.
        input.setCustomValidity('');
        showFeedback('');
    });

    input.addEventListener('change', () => {
        const incoming = Array.from(input.files || []);
        const unique = incoming.filter((file, index) =>
            !selected.some((entry) => entry.key === fileKey(file)) &&
            incoming.findIndex((candidate) => fileKey(candidate) === fileKey(file)) === index
        );
        const errors = [];
        if (unique.some((file) => !file.type.startsWith('image/'))) {
            errors.push('Chỉ được tải tệp ảnh.');
        }
        if (unique.some((file) => file.size === 0 || file.size > maxBytes)) {
            errors.push('Mỗi ảnh phải có dung lượng từ 1 byte đến 5 MB.');
        }
        if (selected.length + unique.length > maxFiles) {
            errors.push(`Chỉ được chọn tối đa ${maxFiles} ảnh.`);
        }

        if (errors.length) {
            syncFiles();
            input.setCustomValidity('');
            showFeedback(`Không thêm ảnh đã chọn. ${errors.join(' ')}`);
            return;
        }

        input.setCustomValidity('');
        unique.forEach((file) => {
            selected.push({ file, key: fileKey(file), url: URL.createObjectURL(file) });
        });
        syncFiles();
        render();
        showFeedback('');
    });

    closeButton.addEventListener('click', closeLightbox);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) closeLightbox();
    });
    dialog.addEventListener('close', () => {
        fullImage.removeAttribute('src');
        fullImage.alt = '';
        caption.textContent = '';
        openedEntry = null;
        if (previousTrigger?.isConnected) previousTrigger.focus();
        previousTrigger = null;
    });

    input.form?.addEventListener('reset', () => {
        closeLightbox();
        selected.forEach(({ url }) => URL.revokeObjectURL(url));
        selected.length = 0;
        preview.replaceChildren();
        preview.hidden = true;
        input.setCustomValidity('');
        showFeedback('');
    });

    window.addEventListener('pagehide', () => {
        selected.forEach(({ url }) => URL.revokeObjectURL(url));
    });
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        selected.forEach((entry) => { entry.url = URL.createObjectURL(entry.file); });
        render();
    });
})();
