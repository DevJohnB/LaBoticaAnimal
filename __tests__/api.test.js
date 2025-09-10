import { jest } from '@jest/globals';
import { apiRequest } from '../PetIA/js/api.js';
import { setToken, getToken, clearToken } from '../PetIA/js/token.js';
import 'whatwg-fetch';

function createValidToken() {
  const payload = Buffer.from(
    JSON.stringify({ exp: Math.floor(Date.now() / 1000) + 3600 })
  ).toString('base64');
  return `aa.${payload}.bb`;
}

describe('apiRequest', () => {
  beforeEach(() => {
    clearToken();
    global.fetch = jest.fn();
    localStorage.clear();
  });

  test('adds Authorization header', async () => {
    const token = createValidToken();
    setToken(token);
    global.fetch.mockResolvedValue({
      status: 200,
      ok: true,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ ok: true }),
    });
    await apiRequest('/test');
    const headers = global.fetch.mock.calls[0][1].headers;
    expect(headers.get('Authorization')).toBe(`Bearer ${token}`);
  });

  test('clears token without redirect on 401 by default', async () => {
    setToken(createValidToken());
    delete window.location;
    window.location = { href: '' };
    global.fetch.mockResolvedValue({
      status: 401,
      ok: false,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ message: 'Unauthorized' }),
    });
    await expect(apiRequest('/test')).rejects.toThrow('Unauthorized');
    expect(getToken()).toBe('');
    expect(window.location.href).toBe('');
    expect(localStorage.getItem('restoreCart')).toBe('1');
  });

  test('redirects on 401 when option enabled', async () => {
    setToken(createValidToken());
    delete window.location;
    window.location = { href: '' };
    global.fetch.mockResolvedValue({
      status: 401,
      ok: false,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ message: 'Unauthorized' }),
    });
    await expect(apiRequest('/test', { redirectOnAuthError: true })).rejects.toThrow('Unauthorized');
    expect(getToken()).toBe('');
    expect(window.location.href).toBe('index.html');
  });

  test('missing token prevents fetch', async () => {
    delete window.location;
    window.location = { href: '' };
    await expect(apiRequest('/test')).rejects.toThrow('Missing authentication token');
    expect(global.fetch).not.toHaveBeenCalled();
    expect(window.location.href).toBe('');
  });

  test('throws Network error on fetch failure', async () => {
    setToken(createValidToken());
    global.fetch.mockRejectedValue(new Error('failed'));
    await expect(apiRequest('/test')).rejects.toThrow('Network error');
  });

  test('throws on boolean false response', async () => {
    setToken(createValidToken());
    global.fetch.mockResolvedValue({
      status: 200,
      ok: true,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => false,
    });
    await expect(apiRequest('/test')).rejects.toThrow('Respuesta no válida del servidor');
  });

  test('throws on non JSON response', async () => {
    setToken(createValidToken());
    global.fetch.mockResolvedValue({
      status: 200,
      ok: true,
      headers: new Headers({ 'content-type': 'text/html' }),
      json: async () => ({ ok: true }),
    });
    await expect(apiRequest('/test')).rejects.toThrow('Invalid JSON response');
  });
});

