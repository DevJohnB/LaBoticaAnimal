export function setToken(token) {
  localStorage.setItem('token', token);
}

export function getToken() {
  return localStorage.getItem('token');
}

export function clearToken() {
  localStorage.removeItem('token');
}

export async function fetchWithAuth(url, options = {}) {
  const token = getToken();
  const baseHeaders = options.headers || {};
  const headers = token
    ? { ...baseHeaders, Authorization: `Bearer ${token}` }
    : baseHeaders;
  return fetch(url, { ...options, headers });
}
