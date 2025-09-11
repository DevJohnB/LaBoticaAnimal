import { jest } from '@jest/globals';

describe('cart', () => {
  beforeEach(() => {
    jest.resetModules();
    localStorage.clear();
    sessionStorage.clear();
  });

  test('stores cart_key from responses', async () => {
    const mockApi = jest.fn(async () => ({ cart_key: 'abc123', items: [] }));
    jest.unstable_mockModule('../PetIA/js/api.js', () => ({ apiRequest: mockApi }));
    const { addItem, getCartKey } = await import('../PetIA/js/cart.js');
    sessionStorage.setItem('token', 't');
    await addItem(1, 1);
    expect(getCartKey()).toBe('abc123');
  });

  test('sends cart_key in subsequent requests', async () => {
    const first = jest.fn(async () => ({ cart_key: 'abc123', items: [] }));
    jest.unstable_mockModule('../PetIA/js/api.js', () => ({ apiRequest: first }));
    const { addItem } = await import('../PetIA/js/cart.js');
    sessionStorage.setItem('token', 't');
    await addItem(1, 1);

    jest.resetModules();
    const second = jest.fn(async () => ({ items: [] }));
    jest.unstable_mockModule('../PetIA/js/api.js', () => ({ apiRequest: second }));
    const { addItem: addItem2 } = await import('../PetIA/js/cart.js');
    sessionStorage.setItem('token', 't');
    await addItem2(2, 1);
    const url = second.mock.calls[0][0];
    expect(url).toMatch(/cart_key=abc123/);
  });

  test('includes variation in request body', async () => {
    const mockApi = jest.fn(async () => ({ items: [] }));
    jest.unstable_mockModule('../PetIA/js/api.js', () => ({ apiRequest: mockApi }));
    const { addItem } = await import('../PetIA/js/cart.js');
    sessionStorage.setItem('token', 't');
    const variation = { attribute_pa_color: 'blue', attribute_pa_size: 'm' };
    await addItem(10, 1, variation);
    const body = JSON.parse(mockApi.mock.calls[0][1].body);
    expect(body.variation).toEqual(variation);
  });
});

