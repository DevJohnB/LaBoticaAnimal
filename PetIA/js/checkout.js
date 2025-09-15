import { ensureAuth } from './token.js';
import { getCart } from './cart.js';
import { apiRequest } from './api.js';
import config from '../config.js';

async function renderCart() {
  try {
    const cart = await getCart();
    const list = document.getElementById('summary-items');
    const totalEl = document.getElementById('summary-total');
    list.innerHTML = '';
    (cart.items || []).forEach(item => {
      const li = document.createElement('li');
      const price = item.totals?.line_total ?? '';
      li.textContent = `${item.name} x${item.quantity} - ${price}`;
      list.appendChild(li);
    });
    totalEl.textContent = cart.totals?.total ? `Total: ${cart.totals.total}` : '';
  } catch (error) {
    console.error('Error al cargar el carrito:', error);
  }
}

async function loadPaymentMethods() {
  try {
    const methods = await apiRequest(config.endpoints.paymentMethods);
    const select = document.getElementById('payment-method');
    select.innerHTML = '<option value="">Método de pago</option>';
    (methods || []).forEach(method => {
      const option = document.createElement('option');
      option.value = method.id || method.name;
      option.textContent = method.title || method.name;
      select.appendChild(option);
    });
  } catch (error) {
    console.error('Error al obtener métodos de pago:', error);
  }
}

async function submitCheckout(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const msg = document.getElementById('checkout-message');
  msg.textContent = '';
  msg.className = 'checkout-message';
  const data = Object.fromEntries(new FormData(form).entries());
  try {
    await apiRequest(config.endpoints.checkout, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    msg.textContent = 'Pedido realizado con éxito';
    msg.classList.add('success');
    form.reset();
  } catch (error) {
    msg.textContent = error.message || 'Error al procesar el pedido';
    msg.classList.add('error');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  if (!ensureAuth()) return;
  renderCart();
  loadPaymentMethods();
  document
    .getElementById('checkout-form')
    .addEventListener('submit', submitCheckout);
});
