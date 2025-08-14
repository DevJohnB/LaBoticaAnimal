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

document.getElementById('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  try {
    const data = await login(email, password);
    localStorage.setItem('petiaToken', data.token);
    document.getElementById('login-view').classList.add('hidden');
    const frame = document.getElementById('chat-frame');
    frame.src = `${CONFIG.n8nChatUrl}?token=${encodeURIComponent(data.token)}`;
    document.getElementById('chat-view').classList.remove('hidden');
  } catch (err) {
    document.getElementById('login-error').textContent = err.message;
  }
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
