let overlay;

export function showLoading() {
  if (overlay) return;
  overlay = document.createElement('div');
  overlay.id = 'loading-overlay';
  const spinner = document.createElement('div');
  spinner.className = 'spinner';
  overlay.appendChild(spinner);
  document.body.appendChild(overlay);
}

export function hideLoading() {
  if (!overlay) return;
  overlay.remove();
  overlay = null;
}
