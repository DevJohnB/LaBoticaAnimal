import { apiRequest } from './api.js';
import config from '../config.js';
import { getToken } from './token.js';

// Local storage keys
const CART_KEY_STORAGE = 'cart_key';
const LOCAL_CART_STORAGE = 'local_cart';

function getStoredCartKey() {
  if (typeof localStorage === 'undefined') return null;
  return localStorage.getItem(CART_KEY_STORAGE);
}

function storeCartKey(key) {
  if (typeof localStorage === 'undefined' || !key) return;
  localStorage.setItem(CART_KEY_STORAGE, key);
}

function buildEndpoint(endpoint) {
  const cartKey = getStoredCartKey();
  if (!cartKey) return endpoint;
  const sep = endpoint.includes('?') ? '&' : '?';
  return `${endpoint}${sep}cart_key=${encodeURIComponent(cartKey)}`;
}

function getLocalCart() {
  if (typeof localStorage === 'undefined') return [];
  try {
    return JSON.parse(localStorage.getItem(LOCAL_CART_STORAGE)) || [];
  } catch {
    return [];
  }
}

function setLocalCart(items) {
  if (typeof localStorage === 'undefined') return;
  localStorage.setItem(LOCAL_CART_STORAGE, JSON.stringify(items));
}

function buildItemKey(id, variation) {
  if (!variation) return String(id);
  const attrs = Object.keys(variation)
    .sort()
    .map(k => `${k}:${variation[k]}`)
    .join('|');
  return `${id}-${attrs}`;
}

function clearLocalCart() {
  if (typeof localStorage === 'undefined') return;
  localStorage.removeItem(LOCAL_CART_STORAGE);
}

async function cartRequest(endpoint, options) {
  const data = await apiRequest(buildEndpoint(endpoint), options);
  if (data && data.cart_key) {
    storeCartKey(data.cart_key);
  }
  return data;
}

export async function syncLocalCart() {
  if (!getToken()) return;
  const items = getLocalCart();
  for (const item of items) {
    await cartRequest(config.endpoints.cartAddItem, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: item.id,
        quantity: item.quantity,
        ...(item.variation ? { variation: item.variation } : {}),
      }),
    });
  }
  clearLocalCart();
}

export function getCart() {
  if (!getToken()) {
    return Promise.resolve({ items: getLocalCart() });
  }
  return cartRequest(config.endpoints.cart);
}

export function addItem(productId, quantity, variation) {
  if (!getToken()) {
    const cart = getLocalCart();
    const key = buildItemKey(productId, variation);
    const existing = cart.find(i => i.key === key);
    if (existing) {
      existing.quantity += quantity;
    } else {
      cart.push({ id: productId, quantity, variation, key });
    }
    setLocalCart(cart);
    return Promise.resolve({ items: cart });
  }
  return cartRequest(config.endpoints.cartAddItem, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      id: productId,
      quantity,
      ...(variation ? { variation } : {}),
    }),
  });
}

export function updateItem(itemKey, quantity) {
  if (!getToken()) {
    const cart = getLocalCart();
    const item = cart.find(i => String(i.key || i.id) === String(itemKey));
    if (item) item.quantity = quantity;
    setLocalCart(cart);
    return Promise.resolve({ items: cart });
  }
  return cartRequest(config.endpoints.cartUpdateItem, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ key: itemKey, quantity }),
  });
}

export function removeItem(itemKey) {
  if (!getToken()) {
    const cart = getLocalCart().filter(
      i => String(i.key || i.id) !== String(itemKey)
    );
    setLocalCart(cart);
    return Promise.resolve({ items: cart });
  }
  return cartRequest(config.endpoints.cartRemoveItem, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ key: itemKey }),
  });
}

export { getStoredCartKey as getCartKey };

