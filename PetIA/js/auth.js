import config from '../config.js';
import { setToken, getToken, clearToken } from './token.js';
import { apiRequest } from './api.js';
import { handleError } from './error.js';

async function login(email, password) {
  const data = await apiRequest(config.endpoints.login, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  setToken(data.token);
}

async function requestPasswordReset(email) {
  await apiRequest(config.endpoints.passwordResetRequest, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email })
  });
}

export async function logout() {
  const token = getToken();
  if (token) {
    try {
      await apiRequest(config.endpoints.logout, { method: 'POST' });
    } catch (err) {
      handleError(err, 'Logout error');
    }
  }
  clearToken();
  window.location.href = 'index.html';
}

export async function validateToken() {
  const token = getToken();
  if (!token) {
    await logout();
    return false;
  }
  try {
    await apiRequest(config.endpoints.validateToken);
    return true;
  } catch {
    return false;
  }
}

// Event listeners
const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    try {
      await login(email, password);
      window.location.href = 'user.html';
    } catch (err) {
      handleError(err, 'Login error');
    }
  });
}

const recoverForm = document.getElementById('recoverForm');
if (recoverForm) {
  recoverForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('email').value;
    try {
      await requestPasswordReset(email);
      alert('Solicitud enviada');
    } catch (err) {
      handleError(err, 'Error');
    }
  });
}

const logoutBtn = document.getElementById('logoutBtn');
if (logoutBtn) {
  logoutBtn.addEventListener('click', logout);
}
