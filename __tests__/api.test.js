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

  test('clears token and redirects on 401', async () => {
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
    expect(window.location.href).toBe('index.html');
  });

  test('401 without token does not redirect', async () => {
    delete window.location;
    window.location = { href: '' };
    global.fetch.mockResolvedValue({
      status: 401,
      ok: false,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ message: 'Unauthorized' }),
    });
    await expect(apiRequest('/test')).rejects.toThrow('Unauthorized');
    expect(window.location.href).toBe('');
  });

  test('throws on non JSON response', async () => {
    global.fetch.mockResolvedValue({
      status: 200,
      ok: true,
      headers: new Headers({ 'content-type': 'text/html' }),
      json: async () => ({ ok: true }),
    });
    await expect(apiRequest('/test')).rejects.toThrow('Invalid JSON response');
  });
});

