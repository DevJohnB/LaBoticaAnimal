async function login(email, password) {
  const res = await fetch(`${CONFIG.apiBase}/wp-json/petia-app-bridge/v1/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || 'Error de autenticación');
  }
  return res.json();
}

async function fetchProfile(token) {
  const res = await fetch(`${CONFIG.apiBase}/wp-json/petia-app-bridge/v1/profile`, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  if (!res.ok) {
    throw new Error('No se pudo obtener el perfil');
  }
  return res.json();
}

document.getElementById('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  try {
    const data = await login(email, password);
    localStorage.setItem('petiaToken', data.token);
    const profile = await fetchProfile(data.token);
    document.getElementById('profile-name').textContent = profile.name || '';
    document.getElementById('profile-email').textContent = profile.email || '';
    document.getElementById('login-view').classList.add('hidden');
    document.getElementById('app-view').classList.remove('hidden');
    const frame = document.getElementById('chat-frame');
    frame.src = `${CONFIG.n8nChatUrl}?token=${encodeURIComponent(data.token)}`;
    showChat();
    document.getElementById('login-view').classList.add('hidden');
    const frame = document.getElementById('chat-frame');
    frame.src = `${CONFIG.n8nChatUrl}?token=${encodeURIComponent(data.token)}`;
    document.getElementById('chat-view').classList.remove('hidden');
  } catch (err) {
    document.getElementById('login-error').textContent = err.message;
  }
});

function showChat() {
  document.getElementById('chat-view').classList.remove('hidden');
  document.getElementById('profile-view').classList.add('hidden');
}

function showProfile() {
  document.getElementById('profile-view').classList.remove('hidden');
  document.getElementById('chat-view').classList.add('hidden');
}

document.getElementById('nav-chat').addEventListener('click', showChat);
document.getElementById('nav-profile').addEventListener('click', showProfile);
document.getElementById('logout').addEventListener('click', () => {
  localStorage.removeItem('petiaToken');
  document.getElementById('app-view').classList.add('hidden');
  document.getElementById('login-view').classList.remove('hidden');
  document.getElementById('login-form').reset();
});

// Simple voice to text using Web Speech API
const startBtn = document.getElementById('start-voice');
if (startBtn && ('SpeechRecognition' in window || 'webkitSpeechRecognition' in window)) {
  const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
  const rec = new SpeechRec();
  rec.lang = 'es-ES';
  rec.addEventListener('result', (e) => {
    const text = Array.from(e.results).map(r => r[0].transcript).join('');
    document.getElementById('voice-output').value = text;
  });
  startBtn.addEventListener('click', () => rec.start());
} else if (startBtn) {
  startBtn.disabled = true;
  startBtn.textContent = 'Sin soporte de voz';
}
