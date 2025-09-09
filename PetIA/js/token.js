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
  let cookie = `token=${token}; path=/; SameSite=Lax`;
  if (location.protocol === 'https:') {
    cookie += '; Secure';
  }
  document.cookie = cookie;
}

export function getToken() {
  return document.cookie
    .split(';')
    .map(c => c.trim())
    .find(c => c.startsWith('token='))
    ?.split('=')[1] || '';
}

export function clearToken() {
  document.cookie = 'token=; Max-Age=0; path=/;';
}

export async function fetchWithAuth(input, options = {}) {
  const token = getToken();
  const headers = new Headers(options.headers || {});
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }
  return fetch(input, { ...options, headers, credentials: 'include' });
}
