function parseToken(token) {
  try {
    const payload = token.split('.')[1];
    const decoded = atob(payload.replace(/-/g, '+').replace(/_/g, '/'));
    return JSON.parse(decoded);
  } catch {
    return null;
  }
}

function setCookie(name, value, exp) {
  let cookie = `${name}=${value}; path=/; SameSite=Lax;`;
  if (exp) {
    cookie += ` expires=${new Date(exp * 1000).toUTCString()};`;
  }
  if (location.protocol === 'https:') {
    cookie += ' Secure;';
  }
  document.cookie = cookie;
}

function getCookie(name) {
  return document.cookie
    .split('; ')
    .find(row => row.startsWith(name + '='))
    ?.split('=')[1] || null;
}

export function setToken(token) {
  const payload = parseToken(token);
  const exp = payload && payload.exp ? payload.exp : null;
  setCookie('token', token, exp);
}

export function getToken() {
  const token = getCookie('token');
  if (!token) return null;
  const payload = parseToken(token);
  if (!payload || !payload.exp || Date.now() >= payload.exp * 1000) {
    clearToken();
    return null;
  }
  return token;
}

export function clearToken() {
  document.cookie = 'token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/;';
}

export async function fetchWithAuth(url, options = {}) {
  const token = getToken();
  const baseHeaders = options.headers || {};
  const headers = token
    ? { ...baseHeaders, Authorization: `Bearer ${token}` }
    : { ...baseHeaders };
  return fetch(url, { ...options, headers, credentials: 'include' });
}
