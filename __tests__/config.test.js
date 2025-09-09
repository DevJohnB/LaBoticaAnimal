import { jest } from '@jest/globals';

describe('config', () => {
  afterEach(() => {
    delete process.env.API_BASE_URL;
    delete global.API_BASE_URL;
    jest.resetModules();
  });

  test('process.env has priority', async () => {
    process.env.API_BASE_URL = 'http://env';
    const mod = await import('../PetIA/config.js');
    expect(mod.default.apiBaseUrl).toBe('http://env');
  });

  test('window.API_BASE_URL is second', async () => {
    global.API_BASE_URL = 'http://window';
    const mod = await import('../PetIA/config.js');
    expect(mod.default.apiBaseUrl).toBe('http://window');
  });

  test('uses default when unset', async () => {
    const mod = await import('../PetIA/config.js');
    expect(mod.default.apiBaseUrl).toBe('https://laboticaanimal.com');
  });
});
