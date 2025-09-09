import config from '../config.js';
import { setToken, getToken, clearToken, fetchWithAuth } from './token.js';

async function login(email, password) {
  const url = config.apiBaseUrl + config.endpoints.login;
  const payload = { email, password };
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  if (!res.ok) {
    const message = await res.text();
    throw new Error(message || 'Login failed');
  }
  const data = await res.json();
  setToken(data.token);
}

async function requestPasswordReset(email) {
  const url = config.apiBaseUrl + config.endpoints.passwordResetRequest;
  await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email })
  });
}

export async function logout() {
  const token = getToken();
  if (token) {
    const url = config.apiBaseUrl + config.endpoints.logout;
    try {
      await fetchWithAuth(url, { method: 'POST' });
    } catch (err) {
      console.error('Logout error', err);
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
  const url = config.apiBaseUrl + config.endpoints.validateToken;
  try {
    const res = await fetchWithAuth(url);
    if (!res.ok) {
      await logout();
      return false;
    }
    return true;
  } catch (err) {
    await logout();
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
      alert('Login error');
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
      alert('Error');
    }
  });
}

const logoutBtn = document.getElementById('logoutBtn');
if (logoutBtn) {
  logoutBtn.addEventListener('click', logout);
}
