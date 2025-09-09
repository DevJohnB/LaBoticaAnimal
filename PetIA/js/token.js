export function setToken(token) {
  localStorage.setItem('token', token);
}

export function getToken() {
  return localStorage.getItem('token');
}

export function clearToken() {
  localStorage.removeItem('token');
}

export function authHeaders(headers = {}) {
  const token = getToken();
  return token ? { ...headers, Authorization: `Bearer ${token}` } : { ...headers };
}

export async function fetchWithAuth(url, options = {}) {
  const headers = authHeaders(options.headers || {});
  return fetch(url, { ...options, headers });
}
