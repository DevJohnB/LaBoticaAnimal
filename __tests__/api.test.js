import { jest } from '@jest/globals';
import { apiRequest } from '../PetIA/js/api.js';
import { setToken, getToken } from '../PetIA/js/token.js';
import config from '../PetIA/config.js';

const origError = console.error;
beforeAll(() => {
  console.error = () => {};
});

afterAll(() => {
  console.error = origError;
});

function createToken(expSeconds) {
  const payload = { exp: expSeconds };
  const base64url = btoa(JSON.stringify(payload)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  return `aaa.${base64url}.bbb`;
}

describe('apiRequest', () => {
  beforeEach(() => {
    document.cookie = '';
  });

  test('adds base URL and authorization header', async () => {
    const token = createToken(Math.floor(Date.now() / 1000) + 3600);
    setToken(token);
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      status: 200,
      headers: { get: () => 'application/json' },
      json: async () => ({ ok: true })
    });
    await apiRequest('/test');
    expect(fetch).toHaveBeenCalledWith(
      config.apiBaseUrl + '/test',
      expect.objectContaining({
        headers: expect.objectContaining({ Authorization: `Bearer ${token}` }),
        credentials: 'include'
      })
    );
  });

  test('clears token and redirects on 401', async () => {
    const token = createToken(Math.floor(Date.now() / 1000) + 3600);
    setToken(token);
    global.fetch = jest.fn().mockResolvedValue({
      ok: false,
      status: 401,
      statusText: 'Unauthorized',
      headers: { get: () => '' },
      text: async () => ''
    });
    await expect(apiRequest('/test')).rejects.toThrow('Unauthorized');
    expect(getToken()).toBeNull();
  });

  test('does not redirect on 401 without token', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: false,
      status: 401,
      statusText: 'Unauthorized',
      headers: { get: () => '' },
      text: async () => ''
    });
    await expect(apiRequest('/test')).rejects.toThrow('Unauthorized');
  });

  test('throws on non-JSON response', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      status: 200,
      headers: { get: () => 'text/plain' },
      text: async () => 'plain text'
    });
    await expect(apiRequest('/test')).rejects.toThrow('Expected JSON response');
  });
});
