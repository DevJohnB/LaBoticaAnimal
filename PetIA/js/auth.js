async function loadConfig() {
  if (!window.appConfig) {
    window.appConfig = await fetch('config.json').then(r => r.json());
  }
  return window.appConfig;
}

async function login(identifier, password) {
  const config = await loadConfig();
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
  const config = await loadConfig();
  const url = config.apiBaseUrl + config.endpoints.passwordResetRequest;
  await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email })
  });
}

function logout() {
  localStorage.removeItem('token');
  window.location.href = 'index.html';
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
