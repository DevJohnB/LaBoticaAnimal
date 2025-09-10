import { jest } from '@jest/globals';
import { apiRequest } from '../PetIA/js/api.js';
import { parseToken, setToken, clearToken } from '../PetIA/js/token.js';
import 'whatwg-fetch';

function createValidToken() {
  const payload = Buffer.from(
    JSON.stringify({ exp: Math.floor(Date.now() / 1000) + 3600 })
  ).toString('base64');
  return `aa.${payload}.bb`;
}

describe('error handling', () => {

  beforeEach(() => {
    clearToken();
    global.fetch = jest.fn();
  });

  test('parseToken returns null on invalid token', () => {
    expect(parseToken('invalid')).toBeNull();
  });

  test('apiRequest throws when body contains error', async () => {
    setToken(createValidToken());
    global.fetch.mockResolvedValue({
      status: 200,
      ok: true,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ error: 'fail' }),
    });
    await expect(apiRequest('/test')).rejects.toThrow('fail');
  });
});