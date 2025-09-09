function parseToken(token) {
  try {
    const payload = token.split('.')[1];
    const decoded = atob(payload.replace(/-/g, '+').replace(/_/g, '/'));
    return JSON.parse(decoded);
  } catch {
    return null;
  }
}

export function setToken(token) {
  const payload = parseToken(token);
  if (payload && payload.exp) {
    localStorage.setItem('token_exp', payload.exp.toString());
  }
  localStorage.setItem('token', token);
}

export function getToken() {
  const token = localStorage.getItem('token');
  const exp = parseInt(localStorage.getItem('token_exp'), 10);
  if (!token || !exp || Date.now() >= exp * 1000) {
    clearToken();
    return null;
  }
  return token;
}

export function clearToken() {
  localStorage.removeItem('token');
  localStorage.removeItem('token_exp');
}

export async function fetchWithAuth(url, options = {}) {
  const token = getToken();
  const baseHeaders = options.headers || {};
  const headers = token
    ? { ...baseHeaders, Authorization: `Bearer ${token}` }
    : { ...baseHeaders };
  return fetch(url, { ...options, headers });
}
