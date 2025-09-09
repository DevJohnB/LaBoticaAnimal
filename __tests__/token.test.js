import { parseToken, isTokenExpired, setToken, getToken, clearToken } from '../PetIA/js/token.js';

describe('token utils', () => {
  test('parses token payload', () => {
    const payload = { exp: Math.floor(Date.now() / 1000) + 60 };
    const token = 'h.' + btoa(JSON.stringify(payload)) + '.s';
    expect(parseToken(token).exp).toBe(payload.exp);
  });

  test('detects expiration', () => {
    const past = 'h.' + btoa(JSON.stringify({ exp: Math.floor(Date.now() / 1000) - 10 })) + '.s';
    const future = 'h.' + btoa(JSON.stringify({ exp: Math.floor(Date.now() / 1000) + 10 })) + '.s';
    expect(isTokenExpired(past)).toBe(true);
    expect(isTokenExpired(future)).toBe(false);
  });

  test('clears stored token', () => {
    setToken('abc');
    expect(sessionStorage.getItem('token')).toBe('abc');
    expect(getToken()).toBe('abc');
    clearToken();
    expect(getToken()).toBe('');
    expect(sessionStorage.getItem('token')).toBeNull();
  });
});
