import { jest } from '@jest/globals';
import { apiRequest } from '../PetIA/js/api.js';
import { parseToken, setToken, clearToken } from '../PetIA/js/token.js';
import 'whatwg-fetch';

  describe('error handling', () => {
  beforeEach(() => {
    clearToken();
    global.fetch = jest.fn();
  });

  test('parseToken returns null on invalid token', () => {
    expect(parseToken('invalid')).toBeNull();
  });

    test('apiRequest returns body even if it contains error field', async () => {
      setToken('abc');
      global.fetch.mockResolvedValue({
        status: 200,
        ok: true,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ error: 'fail' }),
      });
      await expect(apiRequest('/test')).resolves.toEqual({ error: 'fail' });
    });
  });
