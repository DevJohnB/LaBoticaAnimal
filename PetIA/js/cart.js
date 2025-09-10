import { apiRequest } from './api.js';

export function getCart() {
  return apiRequest('/wp-json/petia-app-bridge/v1/wc-store/cart');
}

export function addItem(productId, quantity) {
  return apiRequest('/wc-store/cart/add-item', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: productId, quantity }),
  });
}

export function updateItem(itemKey, quantity) {
  return apiRequest('/wc-store/cart/update-item', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ key: itemKey, quantity }),
  });
}

export function removeItem(itemKey) {
  return apiRequest('/wc-store/cart/remove-item', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ key: itemKey }),
  });
}

