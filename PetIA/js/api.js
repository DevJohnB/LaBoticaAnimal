import config from '../config.js';
import { getToken, clearToken, fetchWithAuth } from './token.js';

export function redirectToLogin() {
  if (typeof window !== 'undefined' && window.location) {
    window.location.href = 'index.html';
  }
}

export async function apiRequest(endpoint, options = {}) {
  const url = endpoint.startsWith('http') ? endpoint : config.apiBaseUrl + endpoint;
  const token = getToken();
  const res = await fetchWithAuth(url, options);
  if (res.status === 401 && token) {
    clearToken();
    redirectToLogin();
    throw new Error('Unauthorized');
  }
  if (!res.ok) {
    const message = await res.text();
    throw new Error(message || res.statusText);
  }
  const contentType = res.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    return res.json();
  }
  return res.text();
}
