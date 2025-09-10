import config from '../config.js';
import { getToken, clearToken, fetchWithAuth, isTokenExpired } from './token.js';

export async function apiRequest(endpoint, { redirectOnAuthError = false, ...options } = {}) {
  const token = getToken();
  if (!token) {
    if (redirectOnAuthError) {
      window.location.href = 'index.html';
    }
    throw new Error('Missing authentication token');
  }
  if (isTokenExpired(token)) {
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('restoreCart', '1');
    }
    if (redirectOnAuthError) {
      window.location.href = 'index.html';
    }
    throw new Error('Token expired');
  }
  let url = endpoint;
  if (!/^https?:/i.test(endpoint)) {
    url = config.apiBaseUrl.replace(/\/$/, '') + endpoint;
  }
  let response;
  try {
    response = await fetchWithAuth(url, options);
  } catch (e) {
    throw new Error('Network error');
  }
  if (response.status === 401 || response.status === 403) {
    let errorBody = {};
    try {
      errorBody = await response.json();
    } catch (e) {
      // ignore JSON parse errors
    }
    const message = errorBody.message || errorBody.error || '';
    const code = errorBody.code || '';
    const tokenInvalid =
      code === 'TOKEN_EXPIRED' ||
      code === 'TOKEN_INVALID' ||
      /token (?:expired|invalid)/i.test(message);
    if (tokenInvalid) {
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem('restoreCart', '1');
      }
      clearToken();
      if (redirectOnAuthError) {
        window.location.href = 'index.html';
      }
      throw new Error('Unauthorized');
    }
    throw new Error('Authorization failed. Please retry.');
  }
  if (!response.ok) {
    const message = response.status >= 500 ? 'Server error' : 'Unexpected response';
    throw new Error(message);
  }
  const contentType = response.headers.get('content-type') || '';
  if (!contentType.includes('application/json')) {
    throw new Error('Invalid JSON response');
  }
  const data = await response.json();
  if (data === false) {
    throw new Error('Respuesta no válida del servidor');
  }
  if (data && data.error) {
    throw new Error(data.error);
  }
  return data;
}
