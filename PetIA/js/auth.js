import config from '../config.js';

async function login(identifier, password) {
  const url = config.apiBaseUrl + config.endpoints.login;
  const payload = identifier.includes('@')
    ? { email: identifier, password }
    : { username: identifier, password };
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
  localStorage.setItem('token', data.token);
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
  const token = localStorage.getItem('token');
  if (token) {
    const url = config.apiBaseUrl + config.endpoints.logout;
    try {
      await fetch(url, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` }
      });
    } catch (err) {
      console.error('Logout error', err);
    }
  }
  localStorage.removeItem('token');
  window.location.href = 'index.html';
}

export async function validateToken() {
  const token = localStorage.getItem('token');
  if (!token) {
    await logout();
    return false;
  }
  const url = config.apiBaseUrl + config.endpoints.validateToken;
  try {
    const res = await fetch(url, {
      headers: { Authorization: `Bearer ${token}` }
    });
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

    const identifier = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    try {
      await login(identifier, password);
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
