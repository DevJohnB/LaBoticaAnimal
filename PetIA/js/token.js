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
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem('token', token);
  }
  document.cookie = `token=${token}; path=/;`;
}

export function getToken() {
  let token = sessionStorage.getItem('token');
  if (!token && typeof localStorage !== 'undefined') {
    token = localStorage.getItem('token');
  }
  if (!token) {
    const match = document.cookie.match(/(?:^|; )token=([^;]+)/);
    token = match ? decodeURIComponent(match[1]) : '';
  }
  return token || '';
}

export function clearToken() {
  sessionStorage.removeItem('token');
  if (typeof localStorage !== 'undefined') {
    localStorage.removeItem('token');
  }
  // Remove cookie if present
  document.cookie = 'token=; Max-Age=0; path=/;';
}

export async function fetchWithAuth(input, options = {}) {
  const token = getToken();
  if (!token) {
    throw new Error('Missing token');
  }
  const headers = new Headers(options.headers || {});
  headers.set('Authorization', `Bearer ${token}`);
  // Consumers can supply `credentials` in options when cookies are needed
  return fetch(input, { ...options, headers });
}

export function ensureAuth() {
  const token = getToken();
  if (!token) {
    console.warn('Missing authentication token; redirecting to login');
    if (typeof window !== 'undefined') {
      window.location.href = 'index.html';
    }
    return false;
  }
  return true;
}
