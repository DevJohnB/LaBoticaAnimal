import { clearToken } from './token.js';

document.addEventListener('DOMContentLoaded', () => {
  const link = document.getElementById('logout-link');
  if (link) {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      clearToken();
      window.location.href = 'index.html';
    });
  }
});
