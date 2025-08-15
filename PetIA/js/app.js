async function loadConfig() {
  if (!window.appConfig) {
    window.appConfig = await fetch('config.json').then(r => r.json());
  }
  return window.appConfig;
}

async function getProfile() {
  const token = localStorage.getItem('token');
  if (!token) {
    window.location.href = 'index.html';
    return;
  }
  const config = await loadConfig();
  const url = config.apiBaseUrl + config.endpoints.profile.get;
  const res = await fetch(url, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  if (res.ok) {
    const data = await res.json();
    const container = document.getElementById('userData');
    if (container) {
      container.innerHTML = `<p><strong>${data.name || ''}</strong></p><p>${data.email || ''}</p>`;
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
