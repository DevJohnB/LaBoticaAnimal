import { jest } from '@jest/globals';
import { setToken, getToken, clearToken, fetchWithAuth } from '../PetIA/js/token.js';

function createToken(expSeconds) {
  const payload = { exp: expSeconds };
  const base64url = btoa(JSON.stringify(payload)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  return `aaa.${base64url}.bbb`;
}

describe('token utilities', () => {
  beforeEach(() => {

    document.cookie = '';


    global.fetch = jest.fn().mockResolvedValue({ ok: true });
  });

  test('stores and retrieves valid token', () => {
    const token = createToken(Math.floor(Date.now() / 1000) + 3600);
    setToken(token);
    expect(getToken()).toBe(token);
  });

  test('clears expired token', () => {
    const token = createToken(Math.floor(Date.now() / 1000) - 10);
    setToken(token);
    expect(getToken()).toBeNull();
    expect(document.cookie).not.toContain('token=');
  });

  test('fetchWithAuth adds header', async () => {
    const token = createToken(Math.floor(Date.now() / 1000) + 3600);
    setToken(token);
    await fetchWithAuth('/test');
    expect(fetch).toHaveBeenCalledWith('/test', expect.objectContaining({
      headers: { Authorization: `Bearer ${token}` },
      credentials: 'include'
    }));
  });

  test('fetchWithAuth omits header when token expired', async () => {
    const token = createToken(Math.floor(Date.now() / 1000) - 10);
    setToken(token);
    await fetchWithAuth('/test');
    expect(fetch).toHaveBeenCalledWith('/test', expect.objectContaining({
      headers: {},
      credentials: 'include'
    }));
  });
});
