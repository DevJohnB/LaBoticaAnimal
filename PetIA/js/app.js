import config from '../config.js';
import { logout, validateToken } from './auth.js';
import { fetchWithAuth, getToken } from './token.js';
import { handleError } from './error.js';

async function getProfile() {
  const valid = await validateToken();
  if (!valid) return;
  const url = config.apiBaseUrl + config.endpoints.profile;
  const res = await fetchWithAuth(url);
  if (res.ok) {
    const data = await res.json();
    renderProfileForm(data);
  } else {
    logout();
  }
}

function renderProfileForm(data) {
  const container = document.getElementById('userData');
  if (!container) return;

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

  container.innerHTML = `
    <form id="profileForm">
      ${fields
        .map(([key, label]) => {
          const value = data[key] || '';
          const readonly = key === 'email' || key === 'username' ? 'readonly' : '';
          const input = key === 'description'
            ? `<textarea name="${key}" ${readonly}>${value}</textarea>`
            : `<input name="${key}" value="${value}" ${readonly}>`;
          return `<label>${label}<br>${input}</label>`;
        })
        .join('<br>')}
      <br><button type="submit" class="btn-primary">Actualizar</button>
    </form>
  `;

  document
    .getElementById('profileForm')
    .addEventListener('submit', updateProfile);
}

async function updateProfile(e) {
  e.preventDefault();
  const token = getToken();
  if (!token) {
    window.location.href = 'index.html';
    return;
  }

  const form = e.target;
  const data = Object.fromEntries(new FormData(form).entries());
  delete data.username;
  delete data.email;

  const url = config.apiBaseUrl + config.endpoints.profile;
  const res = await fetchWithAuth(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });

  if (res.ok) {
    console.log('Datos actualizados');
    getProfile();
  } else {
    handleError(new Error('Error al actualizar'));
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  const valid = await validateToken();
  if (!valid) return;
  if (document.getElementById('userData')) {
    getProfile();
  }
});
