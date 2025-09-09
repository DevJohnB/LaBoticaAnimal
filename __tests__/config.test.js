import { jest } from '@jest/globals';

describe('config', () => {
  afterEach(() => {
    delete process.env.API_BASE_URL;
    delete global.window.API_BASE_URL;
    jest.resetModules();
  });

  test('uses API_BASE_URL env variable', async () => {
    process.env.API_BASE_URL = 'https://example.com';
    const config = (await import('../PetIA/config.js')).default;
    expect(config.apiBaseUrl).toBe('https://example.com');
  });

  test('uses global API_BASE_URL when env variable is absent', async () => {
    global.window.API_BASE_URL = 'https://window.example.com';
    const config = (await import('../PetIA/config.js')).default;
    expect(config.apiBaseUrl).toBe('https://window.example.com');
  });

  test('falls back to default', async () => {
    const config = (await import('../PetIA/config.js')).default;
    expect(config.apiBaseUrl).toBe('https://laboticaanimal.com');
  });
});
