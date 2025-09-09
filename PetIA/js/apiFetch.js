import { logout } from './auth.js';

export async function apiFetch(url, options = {}) {
  const { skipAuthError, ...fetchOptions } = options;
  const token = localStorage.getItem('token');
  const headers = { ...(fetchOptions.headers || {}) };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
    try {
      const [, payload] = token.split('.');
      if (payload) {
        const { exp } = JSON.parse(atob(payload));
        if (exp && Date.now() >= exp * 1000) {
          await logout();
          throw new Error('Token expired');
        }
      }
    } catch (err) {
      console.error('Token parse error', err);
    }
  }

  const res = await fetch(url, { ...fetchOptions, headers });
  if (res.status === 401 && token && !skipAuthError) {
    await logout();
    throw new Error('Unauthorized');
  }
  return res;
}
