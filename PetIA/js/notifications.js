export function showToast(message, type = 'success') {
  if (typeof document === 'undefined') return;
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  if (!document.getElementById('toast-styles')) {
    const link = document.createElement('link');
    link.id = 'toast-styles';
    link.rel = 'stylesheet';
    link.href = 'css/notifications.css';
    document.head.appendChild(link);
  }
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => toast.classList.add('show'), 10);
  setTimeout(() => {
    toast.classList.remove('show');
    toast.addEventListener('transitionend', () => toast.remove());
  }, 3000);
}
