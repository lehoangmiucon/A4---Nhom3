(() => {
  const image = document.querySelector('#room-qr-image');
  const value = image?.dataset.qrValue;
  if (!image || !value || typeof window.qrcode !== 'function') return;

  try {
    const code = window.qrcode(0, 'M');
    code.addData(value);
    code.make();
    image.src = code.createDataURL(8, 4);
  } catch (error) {
    image.hidden = true;
    const message = document.createElement('p');
    message.className = 'room-qr-error';
    message.setAttribute('role', 'status');
    message.textContent = 'Không thể tạo mã QR. Hãy kiểm tra đường dẫn phòng.';
    image.insertAdjacentElement('afterend', message);
  }
})();
