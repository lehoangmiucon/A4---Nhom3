(() => {
  let lightbox;
  let previousOverflow = '';

  function closeLightbox() {
    if (!lightbox) return;
    lightbox.remove();
    lightbox = null;
    document.documentElement.style.overflow = previousOverflow;
  }

  document.addEventListener('click', (event) => {
    const link = event.target.closest('.js-image-preview');
    if (!link) return;

    event.preventDefault();
    closeLightbox();
    previousOverflow = document.documentElement.style.overflow;

    lightbox = document.createElement('div');
    lightbox.className = 'image-lightbox';
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.setAttribute('aria-label', link.title || link.querySelector('img')?.alt || 'Ảnh đính kèm');

    const image = document.createElement('img');
    image.src = link.href;
    image.alt = link.querySelector('img')?.alt || link.title || 'Ảnh đính kèm';
    image.className = 'image-lightbox__image';

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'image-lightbox__close';
    closeButton.setAttribute('aria-label', 'Đóng ảnh');
    closeButton.textContent = '×';
    closeButton.addEventListener('click', closeLightbox);

    lightbox.append(image, closeButton);
    lightbox.addEventListener('click', (backdropEvent) => {
      if (backdropEvent.target === lightbox) closeLightbox();
    });
    document.body.append(lightbox);
    document.documentElement.style.overflow = 'hidden';
    closeButton.focus();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeLightbox();
  });
})();
