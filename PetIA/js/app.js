import config from '../config.js';
import { logout } from './auth.js';

async function getProfile() {
  const token = localStorage.getItem('token');
  if (!token) {
    window.location.href = 'index.html';
    return;
  }
  const url = config.apiBaseUrl + config.endpoints.profile;
  const res = await fetch(url, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  if (res.ok) {
    const data = await res.json();
    const container = document.getElementById('userData');
    if (container) {
      const fields = [
        ['display_name', 'Nombre para mostrar'],
        ['email', 'Correo electrónico'],
        ['username', 'Usuario'],
        ['first_name', 'Nombre'],
        ['last_name', 'Apellido'],
        ['nickname', 'Apodo'],
        ['description', 'Descripción'],
        ['user_url', 'Sitio web']
      ];
      container.innerHTML = fields
        .filter(([key]) => data[key])
        .map(([key, label]) => `<p><strong>${label}:</strong> ${data[key]}</p>`)
        .join('');
    }
  } else {
    logout();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('userData')) {
    getProfile();
  }
});
