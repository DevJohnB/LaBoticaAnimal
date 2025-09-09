export function parseToken(token) {
  try {
    return JSON.parse(atob(token.split('.')[1]));
  } catch (e) {
    return null;
  }
}

export function isTokenExpired(token) {
  const payload = parseToken(token);
  if (!payload || !payload.exp) return true;
  return payload.exp < Math.floor(Date.now() / 1000);
}

export function setToken(token) {
  sessionStorage.setItem('token', token);
}

export function getToken() {
  return sessionStorage.getItem('token') || '';
}

export function clearToken() {
  sessionStorage.removeItem('token');
  // Remove legacy cookie if present
  document.cookie = 'token=; Max-Age=0; path=/;';
}

export async function fetchWithAuth(input, options = {}) {
  const token = getToken();
  const headers = new Headers(options.headers || {});
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }
  // Consumers can supply `credentials` in options when cookies are needed
  return fetch(input, { ...options, headers });
}
