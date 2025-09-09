import { jest } from '@jest/globals';

describe('config', () => {
  afterEach(() => {
    delete process.env.API_BASE_URL;
    jest.resetModules();
  });

  test('uses API_BASE_URL env variable', async () => {
    process.env.API_BASE_URL = 'https://example.com';
    const config = (await import('../PetIA/config.js')).default;
    expect(config.apiBaseUrl).toBe('https://example.com');
  });

  test('falls back to default', async () => {
    const config = (await import('../PetIA/config.js')).default;
    expect(config.apiBaseUrl).toBe('https://laboticaanimal.com');
  });
});
