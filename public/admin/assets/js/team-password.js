(() => {
  const dialog = document.querySelector('#team-password-modal');
  if (!dialog) return;
  const open = () => { if (!dialog.open) dialog.showModal(); };
  document.querySelectorAll('[data-open-self-password]').forEach(button => button.addEventListener('click', open));
  dialog.querySelectorAll('[data-close-self-password]').forEach(button => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('close', () => dialog.querySelector('form').reset());
  if (dialog.dataset.hasPasswordErrors === 'true') open();
})();
