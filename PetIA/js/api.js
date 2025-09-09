import config from '../config.js';
import { getToken, clearToken, fetchWithAuth } from './token.js';

export async function apiRequest(endpoint, options = {}) {
  let url = endpoint;
  if (!/^https?:/i.test(endpoint)) {
    url = config.apiBaseUrl.replace(/\/$/, '') + endpoint;
  }
  const response = await fetchWithAuth(url, options);
  const contentType = response.headers.get('content-type') || '';
  if (response.status === 401 && getToken()) {
    clearToken();
    window.location.href = 'index.html';
    throw new Error('Unauthorized');
  }
  if (!contentType.includes('application/json')) {
    throw new Error('Invalid JSON response');
  }
  const data = await response.json();
  if (data && data.error) {
    throw new Error(data.error);
  }
  return data;
}
