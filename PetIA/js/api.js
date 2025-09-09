import config from '../config.js';
import { getToken, clearToken, fetchWithAuth, isTokenExpired } from './token.js';

export async function apiRequest(endpoint, options = {}) {
  const token = getToken();
  if (token && isTokenExpired(token)) {
    clearToken();
    window.location.href = 'index.html';
    throw new Error('Token expired');
  }
  let url = endpoint;
  if (!/^https?:/i.test(endpoint)) {
    url = config.apiBaseUrl.replace(/\/$/, '') + endpoint;
  }
  const response = await fetchWithAuth(url, options);
  const contentType = response.headers.get('content-type') || '';
  if (!response.ok) {
    let errorData = {};
    if (contentType.includes('application/json')) {
      errorData = await response.json();
    }
    if ((response.status === 401 || response.status === 403) && getToken()) {
      clearToken();
      window.location.href = 'index.html';
    }
    throw new Error(errorData.message || response.statusText);
  }
  if (!contentType.includes('application/json')) {
    throw new Error('Invalid JSON response');
  }
  const data = await response.json();
  return data;
}
